<?php

namespace App\Certificates\Controllers;

use Ahl\Certificates\Certificates as Engine;
use Ahl\Certificates\CertificateService;
use Ahl\Certificates\Models\Certificate;
use App\Certificates\Contracts\CertificateContext;
use App\Certificates\Support\CertificateBundles;
use App\Certificates\Support\CertificatePresenter;
use App\Certificates\Support\CertificateRealtime;
use App\Certificates\Support\Contexts;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * The organiser's side, for one context: read where things stand, choose what
 * is issued and when, draft what has been earned, approve, revoke, delete a
 * draft. The context's provider decides who runs it; the policy re-checks each
 * certificate; nothing trusts a status, an amount of certificates or a holder
 * sent by the client.
 */
class ManageController extends Controller
{
    use Concerns;

    /** Approving draws and stores a PDF each, so the console sends them in batches. */
    public const APPROVE_BATCH = 15;

    /** Deleting a draft is cheap (it has no file yet); this only caps one request. */
    public const DELETE_BATCH = 200;

    public function show(Request $request, string $context, string $key, CertificatePresenter $presenter): JsonResponse
    {
        [$provider, $model] = $this->managedContext($context, $key, $request);

        return response()->json(['success' => true] + $this->state($provider, $model, $presenter));
    }

    public function settings(Request $request, string $context, string $key, CertificatePresenter $presenter): JsonResponse
    {
        [$provider, $model] = $this->managedContext($context, $key, $request);
        $data = $request->validate([
            'participation' => ['sometimes', 'boolean'],
            'podium' => ['sometimes', 'boolean'],
            'officials' => ['sometimes', 'boolean'],
            'clubs' => ['sometimes', 'boolean'],
            'trigger' => ['sometimes', Rule::in(['division', 'event', 'manual'])],
        ]);
        $provider->saveSettings($model, $data);
        $this->tellOthers($provider, $model, $request);

        return response()->json(['success' => true, 'message' => __('cert::messages.settings_saved')] + $this->state($provider, $model, $presenter));
    }

    public function generate(Request $request, string $context, string $key, CertificatePresenter $presenter): JsonResponse
    {
        [$provider, $model] = $this->managedContext($context, $key, $request);
        // Optionally one kind only — issuing the clubs' certificates from the
        // federation view drafts nothing else (EventCertificates::generate $only).
        $type = $request->validate(['type' => ['sometimes', 'nullable', Rule::in($provider->types())]])['type'] ?? null;
        $r = $type ? $provider->generate($model, $request->user(), $type) : $provider->generate($model, $request->user());
        $this->tellOthers($provider, $model, $request);

        $message = $r['created'] > 0
            ? trans_choice('cert::messages.generated', $r['created'], ['count' => $r['created']])
            : __('cert::messages.generated_none');
        if (($r['pending'] ?? 0) > 0) {
            $message .= ' '.trans_choice('cert::messages.still_running', $r['pending'], ['count' => $r['pending']]);
        }

        return response()->json(['success' => true, 'message' => $message, 'created' => $r['created']] + $this->state($provider, $model, $presenter));
    }

    /**
     * Approve drafts in this context: the uuids given, or — with `all` — the next
     * batch of drafts. Returns how many remain, so the console can keep going.
     */
    public function approve(Request $request, string $context, string $key, CertificateService $service, CertificatePresenter $presenter): JsonResponse
    {
        [$provider, $model] = $this->managedContext($context, $key, $request);
        $data = $request->validate([
            'uuids' => ['sometimes', 'array', 'max:'.self::APPROVE_BATCH],
            'uuids.*' => ['uuid'],
            'all' => ['sometimes', 'boolean'],
            // Optionally one kind only, so issuing one kind never approves another's drafts.
            'type' => ['sometimes', 'nullable', Rule::in($provider->types())],
        ]);

        $drafts = Engine::model()::query()->forContext($model)->where('status', 'draft')->orderBy('id')
            ->when($data['type'] ?? null, fn ($q, $t) => $q->where('type', $t));
        if (! empty($data['uuids'])) {
            $drafts->whereIn('uuid', $data['uuids']);
        } elseif (! ($data['all'] ?? false)) {
            return response()->json(['success' => false, 'message' => __('cert::messages.nothing_selected')], 422);
        }

        $approved = 0;
        foreach ($drafts->limit(self::APPROVE_BATCH)->get() as $c) {
            if (Gate::forUser($request->user())->allows('approve', $c)) {
                $service->approve($c, $request->user());
                $approved++;
                CertificateRealtime::refresh([CertificateRealtime::holder($c)]);
            }
        }

        Log::info('certificates.approved', ['context' => $provider->alias(), 'key' => $key, 'by' => $request->user()->id, 'count' => $approved]);
        $this->tellOthers($provider, $model, $request);
        $remaining = Engine::model()::query()->forContext($model)->where('status', 'draft')
            ->when($data['type'] ?? null, fn ($q, $t) => $q->where('type', $t))->count();

        return response()->json([
            'success' => true,
            'message' => trans_choice('cert::messages.approved', $approved, ['count' => $approved]),
            'approved' => $approved,
            'remaining' => $remaining,
        ] + $this->state($provider, $model, $presenter));
    }

