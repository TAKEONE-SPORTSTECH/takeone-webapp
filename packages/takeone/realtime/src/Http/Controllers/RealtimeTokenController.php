<?php

namespace Takeone\Realtime\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

class RealtimeTokenController extends Controller
{
    /**
     * Mint a fresh broker token for the signed-in user. The browser calls this
     * on page load (and again shortly before expiry) to (re)connect over WS.
     */
    public function __invoke(): JsonResponse
    {
        if (! Realtime()->enabled()) {
            return response()->json(['enabled' => false], 200);
        }

        $user = auth()->user();

        return response()->json([
            'enabled' => true,
        ] + Realtime()->tokenFor($user->id));
    }
}
