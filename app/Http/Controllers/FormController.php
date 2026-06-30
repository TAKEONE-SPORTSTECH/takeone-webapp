<?php

namespace App\Http\Controllers;

use App\Models\Form;
use App\Support\FormEngine;
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

        return view('forms.show', compact('form', 'alreadyDone'));
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

        [$rules, $labels] = FormEngine::rules($form, (array) $request->input('fields', []));
        $validator = validator($request->all(), $rules, [], $labels);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first(), 'errors' => $validator->errors()], 422);
        }

        $data  = (array) $request->input('fields', []);
        $files = [];

        // Persist uploaded files; record the stored path back into the data.
        foreach ($form->fields() as $field) {
            if (($field['type'] ?? '') === 'file' && $request->hasFile('fields.' . ($field['key'] ?? ''))) {
                $path = $request->file('fields.' . $field['key'])->store("form-uploads/{$form->id}", 'public');
                $data[$field['key']] = $path;
                $files[] = ['path' => $path, 'disk' => 'public'];
            }
        }

        $form->submissions()->create([
            'user_id' => Auth::id(),
            'data'    => $data,
            'files'   => $files,
            'ip'      => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => $form->settings['successMessage'] ?? 'Thank you — your response was submitted.',
        ]);
    }
}
