<?php

namespace App\Events\Controllers;

use App\Events\Models\ClubEvent;
use App\Events\Support\EventAccess;
use App\Events\Support\OfficialsPdf;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * GET /me/events/{uuid}/officials/pdf?kind=by_job|all|signin|by_club — the
 * event's officials as an A4 PDF (App\Events\Support\OfficialsPdf).
 *
 * Who: the organiser and the event's appointed officials, as the participants
 * PDF — the same headless browser per request, the same hard throttle. Anybody
 * else gets the 404 a missing event gets.
 */
class OfficialsPdfController extends Controller
{
    public function show(Request $request, ClubEvent $event, OfficialsPdf $pdf): Response
    {
        abort_unless(OfficialsPdf::available(), 404);
        abort_if($event->is_archived, 404);

        $me = Auth::user();
        $access = app(EventAccess::class);
        abort_unless($access->canManage($event, $me) || $access->canOfficiate($event, $me), 404);

        $kind = $request->validate(['kind' => ['required', 'in:'.implode(',', OfficialsPdf::KINDS)]])['kind'];

        $groups = $pdf->groups($event, $me, $kind);
        if ($groups === []) {
            return $this->refuse($request, __('events::events.officials_pdf_nothing'));
        }

        try {
            $bytes = $pdf->render($pdf->html($event, $me, $groups, $kind));
        } catch (\Throwable $e) {
            Log::error('Officials PDF failed', ['event' => $event->uuid, 'kind' => $kind, 'error' => $e->getMessage()]);

            return $this->refuse($request, __('events::events.bracket_pdf_failed'), 503);
        }

        $slug = Str::slug(Str::ascii((string) $event->title)) ?: 'event';

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$slug.'-'.['by_job' => 'officials-by-job', 'all' => 'officials', 'signin' => 'officials-sign-in', 'by_club' => 'officials-by-club'][$kind].'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function refuse(Request $request, string $message, int $status = 422): Response
    {
        return $request->expectsJson() || $request->ajax()
            ? response()->json(['success' => false, 'message' => $message], $status)
            : back()->with('error', $message);
    }
}
