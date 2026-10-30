<?php

namespace App\Forms\Controllers;

use App\Forms\Models\Form;
use App\Forms\Support\FormSchema;
use App\Forms\Support\SurveyAnalytics;
use App\Forms\Support\SurveyResponses;
use App\Http\Controllers\Controller;
use App\Members\Models\User;
use App\Support\Qr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The super-admin's view of every form: the list, and one form's links,
 * answers, charts and proposed decisions. Platform-wide by nature — a form's
 * answers can name clubs and people — so only a super-admin reaches any of it
 * (route middleware AND guard(), never one without the other).
 */
class FormAdminController extends Controller
{
    use \App\Traits\StoresBase64Images;

    public function index(Request $request)
    {
        $this->guard($request);

        $forms = Form::query()->with('tenant:id,club_name')->withCount('submissions')
            ->withMax('submissions', 'created_at')->latest('id')->get()
            ->map(fn (Form $f) => [
                'uuid' => $f->uuid,
                'title' => $f->title,
                'title_ar' => $f->settings['title_ar'] ?? '',
                'type' => $f->type,
                'active' => (bool) $f->is_active,
                'owner' => $f->tenant?->club_name,
                'questions' => count($f->fields()),
                'pages' => count($f->schema['pages'] ?? []),
                'responses' => (int) $f->submissions_count,
                'last' => $f->submissions_max_created_at,
                'created' => $f->created_at,
                'url' => route('admin.forms.show', $f->uuid),
                'edit_url' => route('admin.forms.edit', $f->uuid),
                'cover_url' => $f->coverUrl(),
                'public_url' => route('forms.show', $f->uuid),
                'pdf_url' => route('admin.forms.pdf', $f->uuid),
                'poster_url' => route('admin.forms.poster', $f->uuid),
            ]);

        $mobile = (bool) $request->attributes->get('is_mobile', false);

        return view($mobile ? 'forms::admin.mobile.index' : 'forms::admin.desktop.index', [
            'forms' => $forms,
            'totals' => [
                'forms' => $forms->count(),
                'active' => $forms->where('active', true)->count(),
                'responses' => $forms->sum('responses'),
            ],
        ]);
    }

    public function show(Request $request, Form $form)
    {
        $this->guard($request);

        $real = SurveyResponses::rows($form);
        $example = $request->boolean('example') && SurveyResponses::hasExample($form);
        $rows = $example ? SurveyResponses::example($form) : $real;
        $payload = [
            'form' => $form,
            'example' => $example,
            'canExample' => SurveyResponses::hasExample($form),
            'realCount' => count($real),
            'stats' => SurveyAnalytics::build($form, $rows),
            'responses' => SurveyResponses::list($form, $rows),
            'publicUrl' => route('forms.show', $form->uuid),
            'visitUrl' => ! empty($form->settings['visitToken']) ? route('forms.show', $form->uuid).'?v='.$form->settings['visitToken'] : null,
        ];

        // In-place refresh after a new answer arrives (realtime `forms` channel).
        if ($request->ajax() && $request->query('fragment') === 'panels') {
            return view('forms::admin.partials.panels', $payload);
        }

        $payload['qr'] = Qr::svg($payload['publicUrl'], 320, 1);
        $mobile = (bool) $request->attributes->get('is_mobile', false);

        return view($mobile ? 'forms::admin.mobile.show' : 'forms::admin.desktop.show', $payload);
    }

    public function toggle(Request $request, Form $form): JsonResponse
    {
        $user = $this->guard($request);
        $form->update(['is_active' => ! $form->is_active]);
        self::nudge($form, $user->id);

        return response()->json([
            'success' => true,
            'message' => $form->is_active ? 'The form is open for answers.' : 'The form is closed. Its link now shows nothing.',
            'form' => ['uuid' => $form->uuid, 'active' => (bool) $form->is_active],
        ]);
    }

