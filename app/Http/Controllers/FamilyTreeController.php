<?php

namespace App\Http\Controllers;

use App\Models\Person;
use App\Models\PersonParentLink;
use App\Models\PersonUnion;
use App\Models\User;
use App\Models\UserNotification;
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
        $data['nodes'] = array_map(fn ($n) => $this->decorateNode($n), $data['nodes']);
        $data['parentEdges'] = array_map(fn ($e) => $this->decorateEdge($e, $root->id, (int) Auth::id()), $data['parentEdges']);
        $data['unions'] = array_map(fn ($e) => $this->decorateEdge($e, $root->id, (int) Auth::id()), $data['unions']);

        return response()->json($data);
    }

    /** Add a parent / child / spouse to a focus person (new node or existing). */
    public function addRelative(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'focus_person_id' => ['required', 'integer'],
            'type' => ['required', Rule::in(['parent', 'child', 'spouse'])],
            'existing_person_id' => ['nullable', 'integer'],
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
            return $this->registerChildDependent($focus, $validated, $actor);
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

        $this->notifyCounterpartOfRequest($edge, $actor, $validated['type']);

        return response()->json([
            'success' => true,
            'message' => $edge->status === 'pending'
                ? __('A request was sent — it appears once they confirm.')
                : __('Relative added.'),
            'status' => $edge->status,
        ]);
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

        // Only the counterpart (a person on the edge that isn't the requester) may respond.
        if (! $this->edgeTouches($edge, $me->id) || $edge->created_by_user_id === $actor->id) {
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
     * Register a real family dependent (User + UserRelationship) under an
     * account-holding focus, link it into the tree, and return success. This is
     * what makes "add a child" in the tree also appear on the Family page.
     */
    private function registerChildDependent(Person $focus, array $v, User $actor): JsonResponse
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
     * A pending edge is answerable by the viewer when it touches their own node
     * and they are not the one who created it. Strips the internal created_by.
     */
    private function decorateEdge(array $edge, int $rootPersonId, int $viewerUserId): array
    {
        $endpoints = $edge['type'] === 'parent'
            ? [$edge['p'], $edge['c']]
            : [$edge['a'], $edge['b']];

        $edge['can_respond'] = $edge['status'] === 'pending'
            && in_array($rootPersonId, $endpoints, true)
            && $edge['created_by'] !== $viewerUserId;

        unset($edge['created_by']);

        return $edge;
    }

    private function notifyCounterpartOfRequest(PersonParentLink|PersonUnion $edge, $actor, string $type): void
    {
        if ($edge->status !== 'pending') {
            return; // auto-confirmed (account-less counterpart) — nobody to ask
        }

        // Find the other person's account, if any.
        $otherPersonId = $edge instanceof PersonParentLink
            ? ($edge->parent_person_id === $this->kin->personFor($actor)->id ? $edge->child_person_id : $edge->parent_person_id)
            : $edge->partnerIdOf($this->kin->personFor($actor)->id);

        $other = Person::with('user')->find($otherPersonId);
        if (! $other?->user_id) {
            return;
        }

        UserNotification::notifyUser(
            $other->user_id,
            'family_request',
            __(':name added you as family (:rel).', ['name' => $actor->full_name, 'rel' => __($type)]),
            [
                'actor_id' => $actor->id,
                'icon' => 'bi-diagram-3',
                'body' => __('Open your family tree to confirm.'),
                'action_url' => route('me.family'),
            ],
        );
    }
}
