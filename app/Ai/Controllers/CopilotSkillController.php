<?php

namespace App\Ai\Controllers;

use App\Ai\Copilot\SkillCopilot;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Coach with every skill (SkillCopilot). Same stack, throttle and
 * draft-then-confirm as the club-only endpoints it will replace; behind
 * config('copilot.skills').
 */
class CopilotSkillController extends Controller
{
    public function message(Request $request, SkillCopilot $copilot): JsonResponse
    {
        abort_unless(config('copilot.skills'), 404);
        $data = $request->validate([
            'page' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9._\-]*$/'],
            'messages' => ['array', 'max:40'],
            'messages.*.role' => ['required', 'string', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string'],
            'state' => ['nullable', 'string', 'max:40000'],
        ]);
        if (! config('copilot.enabled', true)) {
            return response()->json(['reply' => __('copilot.turned_off')]);
        }

        try {
            return response()->json($copilot->reply($request->user(), $data['page'] ?? null, $data['messages'] ?? [], $data['state'] ?? null));
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['reply' => __('copilot.ai_unreachable')]);
        }
    }

    public function apply(Request $request, SkillCopilot $copilot): JsonResponse
    {
        abort_unless(config('copilot.skills'), 404);
        $request->validate(['token' => ['required', 'string', 'max:40000']]);

        try {
            $out = $copilot->apply($request->user(), (string) $request->input('token'));
        } catch (\Throwable $e) {
            report($e);
            $out = ['ok' => false, 'message' => __('copilot.error_create_any')];
        }

        return response()->json(['success' => (bool) ($out['ok'] ?? false)] + $out, ($out['ok'] ?? false) ? 200 : 422);
    }
}
