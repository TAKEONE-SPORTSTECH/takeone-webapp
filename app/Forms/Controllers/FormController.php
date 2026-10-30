<?php

namespace App\Forms\Controllers;

use App\Http\Controllers\Controller;
use App\Forms\Models\Form;
use App\Forms\FormEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FormController extends Controller
{
    /** Public form fill page (surveys via link/QR, etc.). */
    public function show(Request $request, Form $form): View
    {
        abort_unless($form->is_active, 404);

        if (! empty($form->settings['loginRequired']) && ! Auth::check()) {
            session(['url.intended' => $request->fullUrl()]);
            abort(redirect()->route('login'));
        }

        $alreadyDone = ! empty($form->settings['oncePerUser']) && Auth::check()
            && $form->submissions()->where('user_id', Auth::id())->exists();

        if ($form->isPaged()) {
            $visit = $form->isVisit($request->query('v'));
            $view = $request->attributes->get('is_mobile', false) ? 'forms::survey.mobile' : 'forms::survey.desktop';
            $s = $form->settings ?? [];

            return view($view, [
                'form' => $form,
                'alreadyDone' => $alreadyDone,
                'visit' => $visit,
                // Everything the page's script reads, and nothing more.
                'surveyCfg' => [
                    'uuid' => $form->uuid,
                    'cover' => $form->coverUrl(),
                    'copy' => [
                        'title' => $form->title, 'title_ar' => $s['title_ar'] ?? '',
                        'description' => $form->description, 'description_ar' => $s['description_ar'] ?? '',
                    ],
                    'pages' => \App\Forms\Support\SurveyPages::for($form, $visit),
                    'action' => route('forms.submit', $form->uuid),
                    'csrf' => csrf_token(),
                    'visit' => $visit,
                    'visitToken' => $visit ? (string) $request->query('v') : null,
                ],
            ]);
        }

        return view('forms::show', compact('form', 'alreadyDone'));
    }

    /**
     * The form's cover artwork. Whoever may open the form may see its picture;
     * a closed form shows it to nobody but a super-admin. One answer — 404 —
     * for a missing picture, a closed form and an unknown form alike.
     */
    public function cover(Request $request, Form $form)
    {
        $path = $form->settings['cover'] ?? null;
        $may = $form->is_active || ($request->user()?->isSuperAdmin() ?? false);
        if (! $path || ! $may || ! str_starts_with($path, 'platform/forms/'.$form->uuid.'/cover/')
            || ! \Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return \Illuminate\Support\Facades\Storage::disk('local')->response($path, null, [
            'Cache-Control' => 'public, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** Validate against the schema, store files + the submission. */
    public function submit(Request $request, Form $form): JsonResponse
    {
        abort_unless($form->is_active, 404);

        if (! empty($form->settings['loginRequired']) && ! Auth::check()) {
            return response()->json(['success' => false, 'message' => 'Please sign in to submit.'], 401);
        }
        if (! empty($form->settings['oncePerUser']) && Auth::check()
            && $form->submissions()->where('user_id', Auth::id())->exists()) {
            return response()->json(['success' => false, 'message' => 'You have already submitted this form.'], 422);
        }

        if ($form->isPaged()) {
            return $this->submitPaged($request, $form);
        }

        [$rules, $labels] = FormEngine::rules($form, (array) $request->input('fields', []));
        $validator = validator($request->all(), $rules, [], $labels);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $data = (array) $request->input('fields', []);
        $files = [];

        // Persist uploaded files; record the stored path back into the data.
        foreach ($form->fields() as $field) {
            if (($field['type'] ?? '') === 'file' && $request->hasFile('fields.'.($field['key'] ?? ''))) {
                $path = $request->file('fields.'.$field['key'])->store("form-uploads/{$form->id}", 'public');
                $data[$field['key']] = $path;
                $files[] = ['path' => $path, 'disk' => 'public'];
            }
        }

        $form->submissions()->create([
            'user_id' => Auth::id(),
            'data' => $data,
            'files' => $files,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => $form->settings['successMessage'] ?? 'Thank you — your response was submitted.',
        ]);
    }

    /**
     * The paged survey: keeps ONLY the declared keys (never the raw request),
     * accepts private pages only with the visit link, and records which
     * language the answers were given in.
     */
    private function submitPaged(Request $request, Form $form): JsonResponse
    {
        $visit = $form->isVisit($request->input('visit'));
        $fields = (array) $request->input('fields', []);

        [$rules, $labels, $keys] = FormEngine::surveyRules($form, $fields, $visit);
        $validator = validator($request->all(), $rules + ['lang' => ['nullable', 'in:en,ar']], [], $labels);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $clean = $validator->validated()['fields'] ?? [];
        $data = [];
        foreach ($keys as $key) {
            $v = $clean[$key] ?? null;
            if ($v !== null && $v !== '' && $v !== []) {
                $data[$key] = $v;
            }
        }
        $data['_mode'] = $visit ? 'visit' : 'self';
        $data['_lang'] = $request->input('lang') === 'ar' ? 'ar' : 'en';

        $form->submissions()->create([
            'user_id' => Auth::id(),
            'data' => $data,
            'files' => [],
            'ip' => $request->ip(),
        ]);

        // Open results pages re-read; no answer travels over the wire.
        FormAdminController::nudge($form);

        $ar = $data['_lang'] === 'ar';

        return response()->json([
            'success' => true,
            'message' => $ar
                ? ($form->settings['successMessage_ar'] ?? 'شكراً لكم.')
                : ($form->settings['successMessage'] ?? 'Thank you.'),
        ]);
    }
}
