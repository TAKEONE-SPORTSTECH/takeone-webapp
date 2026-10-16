<?php

namespace App\Events\Controllers;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Support\EventAccess;
use App\Events\Support\RegistrationFormPdf;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /me/events/{uuid}/registration-form/pdf — the event's blank, bilingual
 * registration form as an A4 PDF (App\Events\Support\RegistrationFormPdf).
 *
 * ── Who ───────────────────────────────────────────────────────────────────
 * The organiser and the event's appointed officials — the people who hand
 * the form out and collect it. The form itself holds no personal data, but
 * it is a headless browser per request, so it sits behind the same people
 * and the same hard throttle as the draw and entry-list PDFs. Anybody else
 * gets the 404 a missing event gets.
 *
 * GET /me/events/{uuid}/entrants/{registration}/registration-form/pdf — the
 * same form already filled in for one entrant, so it only needs signing.
 * That one carries a person's details, so it is the ORGANISER's alone (the
 * entry editor's own door, EntryPanelController::entrant, has the same
 * rule), and what goes on it is the editor's own read
 * (RegistrationFormPdf::fill).
 */
class RegistrationFormPdfController extends Controller
{
    public function show(Request $request, ClubEvent $event, RegistrationFormPdf $pdf): Response
    {
        abort_unless(RegistrationFormPdf::available(), 404);
        abort_if($event->is_archived, 404);

        $me = Auth::user();
        $access = app(EventAccess::class);
        abort_unless($access->canManage($event, $me) || $access->canOfficiate($event, $me), 404);

        // ?fill=all — every participant's form filled in (personal details: the organiser's alone).
        $all = $request->query('fill') === 'all';
        if ($all) {
            abort_unless($access->canManage($event, $me), 404);
        }

        try {
            $html = $all ? $pdf->htmlForAll($event, $me) : $pdf->html($event, $me);
            if ($html === null) {
                return $request->expectsJson() || $request->ajax()
                    ? response()->json(['success' => false, 'message' => __('events::events.participants_pdf_nothing')], 422)
                    : back()->with('error', __('events::events.participants_pdf_nothing'));
            }
            $bytes = $pdf->render($html);
        } catch (\Throwable $e) {
            Log::error('Registration form PDF failed', ['event' => $event->uuid, 'error' => $e->getMessage()]);

            return $request->expectsJson() || $request->ajax()
                ? response()->json(['success' => false, 'message' => __('events::events.bracket_pdf_failed')], 503)
                : back()->with('error', __('events::events.bracket_pdf_failed'));
        }

        // A name that says what it is, built from the title — never from input.
        $slug = Str::slug(Str::ascii((string) $event->title)) ?: 'event';

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$slug.($all ? '-registration-forms-filled' : '-registration-form').'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function entrant(Request $request, ClubEvent $event, ClubEventRegistration $registration, RegistrationFormPdf $pdf): Response
    {
        abort_unless(RegistrationFormPdf::available(), 404);
        abort_if($event->is_archived, 404);

        $me = Auth::user();
        abort_unless(app(EventAccess::class)->canManage($event, $me), 404);
        // An entry of ANOTHER event answers exactly as a missing one.
        abort_unless((int) $registration->event_id === (int) $event->id, 404);

        try {
            $bytes = $pdf->render($pdf->html($event, $me, $pdf->fill($event, $registration, $me)));
        } catch (\Throwable $e) {
            Log::error('Registration form PDF failed', ['event' => $event->uuid, 'registration' => $registration->id, 'error' => $e->getMessage()]);

            return $request->expectsJson() || $request->ajax()
                ? response()->json(['success' => false, 'message' => __('events::events.bracket_pdf_failed')], 503)
                : back()->with('error', __('events::events.bracket_pdf_failed'));
        }

        // Named from the person's name, ASCII-folded — never from input.
        $who = Str::slug(Str::ascii((string) ($registration->user?->full_name ?? ''))) ?: 'entry-'.$registration->id;

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$who.'-registration-form.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
