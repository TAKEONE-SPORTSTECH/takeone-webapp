<?php

namespace App\Events\Controllers;

use App\Events\Models\ClubEvent;
use App\Events\Support\BracketPdf;
use App\Events\Support\EventAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /me/events/{uuid}/brackets/pdf — the draw, as an A4 PDF.
 *
 * ── Who ───────────────────────────────────────────────────────────────────
 * The people who run the draw: the organiser and the event's appointed
 * officials — the same two who reach the draw stage's working tools. It is a
 * READ, so it could be wider; it is held to them because it is the most
 * expensive read on the platform (a headless browser per request), and that is
 * also why the route is throttled hard (Part 3 §14). Anybody else gets the
 * same 404 a missing event gets.
 *
 * ── What ──────────────────────────────────────────────────────────────────
 * `?division=` prints one division; without it, every drawn division, one per
 * page. `layout` and `third` are the viewer's own board settings (linear/split,
 * play-off/shared bronze), which live in their browser — passed along so the
 * paper matches the screen they just looked at. Both are whitelisted.
 */
class BracketPdfController extends Controller
{
    public function show(Request $request, ClubEvent $event, BracketPdf $pdf): Response
    {
        abort_unless(BracketPdf::available(), 404);
        abort_if($event->is_archived, 404);

        $me = Auth::user();
        // Organiser, jury, platform staff — EventAccess's own "who runs the draw".
        abort_unless(app(EventAccess::class)->canArrange($event, $me), 404);

        $data = $request->validate([
            'division' => ['nullable', 'integer'],
            'layout' => ['nullable', 'in:linear,split'],
            'third' => ['nullable', 'in:match,shared'],
        ]);

        $divisions = $pdf->divisions($event, $me, isset($data['division']) ? (int) $data['division'] : null);

        if ($divisions === []) {
            return $this->refuse($request, __('events::events.bracket_pdf_nothing'));
        }

        try {
            // The whole draw opens with the competition and its divisions (pages 1
            // and 2); a single division prints as just its own sheet.
            $bytes = $pdf->render($pdf->html($event, $me, $divisions, $data['layout'] ?? 'linear', $data['third'] ?? 'match', ! isset($data['division'])));
        } catch (\Throwable $e) {
            Log::error('Bracket PDF failed', ['event' => $event->uuid, 'error' => $e->getMessage()]);

            return $this->refuse($request, __('events::events.bracket_pdf_failed'), 503);
        }

        // A name that says what it is, built from the title — never from input.
        $slug = Str::slug(Str::ascii((string) $event->title)) ?: 'draw';
        $name = count($divisions) === 1
            ? $slug.'-'.(Str::slug(Str::ascii((string) $divisions[0]['name'])) ?: 'division').'.pdf'
            : $slug.'-draw.pdf';

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** The board fetches this, so it is told in JSON and stays where it is. */
    private function refuse(Request $request, string $message, int $status = 422): Response
    {
        return $request->expectsJson() || $request->ajax()
            ? response()->json(['success' => false, 'message' => $message], $status)
            : back()->with('error', $message);
    }
}
