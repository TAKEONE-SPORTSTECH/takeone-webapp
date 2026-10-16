<?php

namespace App\Events\Controllers;

use App\Events\Models\ClubEvent;
use App\Events\Support\AudienceResolver;
use App\Events\Support\DivisionReset;
use App\Events\Support\EventAccess;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Delete every division, and auto-place entrants into the rule book's
 * divisions (App\Events\Support\DivisionReset). Organiser only — restructuring
 * the event — and switched on with the division tools (EVENT_DIVISION_MERGE).
 */
class DivisionResetController extends Controller
{
    private function allow(Request $request, ClubEvent $event, EventAccess $access): void
    {
        abort_unless(DivisionReset::enabled() && $access->canManage($event, $request->user()), 404);
    }

    public function deleteAll(Request $request, ClubEvent $event, DivisionReset $reset, EventAccess $access): JsonResponse
    {
        $this->allow($request, $event, $access);

        // Typed by the page only after the organiser confirmed — a stray POST
        // without it deletes nothing.
        $data = $request->validate(['confirm' => ['required', 'accepted'], 'then_place' => ['sometimes', 'boolean'],
            'system' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_:\-]+$/']]);

        $out = $reset->deleteAll($event, $request->user());
        if ($out['ok'] && ($data['then_place'] ?? false)) {
            $placed = $reset->autoPlace($event->fresh(), $request->user(), $data['system'] ?? null);
            $out['message'] .= ' '.$placed['message'];
            $out += ['placed' => $placed['placed'] ?? 0, 'created' => $placed['created'] ?? 0, 'left' => $placed['left'] ?? 0];
        }
        $this->push($event, $out['ok']);

        return response()->json(['success' => $out['ok']] + collect($out)->except('ok')->all(), $out['ok'] ? 200 : 422);
    }

    /** The weight tables auto-place can follow, for the picker. */
    public function systems(Request $request, ClubEvent $event, DivisionReset $reset, EventAccess $access): JsonResponse
    {
        $this->allow($request, $event, $access);

        return response()->json(['success' => true] + $reset->systems($event), 200, ['Cache-Control' => 'no-store, private']);
    }

    public function autoPlace(Request $request, ClubEvent $event, DivisionReset $reset, EventAccess $access): JsonResponse
    {
        $this->allow($request, $event, $access);

        // The weight table to place by — one of the event's own (checked in the service).
        $data = $request->validate(['system' => ['nullable', 'string', 'max:64', 'regex:/^[A-Za-z0-9_:\-]+$/']]);

        $out = $reset->autoPlace($event, $request->user(), $data['system'] ?? null);
        $this->push($event, $out['ok']);

        return response()->json(['success' => $out['ok']] + collect($out)->except('ok')->all(), $out['ok'] ? 200 : 422);
    }

    /** Every open board and console re-reads (a refresh signal, never the data). */
    private function push(ClubEvent $event, bool $changed): void
    {
        if (! $changed) {
            return;
        }
        rescue(function () use ($event) {
            if (! \Realtime()->enabled()) {
                return;
            }
            $ids = app(AudienceResolver::class)->forEvent($event);
            if ($ids) {
                \Realtime()->publishMany(array_map(fn (int $uid) => [
                    'topic' => \Realtime()->userTopic($uid, 'events'),
                    'payload' => ['action' => 'draw', 'event' => $event->uuid],
                ], $ids));
            }
        }, null, false);
    }
}
