<?php

namespace App\Http\Middleware;

use App\Translation\Translations;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * This request edits the organiser's words, so it reads the organiser's words.
 *
 * ⚠️ THE FAILURE THIS PREVENTS IS UNRECOVERABLE.
 *
 * Since App\Traits\TranslatesAttributes landed, reading `$event->title` returns
 * the READER's language. That is right on a poster and catastrophic in an edit
 * form: an organiser who switched their event to Chinese to check the
 * translation, then tapped Edit, would find the machine's Chinese sitting in
 * the title box — and pressing Save would write that Chinese into the column
 * their Arabic used to occupy. The source is then gone. Not stale, not
 * overwritten by a person: replaced by a machine translation of itself, with
 * every future translation made from that. There is no undo, because the system
 * has no memory of a source it was never told it was losing.
 *
 * So an editing route says so, once, at the door — rather than every blade,
 * every payload builder and every `old()` call remembering to. The scope covers
 * the whole request: validation, the controller, the payload, the view render,
 * the activity log Spatie writes from the model's attributes, and the JSON a
 * write hands back.
 *
 * ── Where it goes, and where it must NOT ─────────────────────────────────────
 *
 *   ✅ The event create/edit form and its PUT.
 *   ✅ The divisions screen — "Organiser only. Everything on this page edits."
 *   ✅ Anything that pre-fills a control with stored text.
 *
 *   ❌ The event console, the poster, the bracket, the entry list, the
 *      participants page. Those DISPLAY, and an organiser who picked Chinese
 *      to read their own competition should see Chinese.
 *
 * The checklist is the instructive middle case and is deliberately absent: its
 * items are DISPLAYED on the console (translated, which is the whole point) and
 * created from an empty box the organiser types into. Nothing there ever writes
 * a stored label back, so nothing there needs pinning.
 */
class ReadsSourceContent
{
    public function handle(Request $request, Closure $next): Response
    {
        /*
         * The view is rendered INSIDE this scope, not after it.
         *
         * A controller returning a View has it converted to a Response by the
         * router, inside `$next()` — so the blade's every `{{ $e['title'] }}`
         * runs while the scope is open. Returning the View and letting the
         * framework render it later would close the scope first and put the
         * translation straight back into the form, which is the entire bug.
         */
        return Translations::source(fn () => $next($request));
    }
}