    public function revoke(Request $request, string $cert, CertificateService $service, CertificatePresenter $presenter): JsonResponse
    {
        $c = $this->certificate($cert, $request, 'revoke');
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:300']]);

        $service->revoke($c, $request->user(), strip_tags($data['reason']));
        Log::info('certificates.revoked', ['certificate' => $c->uuid, 'by' => $request->user()->id]);
        CertificateRealtime::refresh([CertificateRealtime::holder($c)]);
        [$provider, $model] = $this->contextOf($c);
        if ($provider && $model) {
            $this->tellOthers($provider, $model, $request);
        }

        return response()->json(['success' => true, 'message' => __('cert::messages.revoked'), 'certificate' => $presenter->managed($c->fresh(), $this->locale())]);
    }

    public function destroy(Request $request, string $cert, CertificateService $service): JsonResponse
    {
        $c = $this->certificate($cert, $request, 'delete');
        $uuid = $c->uuid;
        [$provider, $model] = $this->contextOf($c);

        $service->delete($c);   // a draft only; its file goes first
        if ($provider && $model) {
            $this->tellOthers($provider, $model, $request);
        }

        return response()->json(['success' => true, 'message' => __('cert::messages.deleted'), 'uuid' => $uuid]);
    }

    /**
     * Delete drafts in this context in one go: the uuids given, or — with `all` — every
     * draft, DELETE_BATCH per request. Only drafts: an issued certificate is revoked,
     * never deleted, because its QR code is already out in the world.
     */
    /**
     * Delete EVERY certificate in this context — drafts, issued and revoked — with its
     * file, a batch at a time (the console keeps calling while `remaining` > 0). Only for
     * someone who may manage the context; logged, and each holder's list refreshes.
     */
    public function destroyAll(Request $request, string $context, string $key, CertificateService $service, CertificatePresenter $presenter): JsonResponse
    {
        [$provider, $model] = $this->managedContext($context, $key, $request);
        $request->validate(['confirm' => ['required', 'in:delete-all']]);

        $deleted = 0;
        $holders = [];
        foreach (Engine::model()::query()->forContext($model)->orderBy('id')->limit(self::DELETE_BATCH)->get() as $c) {
            $holders[] = CertificateRealtime::holder($c);
            $service->destroy($c);
            $deleted++;
        }

        Log::warning('certificates.all_deleted', ['context' => $provider->alias(), 'key' => $key, 'by' => $request->user()->id, 'count' => $deleted]);
        CertificateRealtime::refresh(array_values(array_unique(array_filter($holders))));
        $this->tellOthers($provider, $model, $request);

        return response()->json([
            'success' => true,
            'message' => trans_choice('cert::messages.deleted_many', $deleted, ['count' => $deleted]),
            'deleted' => $deleted,
            'remaining' => Engine::model()::query()->forContext($model)->count(),
        ] + $this->state($provider, $model, $presenter));
    }

