<?php

namespace App\DevBoard\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * TakeNote on a desk: /me/notes is the notebook as a website of its own —
 * full screen, no site navbar around it — the phone app's own script laid
 * out for a wide screen (takenote/desk). Behind the member stack and
 * EnsureBoardAccess, like every board route; it carries no notes, they come
 * from me.takenote.data.
 */
class TakeNoteDeskController extends Controller
{
    public function show(): Response
    {
        return response()->view('devboard::takenote.desk')
            ->header('X-Frame-Options', 'SAMEORIGIN')
            ->header('Cache-Control', 'no-store, private');
    }
}
