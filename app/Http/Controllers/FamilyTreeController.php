<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\PersonParentLink;
use App\Models\PersonUnion;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\UserRelationship;
use App\Services\FamilyService;
use App\Services\KinshipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The Family Tree page — a graphical, unlimited-depth kinship tree built on the
 * `persons` graph (see App\Services\KinshipService). Renders an ego-centric
 * window that re-centres as you tap through relatives, so an infinite tree
 * stays cheap to draw.
 *
 * Mobile and desktop are genuinely different layouts → two separate views
 * (family/mobile/tree vs family/tree), selected by the shared $isMobile flag.
 */
class FamilyTreeController extends Controller
{
    public function __construct(private readonly KinshipService $kin) {}

    /** The page shell (mobile bottom-sheet UX vs desktop modal UX). */
    public function index(): View
    {
        $this->kin->syncGuardianship(Auth::user()); // registered family ↔ tree
        $root = $this->kin->personFor(Auth::user());

        $isMobile = (bool) request()->attributes->get('is_mobile', false);
        $view = $isMobile && view()->exists('family.mobile.tree') ? 'family.mobile.tree' : 'family.tree';

        return view($view, ['rootPersonId' => $root->id]);
    }

    /** JSON window around a focus person, everything labelled relative to "you". */
    public function data(Request $request): JsonResponse
    {
        $this->kin->syncGuardianship(Auth::user()); // reflect registered kids
        $root = $this->kin->personFor(Auth::user());
        $focus = $this->resolveFocus($request->query('focus'), $root);

        if ($focus === null) {
            return response()->json(['message' => 'Not found or not visible to you.'], 404);
        }

        $data = $this->kin->neighborhood($focus, $root);

        // Precompute once (avoids an N+1 per edge): which of this window's
        // people does the viewer manage as a recognized guardian? Lets a
        // guardian confirm/reject a pending parent-link aimed at their
        // managed-dependent minor, who can never confirm anything themselves.
        $personUserIds = Person::whereIn('id', array_column($data['nodes'], 'id'))
            ->whereNotNull('user_id')->pluck('user_id', 'id');
        $guardianOfUserIds = UserRelationship::where('guardian_user_id', Auth::id())->pluck('dependent_user_id');

        $data['nodes'] = array_map(fn ($n) => $this->decorateNode($n), $data['nodes']);
        $data['parentEdges'] = array_map(fn ($e) => $this->decorateEdge($e, $root->id, (int) Auth::id(), $personUserIds, $guardianOfUserIds), $data['parentEdges']);
        $data['unions'] = array_map(fn ($e) => $this->decorateEdge($e, $root->id, (int) Auth::id(), $personUserIds, $guardianOfUserIds), $data['unions']);

        return response()->json($data);
    }

