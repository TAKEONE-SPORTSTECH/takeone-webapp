<?php

namespace App\Events\Controllers;

use App\Events\Models\ClubEvent;
use App\Events\Support\EventAccess;
use App\Events\Support\ParticipantsPdf;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /me/events/{uuid}/participants/pdf?kind=list|checkin|all — the entry list
 * as an A4 PDF (App\Events\Support\ParticipantsPdf).
 *
 * ── Who ───────────────────────────────────────────────────────────────────
 * The organiser and the event's appointed officials — the people the
 * check-in sheet's payment and weight belong to (readinessFor's rule). The
 * plain entry list could be wider, but it is a headless browser per request,
 * the most expensive read there is, so it is held to the same people and the
 * same hard throttle as the draw PDF. Anybody else gets the 404 a missing
 * event gets.
 */
class ParticipantsPdfController extends Controller
{
    public function show(Request $request, ClubEvent $event, ParticipantsPdf $pdf): Response
    {
        abort_unless(ParticipantsPdf::available(), 404);
        abort_if($event->is_archived, 404);

        $me = Auth::user();
        $access = app(EventAccess::class);
        abort_unless($access->canManage($event, $me) || $access->canOfficiate($event, $me), 404);

        $kind = $request->validate(['kind' => ['required', 'in:'.implode(',', ParticipantsPdf::KINDS)]])['kind'];

        $divisions = $pdf->divisions($event, $me, $kind);
        if ($divisions === []) {
            return $this->refuse($request, __('events::events.participants_pdf_nothing'));
        }

        try {
            $bytes = $pdf->render($pdf->html($event, $me, $divisions, $kind));
        } catch (\Throwable $e) {
            Log::error('Participants PDF failed', ['event' => $event->uuid, 'kind' => $kind, 'error' => $e->getMessage()]);

            return $this->refuse($request, __('events::events.bracket_pdf_failed'), 503);
        }

        // A name that says what it is, built from the title — never from input.
        $slug = Str::slug(Str::ascii((string) $event->title)) ?: 'event';

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$slug.'-'.['checkin' => 'check-in', 'all' => 'participants', 'list' => 'entry-list'][$kind].'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** The page fetches this, so it is told in JSON and stays where it is. */
    private function refuse(Request $request, string $message, int $status = 422): Response
    {
        return $request->expectsJson() || $request->ajax()
            ? response()->json(['success' => false, 'message' => $message], $status)
            : back()->with('error', $message);
    }
}
