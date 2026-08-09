<?php

namespace App\Mcp\Tools;

use App\Events\Support\EventAccess;
use App\Models\ClubEvent;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Title;

/**
 * Discovery for events. The other event tools (bracket, athletes, arrange) all
 * take an event uuid but nothing could hand one out — this closes that gap.
 *
 * Visibility is delegated to App\Events\Support\EventAccess so the MCP can
 * never surface an event the acting user could not open in the web UI.
 */
#[Title('List events')]
#[Description('List events visible to the acting user. Defaults to open events only — those that have not started yet, plus those running right now. Set state=all to include finished events.')]
class ListEventsTool extends BaseTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            'state' => $schema->string()
                ->description('open (default: not started + running now), live, upcoming, past, or all.'),
            'search' => $schema->string()
                ->description('Filter by event title, host club, location or sport (case-insensitive, partial match).'),
            'page' => $schema->integer()->min(1)
                ->description('1-based page number. Defaults to 1.'),
            'per_page' => $schema->integer()->min(1)
                ->description('Results per page. Capped by the server (default 25).'),
        ];
    }

    public function handle(Request $request): Response
    {
        $user = $this->guard($request);

        if ($user instanceof Response) {
            return $user;
        }

        $state = strtolower(trim((string) $request->get('state', 'open')));
        if (! in_array($state, ['open', 'live', 'upcoming', 'past', 'all'], true)) {
            return Response::error('Invalid state. Use one of: open, live, upcoming, past, all.');
        }

        $perPage = $this->pageSize($request->get('per_page'));
        $page = max(1, (int) ($request->get('page') ?: 1));
        $search = trim((string) $request->get('search', ''));

        $clubIds = $user->memberClubs()->pluck('tenants.id');
        $myCountries = $user->memberClubs()->pluck('tenants.country')->filter()->unique()->values();

        $query = ClubEvent::query()
            ->where('is_archived', false)
            ->where('status', '!=', 'cancelled')
            // Coarse pre-scope so the DB never returns the whole table; the exact
            // per-event decision is still made by EventAccess below.
            ->where(function ($q) use ($clubIds, $myCountries, $user) {
                $q->whereIn('tenant_id', $clubIds)
                    ->orWhereIn('scope', ['inter_club', 'worldwide'])
                    ->orWhere(fn ($w) => $w->whereIn('scope', ['nationwide', 'regional'])
                        ->whereHas('tenant', fn ($t) => $t->whereIn('country', $myCountries)))
                    ->orWhere('created_by', $user->id);
            })
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('title', 'like', "%{$search}%")
                        ->orWhere('location', 'like', "%{$search}%")
                        ->orWhere('sport', 'like', "%{$search}%")
                        ->orWhereHas('tenant', fn ($t) => $t->where('club_name', 'like', "%{$search}%"));
                });
            })
            ->withCount('participantRegistrations')
            ->with('tenant:id,club_name,country');

        $today = now()->startOfDay()->toDateString();

        // Date-grain narrowing; end_time precision is applied in PHP via hasEnded().
        if (in_array($state, ['open', 'live', 'upcoming'], true)) {
            $query->where(function ($q) use ($today) {
                $q->where(fn ($w) => $w->whereNotNull('end_date')->whereDate('end_date', '>=', $today))
                    ->orWhere(fn ($w) => $w->whereNull('end_date')->whereDate('date', '>=', $today));
            })->orderBy('date')->orderBy('start_time');
        } elseif ($state === 'past') {
            $query->orderByDesc('date');
        } else {
            $query->orderByDesc('date');
        }

        $access = app(EventAccess::class);

        $events = $query->limit(500)->get()
            ->filter(fn (ClubEvent $e) => $access->visible($e, $user))
            ->filter(function (ClubEvent $e) use ($state) {
                return match ($state) {
                    'open' => ! $e->hasEnded(),
                    'live' => $e->isOngoing(),
                    'upcoming' => ! $e->hasStarted(),
                    'past' => $e->hasEnded(),
                    default => true,
                };
            })
            ->values();

        $total = $events->count();

        $rows = $events->forPage($page, $perPage)->map(fn (ClubEvent $e) => [
            // Public key is the uuid — the other event tools take this.
            'uuid' => $e->uuid,
            'title' => $e->title,
            'state' => $e->isOngoing() ? 'live' : ($e->hasEnded() ? 'past' : 'upcoming'),
            'date' => $e->date?->toDateString(),
            'end_date' => $e->end_date?->toDateString(),
            'start_time' => $e->start_time,
            'end_time' => $e->end_time,
            'club' => $e->tenant?->club_name,
            'country' => $e->tenant?->country,
            'location' => $e->location,
            'event_type' => $e->event_type,
            'sport' => $e->sport,
            'scope' => $e->scope,
            'level' => $e->level,
            'participants' => (int) ($e->participant_registrations_count ?? 0),
            'max_capacity' => $e->max_capacity,
            'can_manage' => $access->canManage($e, $user),
        ])->values();

        return Response::json([
            'state' => $state,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'events' => $rows,
        ]);
    }
}