    /** Add a parent / child / spouse to a focus person (new node or existing). */
    public function addRelative(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'focus_person_id' => ['required', 'integer'],
            'type' => ['required', Rule::in(['parent', 'child', 'spouse'])],
            'existing_person_id' => ['nullable', 'integer'],
            'other_parent_person_id' => ['nullable', 'integer'],
            'full_name' => ['required_without:existing_person_id', 'nullable', 'string', 'max:120'],
            'gender' => ['nullable', Rule::in(['m', 'f'])],
            'birth_date' => ['nullable', 'date'],
            'is_deceased' => ['nullable', 'boolean'],
            'state' => ['nullable', Rule::in(['married', 'partner', 'engaged', 'divorced', 'widowed'])],
        ]);

        $actor = Auth::user();
        $root = $this->kin->personFor($actor);
        $focus = $this->resolveFocus($validated['focus_person_id'], $root);

        if ($focus === null) {
            return response()->json(['success' => false, 'message' => 'You cannot add relatives here.'], 403);
        }

        // ── Registered-kid bridge ──────────────────────────────────────────
        // Adding a LIVING, NEW child under an account holder registers a real
        // family dependent (User + UserRelationship), so the same person shows
        // up on the Family page too — the tree and the family list stay one set.
        if ($validated['type'] === 'child'
            && empty($validated['existing_person_id'])
            && $focus->user_id
            && empty($validated['is_deceased'])) {
            return $this->registerChildDependent($focus, $root, $validated, $actor);
        }

        // Resolve the counterpart: an existing (visible) person, or a brand-new node.
        if (! empty($validated['existing_person_id'])) {
            $counterpart = $this->resolveFocus($validated['existing_person_id'], $root);
            if ($counterpart === null) {
                return response()->json(['success' => false, 'message' => 'That person is not in your family.'], 403);
            }
        } else {
            $counterpart = [
                'full_name' => $validated['full_name'],
                'gender' => $validated['gender'] ?? null,
                'birth_date' => $validated['birth_date'] ?? null,
                'is_deceased' => $validated['is_deceased'] ?? false,
            ];
        }

        try {
            $edge = match ($validated['type']) {
                'parent' => $this->kin->addParent($focus, $counterpart, $actor),
                'child' => $this->kin->addChild($focus, $counterpart, $actor),
                'spouse' => $this->kin->addSpouse($focus, $counterpart, $actor, ['state' => $validated['state'] ?? 'married']),
            };
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        $this->kin->notifyCounterpartOfRequest($edge, $actor, $validated['type']);

        // A child can have a second parent recorded in the same step — e.g. the
        // acting parent has 2+ spouses and picked which one is the mother/father
        // of this specific child, so the two kids don't get "mixed" under a
        // single undifferentiated parent set.
        if ($validated['type'] === 'child' && ! empty($validated['other_parent_person_id'])) {
            $this->linkSecondParent((int) $validated['other_parent_person_id'], $root, Person::find($edge->child_person_id), $actor);
        }

        return response()->json([
            'success' => true,
            'message' => $edge->status === 'pending'
                ? __('A request was sent — it appears once they confirm.')
                : __('Relative added.'),
            'status' => $edge->status,
        ]);
    }

    /**
     * Link a second parent to a child — e.g. the spouse who is the child's
     * actual mother/father — so a person with multiple spouses never has
     * their kids "mixed" under a single undifferentiated parent. Reuses the
     * same pending/confirmed + notification logic every other edge uses.
     */
    private function linkSecondParent(int $otherParentId, Person $root, ?Person $child, User $actor, bool $autoConfirm = false): void
    {
        if (! $child) {
            return;
        }
        $otherParent = $this->resolveFocus($otherParentId, $root);
        if ($otherParent === null || $otherParent->id === $child->id) {
            return; // not a real, visible relative — silently skip rather than fail the whole request
        }

        $edge = $this->kin->linkParent($otherParent, $child, $actor);
        if ($autoConfirm && $edge->status !== 'confirmed') {
            $this->kin->confirm($edge, $actor);

            return; // already settled — no confirmation request to send
        }
        $this->kin->notifyCounterpartOfRequest($edge, $actor, 'parent');
    }

    /** Confirm or reject a pending relationship request aimed at the viewer. */
    public function respond(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'edge_type' => ['required', Rule::in(['parent', 'union'])],
            'edge_id' => ['required', 'integer'],
            'action' => ['required', Rule::in(['confirm', 'reject'])],
        ]);

        $actor = Auth::user();
        $me = $this->kin->personFor($actor);

        /** @var PersonParentLink|PersonUnion|null $edge */
        $edge = $validated['edge_type'] === 'parent'
            ? PersonParentLink::find($validated['edge_id'])
            : PersonUnion::find($validated['edge_id']);

        if (! $edge || $edge->status !== 'pending') {
            return response()->json(['success' => false, 'message' => 'Request no longer available.'], 404);
        }

        // Only the counterpart may respond — or a recognized guardian responding
        // on behalf of their managed-dependent minor, who (per design) can
        // never confirm anything themselves.
        $canRespond = $this->edgeTouches($edge, $me->id) || $this->guardianCanRespondFor($edge, $actor);
        if (! $canRespond || $edge->created_by_user_id === $actor->id) {
            return response()->json(['success' => false, 'message' => 'This request is not yours to answer.'], 403);
        }

        $validated['action'] === 'confirm'
            ? $this->kin->confirm($edge, $actor)
            : $this->kin->reject($edge, $actor);

        // Tell the requester what happened.
        if ($edge->created_by_user_id) {
            UserNotification::notifyUser(
                $edge->created_by_user_id,
                'family_response',
                $validated['action'] === 'confirm'
                    ? __(':name confirmed your family connection.', ['name' => $actor->full_name])
                    : __(':name declined your family request.', ['name' => $actor->full_name]),
                ['actor_id' => $actor->id, 'icon' => 'bi-diagram-3', 'action_url' => route('me.family')],
            );
        }

        return response()->json(['success' => true, 'message' => __('Done.')]);
    }

    /**
     * Remove/unlink a relationship (parent-link or union) from the graph —
     * e.g. correcting a mistaken or duplicate relative. Either edge can be
     * removed by anyone who can already see both people it connects (the
     * same visibility rule "add relative" already uses), not just the two
     * people directly on the edge — so a family admin can fix a mistake
     * anywhere in their visible tree, not only relationships touching them.
     */
    public function removeRelative(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'edge_type' => ['required', Rule::in(['parent', 'union'])],
            'edge_id' => ['required', 'integer'],
        ]);

        $actor = Auth::user();
        $root = $this->kin->personFor($actor);
        $visible = $this->kin->connectedComponent($root, ['confirmed', 'pending']);

        /** @var PersonParentLink|PersonUnion|null $edge */
        $edge = $validated['edge_type'] === 'parent'
            ? PersonParentLink::find($validated['edge_id'])
            : PersonUnion::find($validated['edge_id']);

        if (! $edge) {
            return response()->json(['success' => false, 'message' => 'Relationship not found.'], 404);
        }

        $touches = $edge instanceof PersonParentLink
            ? [$edge->parent_person_id, $edge->child_person_id]
            : [$edge->person_low_id, $edge->person_high_id];

        if (array_diff($touches, $visible) !== []) {
            return response()->json(['success' => false, 'message' => 'You cannot manage this relationship.'], 403);
        }

        // Let each side know their family connection was removed, unless they're the one removing it.
        foreach (Person::whereIn('id', $touches)->whereNotNull('user_id')->get() as $person) {
            if ((int) $person->user_id === (int) $actor->id) {
                continue;
            }
            UserNotification::notifyUser(
                $person->user_id,
                'family_removed',
                __(':name removed a family connection.', ['name' => $actor->full_name]),
                ['actor_id' => $actor->id, 'icon' => 'bi-diagram-3', 'action_url' => route('me.family')],
            );
        }

        $edge->delete();

        return response()->json(['success' => true, 'message' => __('Relationship removed.')]);
    }

    /**
     * Register a real family dependent (User + UserRelationship) under an
     * account-holding focus, link it into the tree, and return success. This is
     * what makes "add a child" in the tree also appear on the Family page.
     */
    private function registerChildDependent(Person $focus, Person $root, array $v, User $actor): JsonResponse
    {
        $guardian = User::find($focus->user_id);
        if (! $guardian) {
            return response()->json(['success' => false, 'message' => 'Guardian account not found.'], 422);
        }

        $g = $v['gender'] ?? null;
        $dependent = app(FamilyService::class)->createDependent($guardian, [
            'full_name' => $v['full_name'],
            'gender' => $g === 'f' ? 'Female' : ($g === 'm' ? 'Male' : null),
            'birthdate' => ! empty($v['birth_date']) ? $v['birth_date'] : null,
            'nationality' => $guardian->nationality,
            'relationship_type' => $g === 'f' ? 'daughter' : ($g === 'm' ? 'son' : 'child'),
        ]);

        $childPerson = $this->kin->personFor($dependent);
        $edge = $this->kin->linkParent($focus, $childPerson, $actor);
        if ($edge->status !== 'confirmed') {
            $this->kin->confirm($edge, $actor); // a managed dependent needs no confirmation
        }

        // Same reasoning as the general child branch: record the actual other
        // parent (e.g. which of the guardian's spouses is the mother) instead
        // of leaving this child looking like they only descend from one side.
        // Auto-confirmed like the primary edge above — the guardian already has
        // full, unilateral authority over a dependent they manage, so requiring
        // the other spouse to separately confirm "yes, also my child" here would
        // just leave the half/full-sibling distinction wrong in the meantime.
        if (! empty($v['other_parent_person_id'])) {
            $this->linkSecondParent((int) $v['other_parent_person_id'], $root, $childPerson, $actor, autoConfirm: true);
        }

        return response()->json([
            'success' => true,
            'message' => __('Child added to your family.'),
            'status' => 'confirmed',
        ]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Resolve a focus id to a Person the viewer is allowed to see — anyone in
     * their family's connected component (confirmed OR pending edges). Null id
     * defaults to the viewer's own node.
     */
    private function resolveFocus(mixed $focusId, Person $root): ?Person
    {
        if (empty($focusId)) {
            return $root;
        }

        $focusId = (int) $focusId;
        if ($focusId === $root->id) {
            return $root;
        }

        $visible = $this->kin->connectedComponent($root, ['confirmed', 'pending']);

        return in_array($focusId, $visible, true) ? Person::find($focusId) : null;
    }

    private function edgeTouches(PersonParentLink|PersonUnion $edge, int $personId): bool
    {
        return $edge instanceof PersonParentLink
            ? in_array($personId, [$edge->parent_person_id, $edge->child_person_id], true)
            : in_array($personId, [$edge->person_low_id, $edge->person_high_id], true);
    }

    /**
     * May $actor confirm/reject this pending PARENT-link on behalf of one of
     * the two people on it, because they're that person's recognized
     * guardian (UserRelationship)? A managed-dependent minor can never
     * confirm anything themselves, so their real guardian stands in.
     */
    private function guardianCanRespondFor(PersonParentLink|PersonUnion $edge, User $actor): bool
    {
        if (! $edge instanceof PersonParentLink) {
            return false;
        }

        $userIds = Person::whereIn('id', [$edge->parent_person_id, $edge->child_person_id])
            ->whereNotNull('user_id')
            ->pluck('user_id');

        return UserRelationship::where('guardian_user_id', $actor->id)->whereIn('dependent_user_id', $userIds)->exists();
    }

    /** Add the resolved avatar URL to a node payload (service stays HTTP-free). */
    private function decorateNode(array $node): array
    {
        $node['avatar'] = match (true) {
            ! empty($node['photo']) => asset('storage/'.$node['photo']),
            ! empty($node['user_photo']) => asset('storage/'.$node['user_photo']).'?v='.($node['user_v'] ?? ''),
            default => null,
        };
        unset($node['photo'], $node['user_photo'], $node['user_v']);

        return $node;
    }

    /**
     * A pending edge is answerable by the viewer when it touches their own
     * node, OR (for a parent-link) they're the recognized guardian of one of
     * the two people on it — a managed-dependent minor can never confirm
     * anything themselves, so their real guardian stands in. Never answerable
     * by whoever created the request. Strips the internal created_by.
     */
    private function decorateEdge(array $edge, int $rootPersonId, int $viewerUserId, $personUserIds, $guardianOfUserIds): array
    {
        $endpoints = $edge['type'] === 'parent'
            ? [$edge['p'], $edge['c']]
            : [$edge['a'], $edge['b']];

        $touchesRoot = in_array($rootPersonId, $endpoints, true);
        $guardianCanRespond = $edge['type'] === 'parent'
            && collect($endpoints)->map(fn ($id) => $personUserIds[$id] ?? null)->filter()->intersect($guardianOfUserIds)->isNotEmpty();

        $edge['can_respond'] = $edge['status'] === 'pending'
            && ($touchesRoot || $guardianCanRespond)
            && $edge['created_by'] !== $viewerUserId;

        unset($edge['created_by']);

        return $edge;
    }

}
