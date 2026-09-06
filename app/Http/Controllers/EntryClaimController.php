<?php

namespace App\Http\Controllers;

use App\Events\Support\EntryClaim;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * The athlete's side of a claim link — the ONE unauthenticated write surface
 * this feature adds.
 *
 * Documentation/EVENTS-PUBLIC-ENTRY.md, Door B. Somebody with no account opens
 * a link from a WhatsApp message and gives what only they know. Every rule is
 * in App\Events\Support\EntryClaim; this controller carries the request and
 * nothing else.
 *
 * Failure is uniform on purpose. An unknown uuid, a wrong secret, an expired,
 * revoked or already-used link all render the SAME page — telling a stranger
 * which links are real is the whole attack.
 */
class EntryClaimController extends Controller
{
    public function show(Request $request, string $claim, EntryClaim $claims)
    {
        $row = $claims->resolve($claim, (string) $request->query('t'));

        if (! $row) {
            return response()->view('entry.mobile.claim-dead', [], 404);
        }

        $event = $row->event;
        $registration = $row->registration;

        return view('entry.mobile.claim', [
            'claimUuid' => $row->uuid,
            // Echoed back into the form so the submit carries the same secret.
            // It never leaves this page and is never stored client-side.
            'secret' => (string) $request->query('t'),
            'ev' => $this->eventPayload($event),
            'claim' => [
                'athlete' => $row->athlete?->full_name ?? '—',
                'coach' => $row->issuer?->full_name ?? __('events.claim_your_coach'),
                'club' => $registration?->representingTenant?->club_name
                    ?? $event->tenant?->club_name ?? '',
                'expires' => $row->expires_at->diffForHumans(null, true),
            ],
            'belts' => $this->belts(),
        ]);
    }

    /** The athlete's answers. Rejected the same way for everyone who is not them. */
    public function store(Request $request, string $claim, EntryClaim $claims): JsonResponse
    {
        $row = $claims->resolve($claim, (string) $request->input('t'));

        if (! $row) {
            return response()->json(['success' => false, 'message' => __('events.claim_dead')], 404);
        }

        $data = $request->validate([
            // Never required — of anyone, on any form (CLAUDE.md). Only its
            // FORMAT is enforced, and a blank sends them to the weigh-in desk.
            'birthdate' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', 'in:Male,Female'],
            'weight' => ['nullable', 'numeric', 'min:15', 'max:250'],
            'belt' => ['nullable', 'string', 'max:32'],
            'guardian_name' => ['nullable', 'string', 'min:2', 'max:120'],
            // The account is the one thing this page does demand: without it
            // there is nobody to hold the place.
            'email' => ['required', 'email', 'max:190'],
            'password' => ['required', 'string', 'min:8', 'max:200'],
        ]);

        // A minor's account belongs to their guardian, so the guardian must be
        // named. Checked here rather than as a rule because it depends on the
        // birthdate they just gave.
        if (! empty($data['birthdate'])
            && \Carbon\Carbon::parse($data['birthdate'])->age < 18
            && trim((string) ($data['guardian_name'] ?? '')) === '') {
            return response()->json([
                'success' => false,
                'message' => __('events.claim_guardian_required'),
                'field' => 'guardian_name',
            ], 422);
        }

        $result = $claims->complete($row, [
            'birthdate' => $data['birthdate'] ?? null,
            'gender' => $data['gender'] ?? null,
            'weight' => $data['weight'] ?? null,
            'belt' => $data['belt'] ?? null,
            'guardian_name' => $data['guardian_name'] ?? null,
            'email' => $data['email'],
            'password' => $data['password'],
        ], $request->ip());

        if (! $result['ok']) {
            return response()->json(['success' => false, 'message' => $result['message']], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'division' => $result['division'],
            'is_minor' => $result['is_minor'],
        ]);
    }

    /** Only what a poster shows. Nothing about anybody else in the draw. */
    private function eventPayload($event): array
    {
        $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $event->color) ? $event->color : '#7c3aed';

        $images = is_array($event->images) ? $event->images : [];

        return [
            'title' => $event->title,
            'club' => $event->tenant?->club_name ?? '',
            // The mark this page wears. A claim link is white-labelled like
            // every other page a stranger sees — see entry/layout.blade.php.
            'club_logo' => $event->tenant?->logo ? file_url($event->tenant->logo) : null,
            'sport' => $event->sport ? ucfirst($event->sport) : '',
            'type' => __('events.claim_competition'),
            'color' => $color,
            'photo' => $images ? file_url($images[0]) : asset('images/mock/event-backdrop.jpg'),
            'date' => $event->date?->format('D, j M Y') ?? '',
            'time' => $event->start_time ? date('g:i A', strtotime($event->start_time)) : '',
            'location' => $event->location ?: '',
            'deadline' => $event->enrollment_ends_at?->format('D, j M') ?: '',
            'fee' => __('events.claim_fee_club'),
        ];
    }

    /** @return array<int, array{v: string, label: string, hex: string}> */
    private function belts(): array
    {
        return [
            ['v' => 'white', 'label' => __('events.belt_white'), 'hex' => '#e5e7eb'],
            ['v' => 'yellow', 'label' => __('events.belt_yellow'), 'hex' => '#facc15'],
            ['v' => 'green', 'label' => __('events.belt_green'), 'hex' => '#22c55e'],
            ['v' => 'blue', 'label' => __('events.belt_blue'), 'hex' => '#3b82f6'],
            ['v' => 'brown', 'label' => __('events.belt_brown'), 'hex' => '#92400e'],
            ['v' => 'black', 'label' => __('events.belt_black'), 'hex' => '#111827'],
        ];
    }
}
