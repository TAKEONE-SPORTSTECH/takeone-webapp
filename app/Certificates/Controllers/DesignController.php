<?php

namespace App\Certificates\Controllers;

use Ahl\Certificates\Certificates as Engine;
use Ahl\Certificates\CertificateService;
use Ahl\Certificates\Contracts\FileStore;
use Ahl\Certificates\Models\CertificateTemplate;
use Ahl\Certificates\Support\Format;
use Ahl\Certificates\Support\Formats;
use Ahl\Certificates\Support\Layout;
use App\Certificates\Contracts\CertificateContext;
use App\Certificates\Support\CertificateImages;
use App\Certificates\Support\TakeOneFileStore;
use App\Http\Controllers\Controller;
use App\Members\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The design room: the wording (in every language), the two signatures, the
 * background and whose design it is.
 *
 * WHOSE DESIGN is the one decision that needs care. A context's chain names
 * its owners most specific first — for an event, [this event, its host]. The
 * design in force is the first that exists. The organiser chooses a SCOPE:
 *
 *   event  this event's own design — anyone who runs the event may edit it
 *   host   the host's default, which every event of that host inherits — only
 *          the host's own people (CertificateContext::canManageOwner)
 *
 * Moving between them carries the design across (wording, settings and the
 * signature/artwork files), so nothing the organiser made is lost by choosing.
 * Owners are named by scope on the wire, never by numeric id.
 */
class DesignController extends Controller
{
    use Concerns;

    public function page(Request $request, string $context, string $key)
    {
        [$provider, $model] = $this->managedContext($context, $key, $request);

        // With the new designer switched on (App\Printables), this room hands over to it,
        // on the same certificate type. `?classic=1` still opens this room.
        if (\App\Printables\Designer::enabled() && ! $request->boolean('classic')) {
            $type = in_array($request->query('type'), $provider->types(), true) ? $request->query('type') : ($provider->types()[0] ?? null);
            $url = \App\Printables\Designer::url($model, 'certificate', $type, $request->query('back'));
            if ($url) {
                return redirect()->to($url);
            }
        }

        $isMobile = (bool) $request->attributes->get('is_mobile');

        return view($isMobile ? 'cert::design.mobile' : 'cert::design.desktop', [
            'state' => $this->build($provider, $model, $request->user()),
            'headline' => $provider->headline($model),
            'accent' => $provider->brand($model)['accent'] ?? '#6d5bd0',
            'endpoints' => $this->endpoints($provider, $model),
            // Officials' badges use this same room on a badge-sized page; only its words differ.
            'badges' => $provider instanceof \App\Events\Support\EventBadges,
            'backUrl' => $request->query('back') && str_starts_with((string) $request->query('back'), '/') && ! str_starts_with((string) $request->query('back'), '//')
                ? (string) $request->query('back') : route('me.certificates.index'),
        ]);
    }

    public function state(Request $request, string $context, string $key): JsonResponse
    {
        [$provider, $model] = $this->managedContext($context, $key, $request);

        return response()->json(['success' => true] + $this->build($provider, $model, $request->user()));
    }

    public function update(Request $request, string $context, string $key, string $type, CertificateService $service): JsonResponse
    {
        [$provider, $model] = $this->managedContext($context, $key, $request);
        abort_unless(in_array($type, $provider->types(), true), 404);

        $data = $request->validate($this->fieldRules(true));
        $format = $this->sized(Formats::forType($type), $data, $service->templateFor($type, $model));
        $fields = $this->fields($data, $format);
        // A certificate says what it is and why; another format (a badge) may carry no wording at all.
        if ($format->key === Formats::CERTIFICATE && (! array_filter($fields['title']) || ! array_filter($fields['body']))) {
            return response()->json(['success' => false, 'message' => __('cert::messages.wording_required')], 422);
        }

        $target = $this->ownerFor($provider, $model, $data['scope']);
        abort_unless($target && $provider->canManageOwner($model, $target, $request->user()), 403);

        DB::transaction(function () use ($service, $provider, $model, $type, $target, $fields) {
            $current = $service->templateFor($type, $model);
            $this->moveTo($current, $target, $provider, $model)->fill($fields)->save();
        });

        return response()->json(['success' => true, 'message' => __('cert::messages.design_saved')] + $this->build($provider, $model, $request->user()));
    }