    public function destroyDrafts(Request $request, string $context, string $key, CertificateService $service, CertificatePresenter $presenter): JsonResponse
    {
        [$provider, $model] = $this->managedContext($context, $key, $request);
        $data = $request->validate([
            'uuids' => ['sometimes', 'array', 'max:'.self::DELETE_BATCH],
            'uuids.*' => ['uuid'],
            'all' => ['sometimes', 'boolean'],
        ]);

        $drafts = Engine::model()::query()->forContext($model)->where('status', 'draft')->orderBy('id');
        if (! empty($data['uuids'])) {
            $drafts->whereIn('uuid', $data['uuids']);
        } elseif (! ($data['all'] ?? false)) {
            return response()->json(['success' => false, 'message' => __('cert::messages.nothing_selected')], 422);
        }

        $deleted = 0;
        foreach ($drafts->limit(self::DELETE_BATCH)->get() as $c) {
            if (Gate::forUser($request->user())->allows('delete', $c)) {
                $service->delete($c);   // its file (if one was ever drawn) goes first
                $deleted++;
            }
        }

        Log::info('certificates.drafts_deleted', ['context' => $provider->alias(), 'key' => $key, 'by' => $request->user()->id, 'count' => $deleted]);
        $this->tellOthers($provider, $model, $request);
        $remaining = Engine::model()::query()->forContext($model)->where('status', 'draft')->count();

        return response()->json([
            'success' => true,
            'message' => trans_choice('cert::messages.deleted_many', $deleted, ['count' => $deleted]),
            'deleted' => $deleted,
            'remaining' => $remaining,
        ] + $this->state($provider, $model, $presenter));
    }

    /**
     * Issue one kind of certificate to the holders chosen, and only them (owner,
     * 2026-10-20): draft what they have earned and not been given, then approve
     * their drafts. Answers with every issued certificate of theirs of that kind,
     * so the caller can go straight on to a PDF of exactly these.
     */
    public function issueSelected(Request $request, string $context, string $key, CertificateService $service): JsonResponse
    {
        [$provider, $model] = $this->managedContext($context, $key, $request);
        abort_unless(method_exists($provider, 'generateFor') && method_exists($provider, 'holderKey'), 404);
        $data = $request->validate([
            'type' => ['required', Rule::in($provider->types())],
            'holders' => ['required', 'array', 'min:1', 'max:1000'],
            'holders.*' => ['string', 'max:200'],
        ]);
        $want = array_fill_keys($data['holders'], true);

        $made = $provider->generateFor($model, $request->user(), $data['type'], $data['holders']);
        $theirs = fn () => Engine::model()::query()->forContext($model)->where('type', $data['type'])->whereIn('status', ['draft', 'approved'])
            ->with('recipient')->orderBy('id')->get()->filter(fn ($c) => $c->recipient && isset($want[$provider->holderKey($c->recipient)]));

        $approved = 0;
        foreach ($theirs()->filter(fn ($c) => $c->isDraft()) as $c) {
            if (Gate::forUser($request->user())->allows('approve', $c)) {
                $service->approve($c, $request->user());
                $approved++;
                CertificateRealtime::refresh([CertificateRealtime::holder($c)]);
            }
        }
        $issued = $theirs()->filter(fn ($c) => ! $c->isDraft())->values();

        Log::info('certificates.issued_selected', ['context' => $provider->alias(), 'key' => $key, 'type' => $data['type'], 'by' => $request->user()->id, 'holders' => count($want), 'approved' => $approved]);
        $this->tellOthers($provider, $model, $request);

        return response()->json([
            'success' => true,
            'message' => trans_choice('cert::messages.issued_selected', $approved, ['count' => $approved, 'total' => $issued->count()]),
            'issued' => $approved,
            'pending' => $made['pending'] ?? 0,
            'uuids' => $issued->pluck('uuid')->values()->all(),
        ]);
    }

