<?php

namespace App\Events\Controllers;

use App\Clubs\Models\Tenant;
use App\Events\Models\ClubEvent;
use App\Events\Support\CategorySystems;
use App\Events\Support\EventAccess;
use App\Http\Controllers\Controller;
use App\Members\Models\User;
use App\Sports\Combat\SportRegistry;
use App\Support\Modules\ModuleRegistry;
use App\Support\Modules\Surfaces\HostsEvents;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The rule books an organiser picks from — category systems (age groups ×
 * weight classes) and belt systems — and the organiser's own (2026-09-28).
 * The rules are CategorySystems'; this decides only who may ask.
 */
class CategorySystemController extends Controller
{
    public function __construct(private CategorySystems $systems) {}

    /** Every system a host may run a sport under, with each weight table. */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sport' => ['required', 'string', Rule::in(array_keys(app(SportRegistry::class)->all()))],
            'host' => ['nullable', 'string', 'regex:/^[a-z_]{2,32}:\d{1,12}$/'],
        ]);
        [$type, $id] = $this->host($request->user(), $data['host'] ?? null);

        return response()->json([
            'success' => true,
            'weights' => array_values(array_map(fn ($s) => $s->toArray(), $this->systems->weights($data['sport'], $type, $id))),
            'belts' => array_values(array_map(fn ($s) => $s->toArray(), $this->systems->belts($data['sport'], $type, $id))),
        ]);
    }

    /** Save an organiser's own system, owned by (and only by) a host they run. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sport' => ['required', 'string', Rule::in(array_keys(app(SportRegistry::class)->all()))],
            'kind' => ['required', Rule::in(['weights', 'belts'])],
            'label' => ['required', 'string', 'max:120'],
            'host' => ['required', 'string', 'regex:/^[a-z_]{2,32}:\d{1,12}$/'],
            'data' => ['required', 'array'],
            'data.age_groups' => ['nullable', 'array', 'max:30'],
            'data.classes' => ['nullable', 'array', 'max:30'],
            'data.ladder' => ['nullable', 'array', 'max:30'],
        ]);
        [$type, $id] = $this->host($request->user(), $data['host']);
        abort_unless($type && $id, 403);

        $r = $this->systems->saveCustom($data['sport'], $data['kind'], $data['label'], $data['data'], $type, $id, $request->user()->id);
        $system = $data['kind'] === 'belts'
            ? ($this->systems->belts($data['sport'], $type, $id)[$r->key()] ?? null)
            : ($this->systems->weights($data['sport'], $type, $id)[$r->key()] ?? null);

        return response()->json(['success' => true, 'message' => __('events::events.rulebook_saved'), 'system' => $system?->toArray()]);
    }

    /** Change an event's rule book — everybody entered is re-sorted (CategorySystems::change). */
    public function update(Request $request, ClubEvent $event, EventAccess $access): JsonResponse
    {
        abort_unless($request->user() && $access->canManage($event, $request->user()), 403);
        $data = $request->validate([
            'category_system' => ['nullable', 'string', 'max:64'],
            'belt_system' => ['nullable', 'string', 'max:64'],
        ]);

        $r = $this->systems->change($event, $data['category_system'] ?? null, $data['belt_system'] ?? null);
        if (! $r['ok']) {
            return response()->json(['success' => false, 'message' => $r['message'] ?? __('events::events.rulebook_invalid')], 422);
        }
        $fresh = $event->fresh();
        // A host that keeps its own record of the event (a federation) follows.
        \App\Events\Support\EventHost::changed($fresh);

        return response()->json([
            'success' => true,
            'message' => __('events::events.rulebook_changed', ['moved' => $r['moved'] ?? 0, 'created' => $r['created'] ?? 0]),
            'category_system' => $fresh->category_system,
            'belt_system' => $fresh->belt_system,
            'unplaced' => $r['unplaced'] ?? 0,
        ]);
    }

    /**
     * The host a request names, if this user runs it — else [null, null].
     * A club: its owner, a club admin, or a super-admin. Anything else: the
     * module that hosts that kind decides (HostsEvents).
     *
     * @return array{0: ?string, 1: ?int}
     */
    private function host(?User $user, ?string $host): array
    {
        if (! $user || ! $host) {
            return [null, null];
        }
        [$type, $id] = explode(':', $host, 2);
        $id = (int) $id;

        if ($type === 'club') {
            $ok = $user->isSuperAdmin()
                || Tenant::whereKey($id)->where('owner_user_id', $user->id)->exists()
                || $user->isClubAdmin($id);

            return $ok ? ['club', $id] : [null, null];
        }

        $module = app(ModuleRegistry::class)->contributingTo(HostsEvents::class)
            ->first(fn (HostsEvents $m) => in_array($type, $m->eventHostTypes(), true));

        return $module && $module->managesEventsOf($type, $id, $user) ? [$type, $id] : [null, null];
    }
}