    /** Every answer as a spreadsheet: one row per response, one column per question. */
    public function export(Request $request, Form $form): StreamedResponse
    {
        $this->guard($request);
        [$head, $lines] = SurveyResponses::table($form, SurveyResponses::rows($form));

        return response()->streamDownload(function () use ($head, $lines) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // Excel reads the Arabic correctly with a BOM
            fputcsv($out, $head);
            foreach ($lines as $l) {
                // A cell starting = + - @ is a formula to Excel — neutralise it.
                fputcsv($out, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/', $v) ? "'".$v : $v, $l));
            }
            fclose($out);
        }, 'form-'.substr($form->uuid, 0, 8).'-answers.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** The builder, empty. */
    public function create(Request $request)
    {
        $this->guard($request);

        return $this->builder($request, null);
    }

    /** The builder, holding a form. */
    public function edit(Request $request, Form $form)
    {
        $this->guard($request);

        return $this->builder($request, $form);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->guard($request);
        $clean = FormSchema::normalise((array) $request->input('form', []));
        $form = Form::create($clean + ['tenant_id' => null, 'is_active' => (bool) $request->input('form.active', true), 'created_by' => $user->id]);

        return $this->saved($form, 'Form created.');
    }

    public function update(Request $request, Form $form): JsonResponse
    {
        $this->guard($request);
        $clean = FormSchema::normalise((array) $request->input('form', []), $form);
        $form->update($clean + ['is_active' => (bool) $request->input('form.active', $form->is_active)]);

        return $this->saved($form->fresh(), 'Saved.');
    }

    /** A copy with no answers, closed until it is opened, with its own links. */
    public function duplicate(Request $request, Form $form): JsonResponse
    {
        $user = $this->guard($request);
        $settings = $form->settings ?? [];
        unset($settings['template']); // a copy is its own form, not the imported one
        if (! empty($settings['visitToken'])) {
            $settings['visitToken'] = \Illuminate\Support\Str::random(40);
        }
        $cover = $settings['cover'] ?? null;
        unset($settings['cover']);
        $copy = Form::create([
            'tenant_id' => $form->tenant_id,
            'title' => \Illuminate\Support\Str::limit($form->title.' (copy)', 150, ''),
            'type' => $form->type,
            'description' => $form->description,
            'schema' => $form->schema,
            'settings' => $settings,
            'is_active' => false,
            'created_by' => $user->id,
        ]);

        if ($cover && \Illuminate\Support\Facades\Storage::disk('local')->exists($cover)) {
            $to = \App\Support\StoragePath::platform('forms/'.$copy->uuid.'/cover').'/'.\Illuminate\Support\Str::uuid().'.'.pathinfo($cover, PATHINFO_EXTENSION);
            if (\Illuminate\Support\Facades\Storage::disk('local')->copy($cover, $to)) {
                $copy->update(['settings' => array_merge($copy->settings, ['cover' => $to])]);
            }
        }

        return $this->saved($copy, 'A copy was made. It is closed until you open it.');
    }

    /** Answers first (each one, so any file it holds goes with it), then the form. */
    public function destroy(Request $request, Form $form): JsonResponse
    {
        $this->guard($request);
        $cover = $form->settings['cover'] ?? null;
        \Illuminate\Support\Facades\DB::transaction(function () use ($form) {
            $form->submissions()->cursor()->each(fn ($s) => $s->delete());
            $form->delete();
        });
        if ($cover) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($cover);
        }
        \Illuminate\Support\Facades\Storage::disk('local')->deleteDirectory(\App\Support\StoragePath::platform('forms/'.$form->uuid.'/pdf'));

        return response()->json(['success' => true, 'message' => 'The form and its answers were deleted.']);
    }

    private function saved(Form $form, string $message): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'form' => [
                'uuid' => $form->uuid,
                'active' => (bool) $form->is_active,
                'edit_url' => route('admin.forms.edit', $form->uuid),
                'show_url' => route('admin.forms.show', $form->uuid),
                'public_url' => route('forms.show', $form->uuid),
                'state' => self::state($form),
            ],
        ]);
    }

    private function builder(Request $request, ?Form $form)
    {
        $payload = [
            'form' => $form,
            'state' => $form ? self::state($form) : self::blank(),
            'answers' => $form ? $form->submissions()->count() : 0,
            'urls' => [
                'save' => $form ? route('admin.forms.update', $form->uuid) : route('admin.forms.store'),
                'method' => $form ? 'PUT' : 'POST',
                'show' => $form ? route('admin.forms.show', $form->uuid) : null,
                'cover' => $form ? route('admin.forms.cover', $form->uuid) : null,
                'public' => $form ? route('forms.show', $form->uuid) : null,
                'index' => route('admin.forms.index'),
            ],
        ];
        $mobile = (bool) $request->attributes->get('is_mobile', false);

        return view($mobile ? 'forms::admin.mobile.builder' : 'forms::admin.desktop.builder', $payload);
    }

    /** The builder's working copy of a form. */
    private static function state(Form $form): array
    {
        $s = $form->settings ?? [];

        return [
            'title' => $form->title, 'title_ar' => $s['title_ar'] ?? '',
            'description' => $form->description ?? '', 'description_ar' => $s['description_ar'] ?? '',
            'type' => $form->type, 'active' => (bool) $form->is_active,
            'interviewer' => ! empty($s['visitToken']),
            'cover_url' => $form->coverUrl(),
            'successMessage' => $s['successMessage'] ?? '', 'successMessage_ar' => $s['successMessage_ar'] ?? '',
            'pages' => array_map(fn ($p) => [
                'id' => $p['id'] ?? null, 'title' => $p['title'] ?? '', 'title_ar' => $p['title_ar'] ?? '',
                'intro' => $p['intro'] ?? '', 'intro_ar' => $p['intro_ar'] ?? '', 'private' => ! empty($p['private']),
                'fields' => array_values(array_filter($p['fields'] ?? [], fn ($f) => in_array($f['type'] ?? '', FormSchema::BUILDER_TYPES, true))),
            ], $form->schema['pages'] ?? []),
        ];
    }

    private static function blank(): array
    {
        return [
            'title' => '', 'title_ar' => '', 'description' => '', 'description_ar' => '',
            'type' => 'survey', 'active' => true, 'interviewer' => false, 'successMessage' => '', 'successMessage_ar' => '',
            'pages' => [['id' => null, 'title' => 'Page 1', 'title_ar' => 'الصفحة ١', 'intro' => '', 'intro_ar' => '', 'private' => false, 'fields' => []]],
        ];
    }

    /**
     * The form's cover artwork. Real bytes are checked (jpg/png/webp/gif only,
     * never SVG); the folder and name are the server's; the old file goes only
     * after the new one is safely written. Private disk — served by forms.cover.
     */
    public function cover(Request $request, Form $form): JsonResponse
    {
        $this->guard($request);
        $request->validate(['image' => ['required', 'string', 'starts_with:data:image/', 'max:16000000']]);

        $folder = \App\Support\StoragePath::platform('forms/'.$form->uuid.'/cover');
        $path = $this->storeBase64Image($request->input('image'), $folder, (string) \Illuminate\Support\Str::uuid(), 'local');
        if ($path === null) {
            return response()->json(['success' => false, 'message' => 'Invalid or unsupported image.'], 422);
        }
        $old = $form->settings['cover'] ?? null;
        $form->update(['settings' => array_merge($form->settings ?? [], ['cover' => $path])]);
        if ($old && $old !== $path) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($old);
        }

        return response()->json(['success' => true, 'message' => 'Artwork saved.', 'url' => $form->coverUrl(), 'cover_url' => $form->coverUrl()]);
    }

    public function removeCover(Request $request, Form $form): JsonResponse
    {
        $this->guard($request);
        $old = $form->settings['cover'] ?? null;
        $settings = $form->settings ?? [];
        unset($settings['cover']);
        $form->update(['settings' => $settings]);
        if ($old) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($old);
        }

        return response()->json(['success' => true, 'message' => 'Artwork removed.', 'cover_url' => null]);
    }

    /** The questionnaire booklet — the same design as the hand-made PDFs, drawn from this form. */
    public function pdf(Request $request, Form $form, \App\Forms\Support\FormPdf $pdf)
    {
        $this->guard($request);
        try {
            $path = $pdf->path($form);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'The PDF could not be made right now. Try again in a minute.');
        }

        return \Illuminate\Support\Facades\Storage::disk('local')->download($path, $pdf->filename($form), ['Content-Type' => 'application/pdf']);
    }

    /** The QR poster, as a picture to send or print. Only ever the PUBLIC link. */
    public function poster(Request $request, Form $form, \App\Forms\Support\FormPdf $pdf)
    {
        $this->guard($request);
        try {
            $png = $pdf->poster($form);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'The poster could not be made right now. Try again in a minute.');
        }
        $name = \Illuminate\Support\Str::beforeLast($pdf->filename($form), '.pdf').'-qr-poster.png';

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Every super-admin's open results page re-reads (a refresh signal — no answers travel). */
    public static function nudge(Form $form, ?int $except = null): void
    {
        rescue(function () use ($form, $except) {
            if (! function_exists('Realtime') || ! Realtime()->enabled()) {
                return;
            }
            User::whereHas('roles', fn ($q) => $q->where('slug', 'super-admin'))
                ->when($except, fn ($q) => $q->where('id', '!=', $except))->pluck('id')
                ->each(fn ($id) => Realtime()->publishToUser((int) $id, 'forms', ['action' => 'refresh', 'form' => $form->uuid]));
        }, null, report: false);
    }

    private function guard(Request $request): User
    {
        $u = $request->user();
        abort_unless($u && $u->isSuperAdmin(), 403);

        return $u;
    }
}