    public function preview(Request $request, string $context, string $key, string $type, CertificateService $service)
    {
        [$provider, $model] = $this->managedContext($context, $key, $request);
        abort_unless(in_array($type, $provider->types(), true), 404);
        $locale = Engine::locale($request->query('locale'));

        $template = $service->templateFor($type, $model);

        // POST: preview the form as it is on screen, before it is saved. A copy of
        // the design in force (same uuid, so its signatures and artwork are found)
        // with the unsaved fields laid over it — never written.
        if ($request->isMethod('post')) {
            $data = $request->validate($this->fieldRules(false));
            $template = $template->replicate();
            $template->uuid = $service->templateFor($type, $model)->uuid;
            $template->fill($this->fields($data, $this->sized(Formats::forType($type), $data, $template)));
        }

        // The same number of pixels across whatever the page's size: a small badge is drawn
        // at a higher resolution, so the editor shows it as crisply as a certificate.
        $format = $template->format();
        $dpi = (int) round(80 * Layout::PAGE_W / $format->long);
        $purpose = $request->boolean('print') ? CertificateService::PURPOSE_PRINT : CertificateService::PURPOSE_DOWNLOAD;
        $png = $provider instanceof \App\Events\Support\EventBadges && $model instanceof \App\Events\Models\ClubEvent
            ? $service->renderDocument($template, $locale, $provider->sampleFields($model, $locale), $model, $purpose, $dpi)
            : $service->preview($template, $locale, $model, $purpose, $dpi);

        // Sent as a JPEG: a tenth of the PNG's weight, and a preview is only looked at.
        $im = @imagecreatefromstring($png);
        if ($im instanceof \GdImage) {
            ob_start();
            imagejpeg($im, null, 84);
            $png = (string) ob_get_clean();
            imagedestroy($im);
        }

        // Where each element's ink landed, so the editor's handles hug the words (Support\Drawn).
        return response($png, 200, [
            'Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
            'X-Cert-Drawn' => json_encode($service->drawn(), JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function signature(Request $request, string $context, string $key, string $type, int $slot, CertificateService $service, FileStore $files)
    {
        [$provider, $model] = $this->managedContext($context, $key, $request);
        abort_unless(in_array($type, $provider->types(), true), 404);
        $file = $files->get($service->templateFor($type, $model), "signature_{$slot}");
        abort_unless($file, 404);

        return response($file['contents'], 200, ['Content-Type' => $file['mime'], 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function storeSignature(Request $request, string $context, string $key, string $type, int $slot, CertificateService $service, FileStore $files, CertificateImages $images): JsonResponse
    {
        return $this->withEditable($request, $context, $key, $type, $service, function (CertificateTemplate $t) use ($request, $slot, $files, $images) {
            $request->validate(['image' => ['required', 'file', 'max:'.(CertificateImages::MAX_SIGNATURE_BYTES / 1024)]]);
            $r = $images->signature($request->file('image'));
            if (! $r['ok']) {
                return $r['error'];
            }
            $files->put($t, "signature_{$slot}", $r['bytes'], $r['mime'], "signature_{$slot}");

            return null;
        }, 'signature_saved');
    }

    public function destroySignature(Request $request, string $context, string $key, string $type, int $slot, CertificateService $service, FileStore $files): JsonResponse
    {
        return $this->withEditable($request, $context, $key, $type, $service, function (CertificateTemplate $t) use ($slot, $files) {
            $files->delete($t, "signature_{$slot}");

            return null;
        }, 'signature_removed');
    }

    public function storeArtwork(Request $request, string $context, string $key, string $type, CertificateService $service, FileStore $files, CertificateImages $images): JsonResponse
    {
        $small = false;
        $response = $this->withEditable($request, $context, $key, $type, $service, function (CertificateTemplate $t) use ($request, $files, $images, &$small) {
            $request->validate(['image' => ['required', 'file', 'max:'.(CertificateImages::MAX_ARTWORK_BYTES / 1024)]]);
            $r = $images->artwork($request->file('image'), $t->pageOrientation());
            if (! $r['ok']) {
                return $r['error'];
            }
            $files->put($t, CertificateTemplate::BACKGROUND_SLOT, $r['bytes'], $r['mime'], 'background');
            $files->put($t, CertificateTemplate::BACKGROUND_SOURCE_SLOT, $r['source'], 'image/jpeg', 'background_source');
            // A new picture starts the way the page used to place it; the organiser changes it from there.
            $t->forceFill(['background' => 'artwork', 'artwork_fit' => $r['fit']])->save();
            $small = isset($r['warning']);

            return null;
        }, 'artwork_saved');

        // Kept, but honest about it: an enlarged picture may print soft.
        if ($small && $response->getStatusCode() === 200) {
            $response->setData(['message' => __('cert::messages.artwork_saved_small')] + (array) $response->getData(true));
        }

        return $response;
    }

    public function destroyArtwork(Request $request, string $context, string $key, string $type, CertificateService $service, FileStore $files): JsonResponse
    {
        return $this->withEditable($request, $context, $key, $type, $service, function (CertificateTemplate $t) use ($files) {
            $files->delete($t, CertificateTemplate::BACKGROUND_SLOT);
            $files->delete($t, CertificateTemplate::BACKGROUND_SOURCE_SLOT);
            $t->forceFill(['artwork_fit' => null] + ($t->background === 'artwork' ? ['background' => 'plain'] : []))->save();

            return null;
        }, 'artwork_removed');
    }

    /* ───────────────────────────────────────────────────────────────── */

    private function fieldRules(bool $saving): array
    {
        return [
            'scope' => [$saving ? 'required' : 'nullable', Rule::in(['event', 'host'])],
            'title' => ['required', 'array'],
            'title.*' => ['nullable', 'string', 'max:150'],
            'body' => ['required', 'array'],
            'body.*' => ['nullable', 'string', 'max:800'],
            'signature1_name' => ['nullable', 'string', 'max:120'],
            'signature1_title' => ['nullable', 'string', 'max:120'],
            'signature2_name' => ['nullable', 'string', 'max:120'],
            'signature2_title' => ['nullable', 'string', 'max:120'],
            'ornament_level' => ['required', Rule::in(CertificateTemplate::ORNAMENTS)],
            'background' => ['required', Rule::in(CertificateTemplate::BACKGROUNDS)],
            'show_photo' => ['boolean'],
            'accent' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'print_offset_x' => ['integer', 'between:-30,30'],
            'print_offset_y' => ['integer', 'between:-30,30'],
            // Where each element sits (Support\Layout::sanitize keeps only known fields, on the page).
            'layout' => ['nullable', 'array', 'max:40'],
            // Which way up the page is; a portrait page keeps its own arrangement.
            'orientation' => ['nullable', Rule::in(Layout::ORIENTATIONS)],
            'layout_portrait' => ['nullable', 'array', 'max:40'],
            // The organiser's own texts and erased areas (Support\Layout::customs keeps only key, words, style / page).
            'elements' => ['nullable', 'array', 'max:'.(Layout::MAX_CUSTOM + Layout::MAX_ERASE)],
            'artwork_fit' => ['nullable', Rule::in(CertificateTemplate::ARTWORK_FITS)],
            'print_bare' => ['nullable', 'boolean'],
            // The page's own size in mm, long side first (1.4); null = the format's own (A4 / A6).
            'page_size' => ['nullable', 'array'],
            'page_size.long' => ['required_with:page_size', 'numeric', 'between:'.CertificateTemplate::PAGE_MIN_MM.','.CertificateTemplate::PAGE_MAX_MM],
            'page_size.short' => ['required_with:page_size', 'numeric', 'between:'.CertificateTemplate::PAGE_MIN_MM.','.CertificateTemplate::PAGE_MAX_MM],
        ];
    }

    /** The validated form as template attributes: text stripped of markup, one entry per locale. */
    private function fields(array $data, ?Format $format = null): array
    {
        $format ??= Formats::get(Formats::CERTIFICATE);
        $clean = fn ($map) => collect(Engine::locales())->mapWithKeys(fn ($l) => [$l => trim(strip_tags((string) ($map[$l] ?? '')))])->all();
        $line = fn ($v) => ($v = trim(strip_tags((string) $v))) === '' ? null : $v;

        // Absent is not blank: a caller that never sent the page's orientation leaves it alone.
        $page = [];
        if (array_key_exists('orientation', $data)) {
            // Stored only when the page is turned from its format's own way (a certificate lies flat, a badge stands).
            $o = Layout::orientation($data['orientation']);
            $page['orientation'] = $o === $format->defaultOrientation ? null : $o;
        }
        if (array_key_exists('elements', $data)) {
            $page['elements'] = ($e = Layout::customs($data['elements'] ?? [], Engine::locales())) === [] ? null : $e;
        }
        if (array_key_exists('page_size', $data)) {
            // Stored only when it differs from the format's own page.
            $own = Formats::get($format->key);
            $size = CertificateTemplate::sizeOf($data['page_size'] ?? null);
            $page['page_size'] = $size && (abs($size[0] - $own->long) >= 0.05 || abs($size[1] - $own->short) >= 0.05)
                ? ['long' => round($size[0], 1), 'short' => round($size[1], 1)] : null;
        }
        if (array_key_exists('print_bare', $data)) {
            $page['print_bare'] = (bool) $data['print_bare'];
        }
        if (array_key_exists('artwork_fit', $data)) {
            $page['artwork_fit'] = $data['artwork_fit'];
        }
        if (array_key_exists('layout_portrait', $data)) {
            $page['layout_portrait'] = ($l = Layout::sanitize((array) ($data['layout_portrait'] ?? []), 'portrait', [], $format)) === [] ? null : $l;
        }

        return $page + [
            'title' => $clean($data['title'] ?? []),
            'body' => $clean($data['body'] ?? []),
            'signature1_name' => $line($data['signature1_name'] ?? null),
            'signature1_title' => $line($data['signature1_title'] ?? null),
            'signature2_name' => $line($data['signature2_name'] ?? null),
            'signature2_title' => $line($data['signature2_title'] ?? null),
            'ornament_level' => $data['ornament_level'],
            'background' => $data['background'],
            'show_photo' => (bool) ($data['show_photo'] ?? false),
            'accent' => $data['accent'] ?? null,
            'print_offset_x' => (int) ($data['print_offset_x'] ?? 0),
            'print_offset_y' => (int) ($data['print_offset_y'] ?? 0),
            'layout' => ($l = Layout::sanitize((array) ($data['layout'] ?? []), 'landscape', [], $format)) === [] ? null : $l,
        ];
    }

    /** A file change goes to the design in force — and only if this user may change THAT design. */
    private function withEditable(Request $request, string $context, string $key, string $type, CertificateService $service, \Closure $change, string $done): JsonResponse
    {
        [$provider, $model] = $this->managedContext($context, $key, $request);
        abort_unless(in_array($type, $provider->types(), true), 404);
        $template = $service->templateFor($type, $model);
        abort_unless($provider->canManageOwner($model, [(string) $template->owner_type, (int) $template->owner_id], $request->user()), 403);

        $error = $change($template);
        if ($error !== null) {
            return response()->json(['success' => false, 'message' => $error], 422);
        }
        $template->touch();

        return response()->json(['success' => true, 'message' => __('cert::messages.'.$done)] + $this->build($provider, $model, $request->user()));
    }

    /**
     * The template for $target, made from $current when they differ — fields and
     * files copied across. Leaving the event's own design for the host's removes
     * the event's copy (its files first), so the event follows the host again.
     */
    private function moveTo(CertificateTemplate $current, array $target, CertificateContext $provider, Model $model): CertificateTemplate
    {
        if ($current->ownedBy($target)) {
            return $current;
        }
        /** @var TakeOneFileStore $files */
        $files = app(FileStore::class);
        $into = Engine::templateModel()::forType($current->type, $target);

        $into->fill([
            'title' => $current->title, 'body' => $current->body,
            'signature1_name' => $current->signature1_name, 'signature1_title' => $current->signature1_title,
            'signature2_name' => $current->signature2_name, 'signature2_title' => $current->signature2_title,
            'ornament_level' => $current->ornament_level ?: 'full', 'show_photo' => (bool) $current->show_photo,
            'background' => $current->background ?: 'plain', 'accent' => $current->accent,
            'print_offset_x' => (int) $current->print_offset_x, 'print_offset_y' => (int) $current->print_offset_y,
            'layout' => $current->layout,
            'orientation' => $current->orientation, 'layout_portrait' => $current->layout_portrait,
            'elements' => $current->elements, 'artwork_fit' => $current->artwork_fit, 'print_bare' => $current->print_bare,
            'page_size' => $current->page_size,
        ])->save();
        foreach (['signature_1', 'signature_2', CertificateTemplate::BACKGROUND_SLOT, CertificateTemplate::BACKGROUND_SOURCE_SLOT] as $slot) {
            if ($file = $files->get($current, $slot)) {
                $files->put($into, $slot, $file['contents'], $file['mime'], $slot);
            } else {
                $files->delete($into, $slot);
            }
        }

        $eventOwner = $provider->templateOwners($model)[0] ?? null;
        if ($eventOwner && $current->ownedBy($eventOwner)) {
            $files->purgeTemplate($current);
            $current->delete();
        }

        return $into;
    }

    private function ownerFor(CertificateContext $provider, Model $model, string $scope): ?array
    {
        $chain = $provider->templateOwners($model);

        return $scope === 'event' ? ($chain[0] ?? null) : ($chain[1] ?? null);
    }

    private function build(CertificateContext $provider, Model $model, User $user): array
    {
        $service = app(CertificateService::class);
        $files = app(FileStore::class);
        $chain = $provider->templateOwners($model);
        $locale = $this->locale();

        $templates = collect($provider->types())->map(function (string $type) use ($service, $files, $model, $chain, $provider) {
            $t = $service->templateFor($type, $model);
            $owner = [(string) $t->owner_type, (int) $t->owner_id];

            return [
                'type' => $type,
                'label' => Engine::label('types', $type),
                'scope' => isset($chain[1]) && $t->ownedBy($chain[1]) ? 'host' : 'event',
                'title' => (array) $t->title,
                'body' => (array) $t->body,
                'signature1_name' => $t->signature1_name,
                'signature1_title' => $t->signature1_title,
                'signature2_name' => $t->signature2_name,
                'signature2_title' => $t->signature2_title,
                'ornament_level' => $t->ornament_level,
                'background' => in_array($t->background, CertificateTemplate::BACKGROUNDS, true) ? $t->background : 'plain',
                'show_photo' => (bool) $t->show_photo,
                'accent' => $t->accent,
                'print_offset_x' => (int) $t->print_offset_x,
                'print_offset_y' => (int) $t->print_offset_y,
                'layout' => (object) ((array) ($t->layout ?? [])),
                'orientation' => $t->pageOrientation(),
                'layout_portrait' => (object) ((array) ($t->layout_portrait ?? [])),
                'elements' => Layout::customs($t->elements ?? [], Engine::locales()),
                'artwork_fit' => in_array($t->artwork_fit, CertificateTemplate::ARTWORK_FITS, true) ? $t->artwork_fit : null,
                'print_bare' => (bool) $t->print_bare,
                'has_signature' => [1 => $files->exists($t, 'signature_1'), 2 => $files->exists($t, 'signature_2')],
                'has_artwork' => $files->exists($t, CertificateTemplate::BACKGROUND_SLOT),
                'version' => optional($t->updated_at)->timestamp,
                'format' => $t->format()->key,
                // The {tokens} this design can use: the provider's own for this type when it says, else every one.
                'placeholders' => method_exists($provider, 'typePlaceholders') ? $provider->typePlaceholders($type)
                    : ($provider instanceof \App\Events\Support\EventBadges ? $provider->placeholderKeys() : $service->placeholderKeys()),
                // The page's own size (1.4): null = the format's own, `base_page` the format's own in mm.
                'page_size' => ($sz = CertificateTemplate::sizeOf($t->page_size)) ? ['long' => $sz[0], 'short' => $sz[1]] : null,
                'base_page' => ['long' => Formats::forType($type)->long, 'short' => Formats::forType($type)->short],
                'editable' => $provider->canManageOwner($model, $owner, auth()->user()),
            ];
        })->values();

        return [
            'templates' => $templates,
            'locales' => Engine::locales(),
            'placeholders' => $service->placeholderKeys(),
            'scopes' => array_values(array_filter([
                isset($chain[0]) ? ['value' => 'event', 'label' => $provider->ownerLabel($model, $chain[0], $locale), 'allowed' => $provider->canManageOwner($model, $chain[0], $user)] : null,
                isset($chain[1]) ? ['value' => 'host', 'label' => $provider->ownerLabel($model, $chain[1], $locale), 'allowed' => $provider->canManageOwner($model, $chain[1], $user)] : null,
            ])),
            'min_artwork' => CertificateImages::MIN_ARTWORK,
            // The arrangement the editor draws its boxes from: placed (with a medal) and not.
            'layout_defaults' => ['placed' => Layout::defaults(true), 'plain' => Layout::defaults(false)],
            'custom_default' => ['landscape' => Layout::customDefault('landscape'), 'portrait' => Layout::customDefault('portrait')],
            'max_custom' => Layout::MAX_CUSTOM,
            'max_erase' => Layout::MAX_ERASE,
            'erase_default' => ['landscape' => Layout::eraseDefault('landscape'), 'portrait' => Layout::eraseDefault('portrait')],
            'layout_defaults_portrait' => ['placed' => Layout::defaults(true, 'portrait'), 'plain' => Layout::defaults(false, 'portrait')],
            'elements' => collect(Layout::ELEMENTS)->map(fn ($e, $k) => ['key' => $k, 'kind' => $e[0], 'label' => __('cert::messages.el_'.$k)])->values(),
            // Every other format the templates here are drawn in (a badge): its page and its
            // elements, so the editor draws that page instead of a certificate's.
            'formats' => $templates->pluck('format')->unique()->reject(fn ($f) => $f === Formats::CERTIFICATE)
                ->mapWithKeys(fn ($f) => [$f => $this->formatState(Formats::get($f))])->all() ?: (object) [],
            'accent' => $provider->brand($model)['accent'] ?? '#6d5bd0',
        ];
    }

    /**
     * The format a request's layout is measured against: the page size the form sends
     * (1.4) when it sends one — null meaning the format's own — else the template's.
     */
    private function sized(Format $format, array $data, ?CertificateTemplate $template): Format
    {
        $size = array_key_exists('page_size', $data)
            ? CertificateTemplate::sizeOf($data['page_size'] ?? null)
            : CertificateTemplate::sizeOf($template?->page_size);

        return $size ? $format->resized($size[0], $size[1]) : $format;
    }

    /** What the editor needs to draw a page of another format. */
    private function formatState(Format $f): array
    {
        $label = fn (string $k) => trans()->has('cert::messages.el_'.$f->key.'_'.$k) ? __('cert::messages.el_'.$f->key.'_'.$k) : __('cert::messages.el_'.$k);

        return [
            'key' => $f->key,
            'default_orientation' => $f->defaultOrientation,
            'page' => ['landscape' => $f->page('landscape'), 'portrait' => $f->page('portrait')],
            'layout_defaults' => ['placed' => $f->defaults(true, 'landscape'), 'plain' => $f->defaults(false, 'landscape')],
            'layout_defaults_portrait' => ['placed' => $f->defaults(true, 'portrait'), 'plain' => $f->defaults(false, 'portrait')],
            'custom_default' => ['landscape' => $f->customDefault('landscape'), 'portrait' => $f->customDefault('portrait')],
            'erase_default' => ['landscape' => $f->eraseDefault('landscape'), 'portrait' => $f->eraseDefault('portrait')],
            'elements' => collect($f->elements)->map(fn ($e, $k) => ['key' => $k, 'kind' => $e[0], 'label' => $label($k)])->values(),
        ];
    }

    private function endpoints(CertificateContext $provider, Model $model): array
    {
        $p = ['context' => $provider->alias(), 'key' => $provider->key($model)];

        // `xtypex` and slot 1 are placeholders the page swaps for the real type and slot.
        return [
            'state' => route('me.certificates.design.state', $p),
            'update' => route('me.certificates.design.update', $p + ['type' => 'xtypex']),
            'preview' => route('me.certificates.design.preview', $p + ['type' => 'xtypex']),
            'signature' => route('me.certificates.design.signature', $p + ['type' => 'xtypex', 'slot' => 1]),
            'artwork' => route('me.certificates.design.artwork.store', $p + ['type' => 'xtypex']),
        ];
    }
}
