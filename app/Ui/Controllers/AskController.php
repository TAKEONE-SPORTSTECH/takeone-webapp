<?php

namespace App\Ui\Controllers;

use App\Http\Controllers\Controller;
use App\Ui\Ask\AskEngine;
use App\Ui\NewUi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** /me/ask — the "Ask TAKEONE" chat (new UI only). */
class AskController extends Controller
{
    public function show(Request $request): View
    {
        abort_unless(NewUi::active(), 404);

        return view($request->attributes->get('is_mobile') ? 'ui::ask.mobile' : 'ui::ask.desktop');
    }

    public function message(Request $request, AskEngine $engine): JsonResponse
    {
        abort_unless(NewUi::active(), 404);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:600'],
            'history' => ['sometimes', 'array', 'max:8'],
            'history.*.role' => ['required_with:history', 'in:user,assistant'],
            'history.*.content' => ['required_with:history', 'string', 'max:1200'],
        ]);

        return response()->json(['success' => true] + $engine->answer($request->user(), $data['message'], $data['history'] ?? []));
    }
}