    /**
     * A chosen set of issued certificates of one kind, as a print-shop file:
     * remembered for two days under a token, and bundled like any other type
     * (`<type>.sel-<token>` — CertificateBundles reads it back).
     */
    public function selection(Request $request, string $context, string $key, CertificateBundles $bundles): JsonResponse
    {
        [$provider, $model] = $this->managedContext($context, $key, $request);
        // Chosen by certificate (the console's ticks) or by holder (a federation list's ticks).
        $data = $request->validate([
            'type' => ['required', Rule::in($provider->types())],
            'uuids' => ['required_without:holders', 'array', 'max:2000'],
            'uuids.*' => ['uuid'],
            'holders' => ['required_without:uuids', 'array', 'max:2000'],
            'holders.*' => ['string', 'max:200'],
        ]);
        // Badges are pages, not certificate records: a chosen set is the officials
        // themselves (EventBadges::pageSelection), never approved certificates.
        if ($provider instanceof \App\Certificates\Contracts\PrintsPages && method_exists($provider, 'pageSelection')) {
            $ids = $provider->pageSelection($model, $data['holders'] ?? []);
            if ($ids === []) {
                return response()->json(['success' => false, 'message' => __('cert::messages.selection_none')], 422);
            }

            return response()->json(['success' => true, 'type' => $bundles->remember($provider, $model, $data['type'], $ids), 'count' => count($ids)]);
        }
        $issued = Engine::model()::query()->forContext($model)->where('type', $data['type'])->where('status', 'approved')->orderBy('id');
        if (! empty($data['uuids'])) {
            $ids = $issued->whereIn('uuid', $data['uuids'])->pluck('id')->map(fn ($id) => (int) $id)->all();
        } else {
            abort_unless(method_exists($provider, 'holderKey'), 404);
            $want = array_fill_keys($data['holders'] ?? [], true);
            $ids = $issued->with('recipient')->get()->filter(fn ($c) => $c->recipient && isset($want[$provider->holderKey($c->recipient)]))
                ->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        }
        if ($ids === []) {
            return response()->json(['success' => false, 'message' => __('cert::messages.selection_none')], 422);
        }

        return response()->json(['success' => true, 'type' => $bundles->remember($provider, $model, $data['type'], $ids), 'count' => count($ids)]);
    }

    /**
     * One design's issued certificates as one PDF, for the print shop: start building it
     * (or find it already built). The file is drawn in the background; the answer says
     * how far along it is, and the console hears when it is ready.
     */
    public function bundle(Request $request, string $context, string $key, string $type, CertificateBundles $bundles): JsonResponse
    {
        [$provider, $model] = $this->managedContext($context, $key, $request);
        abort_unless($bundles->accepts($provider, $model, $type), 404);

        $state = $request->isMethod('post')
            ? $bundles->start($provider, $model, $type, $request->user()->id)
            : $bundles->state($provider, $model, $type);
        if ($request->isMethod('post')) {
            Log::info('certificates.bundle_requested', ['context' => $provider->alias(), 'key' => $key, 'type' => $type, 'by' => $request->user()->id, 'state' => $state['state']]);
        }

        return response()->json(['success' => true, 'type' => $type] + $state);
    }

    /** The built file. Private and never cached by a shared cache, like every certificate file. */
    public function bundleDownload(Request $request, string $context, string $key, string $type, CertificateBundles $bundles)
    {
        [$provider, $model] = $this->managedContext($context, $key, $request);
        abort_unless($bundles->accepts($provider, $model, $type), 404);
        $path = $bundles->path($provider, $model, $type);
        abort_unless($path && \Illuminate\Support\Facades\Storage::disk('local')->exists($path), 404);
        $name = \Illuminate\Support\Str::slug($provider->headline($model)) ?: 'certificates';

        return \Illuminate\Support\Facades\Storage::disk('local')->download($path, $name.'-'.$type.'.pdf', [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /* ───────────────────────────────────────────────────────────────── */

    private function state(CertificateContext $provider, Model $model, CertificatePresenter $presenter): array
    {
        $locale = $this->locale();
        $all = Engine::model()::query()->forContext($model)->with(['recipient', 'context'])->orderBy('status')->orderByDesc('id')->limit(500)->get();
        $counts = Engine::model()::query()->forContext($model)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return [
            'settings' => $provider->settings($model),
            'eligible' => $provider->eligible($model),
            'counts' => [
                'draft' => (int) ($counts['draft'] ?? 0),
                'approved' => (int) ($counts['approved'] ?? 0),
                'revoked' => (int) ($counts['revoked'] ?? 0),
            ],
            'certificates' => $all->map(fn (Certificate $c) => $presenter->managed($c, $locale))->values(),
            'design_url' => route('me.certificates.design', ['context' => $provider->alias(), 'key' => $provider->key($model)]),
            'batch' => self::APPROVE_BATCH,
            'bundles' => app(CertificateBundles::class)->states($provider, $model),
        ];
    }

    /** Every other person running this context re-reads their console. */
    private function tellOthers(CertificateContext $provider, Model $model, Request $request): void
    {
        CertificateRealtime::refresh($provider->audience($model), $request->user()?->id);
    }

    private function contextOf(Certificate $c): array
    {
        $model = $c->context_id ? $c->context : null;

        return [app(Contexts::class)->forModel($model), $model];
    }
}
