<?php

namespace App\Events\Controllers;

use App\Events\Models\ClubEvent;
use App\Events\Support\DivisionMerge;
use App\Events\Support\EventAccess;
use App\Events\Support\MergeHandbookPdf;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Merging small divisions (App\Events\Support\DivisionMerge): the suggestions,
 * a judgement of any groups the organiser picks, and the merge itself.
 *
 * Organiser only — restructuring the event, like creating or deleting a group —
 * and behind config('events.division_merge.enabled'). Every rule lives in the
 * support class; this only carries the request. The grade a client shows is
 * never trusted: merge() judges again on the server.
 */
class DivisionMergeController extends Controller
{
    private function allow(Request $request, ClubEvent $event, EventAccess $access): void
    {
        abort_unless(DivisionMerge::enabled() && $access->canManage($event, $request->user()), 404);
    }

    public function suggest(Request $request, ClubEvent $event, DivisionMerge $merge, EventAccess $access): JsonResponse
    {
        $this->allow($request, $event, $access);

        return response()->json(['success' => true] + $merge->suggest($event), 200, ['Cache-Control' => 'no-store, private']);
    }

    public function check(Request $request, ClubEvent $event, DivisionMerge $merge, EventAccess $access): JsonResponse
    {
        $this->allow($request, $event, $access);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:2', 'max:12'],
            'ids.*' => ['integer'],
            'into' => ['nullable', 'integer'],
        ]);

        $divs = $merge->divisions($event)->keyBy('id');
        $set = collect($data['ids'])->map(fn ($id) => $divs[(int) $id] ?? null);

        if ($set->contains(null) || $set->unique('id')->count() < 2) {
            return response()->json(['success' => false, 'message' => __('events::events.merge_gone')], 422);
        }

        return response()->json(['success' => true, 'judgement' => $merge->judge($event, $set->unique('id')->values()->all(), $data['into'] ?? null)]);
    }

    public function merge(Request $request, ClubEvent $event, DivisionMerge $merge, EventAccess $access): JsonResponse
    {
        $this->allow($request, $event, $access);

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:2', 'max:12'],
            'ids.*' => ['integer'],
            'into' => ['nullable', 'integer'],
            // The organiser's statement that written guardian consent is in
            // hand. Required only when the server grades the merge amber.
            'consent' => ['sometimes', 'boolean'],
        ]);

        $result = $merge->merge($event, $data['ids'], $data['into'] ?? null, $request->user(), (bool) ($data['consent'] ?? false));

        return response()->json(['success' => $result['ok']] + collect($result)->except(['ok', 'status'])->all(), $result['status']);
    }

    /**
     * The officials' handbook as an A4 PDF, with this event's own chapter
     * (MergeHandbookPdf). For whoever runs the event — the organiser and the
     * appointed officials, the same people as the registration form — behind
     * the PDF limiter: it is a headless browser per request.
     */
    public function handbook(Request $request, ClubEvent $event, MergeHandbookPdf $pdf, EventAccess $access): Response
    {
        abort_unless(MergeHandbookPdf::available(), 404);
        abort_if($event->is_archived, 404);
        $me = $request->user();
        abort_unless($access->canManage($event, $me) || $access->canOfficiate($event, $me), 404);

        try {
            $bytes = $pdf->render($pdf->html($event, $me));
        } catch (\Throwable $e) {
            Log::error('Merge handbook PDF failed', ['event' => $event->uuid, 'error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => __('events::events.bracket_pdf_failed')], 503);
        }

        $slug = Str::slug(Str::ascii((string) $event->title)) ?: 'event';

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$slug.'-merging-handbook.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
