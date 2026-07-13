<?php

namespace App\Services;

use App\Models\Person;
use App\Models\PersonParentLink;
use App\Models\PersonUnion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The family-tree brain.
 *
 * Everything that reasons about kinship lives here so the graph logic is in one
 * place. Only two primitive edges are stored (parent_of, union); every derived
 * relationship — grandparent, sibling, uncle, cousin, in-law — is COMPUTED by
 * walking the graph, never stored, so the data can never contradict itself.
 *
 * Authenticity rule: an edge that involves another person's account is created
 * `pending` and must be confirmed by that account. An edge whose counterpart is
 * account-less (a deceased ancestor, a young child) is auto-`confirmed` because
 * the acting relative vouches for it.
 */
class KinshipService
{
    // =====================================================================
    // Person nodes
    // =====================================================================

    /** Get (or lazily create) the tree node for an account. */
    public function personFor(User $user): Person
    {
        return Person::firstOrCreate(
            ['user_id' => $user->id],
            [
                'full_name' => $user->full_name ?: $user->name ?: 'Member',
                'gender' => $this->normalizeGender($user->gender),
                'birth_date' => $user->birthdate,
                'created_by_user_id' => $user->id,
            ],
        );
    }

    /** Create an account-less person (an ancestor, a child with no login). */
    public function createPerson(array $attrs, ?User $actor = null): Person
    {
        return Person::create([
            'full_name' => $attrs['full_name'] ?? 'Unknown',
            'gender' => $this->normalizeGender($attrs['gender'] ?? null),
            'birth_date' => $attrs['birth_date'] ?? null,
            'death_date' => $attrs['death_date'] ?? null,
            'is_deceased' => $attrs['is_deceased'] ?? false,
            'photo' => $attrs['photo'] ?? null,
            'notes' => $attrs['notes'] ?? null,
            'created_by_user_id' => $actor?->id,
        ]);
    }

    /**
     * Coerce any gender representation to the persons enum ('m' | 'f' | null).
     * User accounts store gender inconsistently ('Male'/'Female', 'male', 'm'…);
     * the persons table only accepts 'm'/'f', so normalise at the boundary.
     */
    private function normalizeGender(?string $gender): ?string
    {
        $g = strtolower(trim((string) $gender));

        return match (true) {
            $g === '' => null,
            str_starts_with($g, 'm') => 'm',
            str_starts_with($g, 'f') => 'f',
            default => null,
        };
    }

    /** Coerce a Person|array into a Person (creating an account-less node from an array). */
    private function resolvePerson(Person|array $person, ?User $actor): Person
    {
        return $person instanceof Person ? $person : $this->createPerson($person, $actor);
    }

    // =====================================================================
    // Guardianship bridge — keep the tree in sync with REGISTERED family
    // (UserRelationship guardian↔dependent), so registered kids appear in the
    // tree and both systems describe the same people.
    // =====================================================================

    /**
     * Mirror a user's registered family relationships into the tree as confirmed
     * edges. Idempotent — safe to call on every page load.
     */
    public function syncGuardianship(User $user): void
    {
        $this->personFor($user); // ensure the user's own node exists

        $rels = \App\Models\UserRelationship::where('guardian_user_id', $user->id)
            ->orWhere('dependent_user_id', $user->id)
            ->get();

        foreach ($rels as $r) {
            $guardian = User::find($r->guardian_user_id);
            $dependent = User::find($r->dependent_user_id);
            if (! $guardian || ! $dependent) {
                continue;
            }

            $gp = $this->personFor($guardian);
            $dp = $this->personFor($dependent);
            $type = strtolower((string) $r->relationship_type);

            match (true) {
                in_array($type, ['son', 'daughter', 'child', ''], true) => $this->ensureParentEdge($gp, $dp),
                in_array($type, ['father', 'mother', 'parent'], true) => $this->ensureParentEdge($dp, $gp),
                $type === 'spouse' => $this->ensureUnion($gp, $dp),
                default => null, // sponsor/other → no kinship edge
            };
        }
    }

    /** Create a confirmed parent→child edge if one doesn't already exist. */
    public function ensureParentEdge(Person $parent, Person $child): PersonParentLink
    {
        return PersonParentLink::firstOrCreate(
            ['parent_person_id' => $parent->id, 'child_person_id' => $child->id],
            [
                'status' => 'confirmed',
                'created_by_user_id' => $parent->user_id,
                'confirmed_by_user_id' => $parent->user_id,
                'confirmed_at' => now(),
            ],
        );
    }

    /** Create a confirmed union edge (normalised) if one doesn't already exist. */
    public function ensureUnion(Person $a, Person $b): ?PersonUnion
    {
        if ($a->id === $b->id) {
            return null;
        }
        [$low, $high] = $a->id < $b->id ? [$a->id, $b->id] : [$b->id, $a->id];

        return PersonUnion::firstOrCreate(
            ['person_low_id' => $low, 'person_high_id' => $high],
            [
                'status' => 'confirmed',
                'state' => 'married',
                'created_by_user_id' => $a->user_id,
                'confirmed_by_user_id' => $a->user_id,
                'confirmed_at' => now(),
            ],
        );
    }

    // =====================================================================
    // Building edges
    // =====================================================================

    /** Add/attach a parent above a child. */
    public function addParent(Person $child, Person|array $parent, User $actor): PersonParentLink
    {
        return $this->linkParent($this->resolvePerson($parent, $actor), $child, $actor);
    }

    /** Add/attach a child below a parent. */
    public function addChild(Person $parent, Person|array $child, User $actor): PersonParentLink
    {
        return $this->linkParent($parent, $this->resolvePerson($child, $actor), $actor);
    }

    /** Core parent→child edge creator. Idempotent on the (parent,child) pair. */
    public function linkParent(Person $parent, Person $child, User $actor): PersonParentLink
    {
        if ($parent->id === $child->id) {
            throw new \InvalidArgumentException('A person cannot be their own parent.');
        }

        $status = $this->involvesOtherAccount($actor, $parent, $child) ? 'pending' : 'confirmed';

        return PersonParentLink::firstOrCreate(
            ['parent_person_id' => $parent->id, 'child_person_id' => $child->id],
            [
                'status' => $status,
                'created_by_user_id' => $actor->id,
                'confirmed_by_user_id' => $status === 'confirmed' ? $actor->id : null,
                'confirmed_at' => $status === 'confirmed' ? now() : null,
            ],
        );
    }

    /** Add/attach a spouse or partner. */
    public function addSpouse(Person $person, Person|array $spouse, User $actor, array $attrs = []): PersonUnion
    {
        return $this->linkUnion($person, $this->resolvePerson($spouse, $actor), $actor, $attrs);
    }

    /** Core union edge creator. Normalises the pair so (A,B)==(B,A). Idempotent. */
    public function linkUnion(Person $a, Person $b, User $actor, array $attrs = []): PersonUnion
    {
        if ($a->id === $b->id) {
            throw new \InvalidArgumentException('A person cannot be in a union with themselves.');
        }

        [$low, $high] = $a->id < $b->id ? [$a->id, $b->id] : [$b->id, $a->id];
        $status = $this->involvesOtherAccount($actor, $a, $b) ? 'pending' : 'confirmed';

        return PersonUnion::firstOrCreate(
            ['person_low_id' => $low, 'person_high_id' => $high],
            [
                'status' => $status,
                'state' => $attrs['state'] ?? 'married',
                'started_on' => $attrs['started_on'] ?? null,
                'ended_on' => $attrs['ended_on'] ?? null,
                'created_by_user_id' => $actor->id,
                'confirmed_by_user_id' => $status === 'confirmed' ? $actor->id : null,
                'confirmed_at' => $status === 'confirmed' ? now() : null,
            ],
        );
    }

    /**
     * True when the edge touches a real account that is NOT the actor's — i.e.
     * a claim about someone else that needs their confirmation.
     */
    private function involvesOtherAccount(User $actor, Person ...$persons): bool
    {
        foreach ($persons as $p) {
            if ($p->user_id !== null && $p->user_id !== $actor->id) {
                return true;
            }
        }

        return false;
    }

    // =====================================================================
    // Confirming / rejecting (authenticity)
    // =====================================================================

    public function confirm(PersonParentLink|PersonUnion $edge, User $actor): void
    {
        $edge->update([
            'status' => 'confirmed',
            'confirmed_by_user_id' => $actor->id,
            'confirmed_at' => now(),
        ]);
    }

    public function reject(PersonParentLink|PersonUnion $edge, User $actor): void
    {
        $edge->update([
            'status' => 'rejected',
            'confirmed_by_user_id' => $actor->id,
            'confirmed_at' => now(),
        ]);
    }

    // =====================================================================
    // Graph traversal (confirmed edges only)
    // =====================================================================

    /**
     * Map of ancestorPersonId => minimum generational distance, via confirmed
     * parent edges. Includes the person themselves at depth 0.
     *
     * @return array<int,int>
     */
    public function ancestorMap(Person $person, ?int $maxDepth = null): array
    {
        return $this->walk($person->id, 'up', $maxDepth);
    }

    /**
     * Map of descendantPersonId => minimum distance, via confirmed parent edges.
     * Includes the person themselves at depth 0.
     *
     * @return array<int,int>
     */
    public function descendantMap(Person $person, ?int $maxDepth = null): array
    {
        return $this->walk($person->id, 'down', $maxDepth);
    }

    /**
     * Breadth-first walk over confirmed parent edges. Cycle-safe (a "seen" set
     * guarantees each node is recorded once, at its minimum depth).
     *
     * @return array<int,int> personId => depth
     */
    private function walk(int $startId, string $direction, ?int $maxDepth): array
    {
        $depths = [$startId => 0];
        $frontier = [$startId];
        $depth = 0;

        [$fromCol, $toCol] = $direction === 'up'
            ? ['child_person_id', 'parent_person_id']   // step child → parent
            : ['parent_person_id', 'child_person_id'];  // step parent → child

        while ($frontier !== [] && ($maxDepth === null || $depth < $maxDepth)) {
            $next = PersonParentLink::query()
                ->where('status', 'confirmed')
                ->whereIn($fromCol, $frontier)
                ->pluck($toCol)
                ->all();

            $depth++;
            $frontier = [];
            foreach ($next as $id) {
                if (! array_key_exists($id, $depths)) {
                    $depths[$id] = $depth;
                    $frontier[] = $id;
                }
            }
        }

        return $depths;
    }

    /**
     * Confirmed partner ids for a person (across all their unions).
     *
     * @return array<int,int>
     */
    public function partnerIds(Person $person, bool $currentOnly = false): array
    {
        return $person->unions()
            ->when($currentOnly, fn ($c) => $c->filter->isCurrent())
            ->map(fn (PersonUnion $u) => $u->partnerIdOf($person->id))
            ->filter()
            ->values()
            ->all();
    }

    /** Full siblings-and-half-siblings: everyone who shares ≥1 confirmed parent. */
    public function siblingIds(Person $person): array
    {
        $parentIds = $person->parentLinks()->where('status', 'confirmed')->pluck('parent_person_id');
        if ($parentIds->isEmpty()) {
            return [];
        }

        return PersonParentLink::query()
            ->where('status', 'confirmed')
            ->whereIn('parent_person_id', $parentIds)
            ->where('child_person_id', '!=', $person->id)
            ->pluck('child_person_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * All person ids reachable from $root by walking edges in any direction —
     * the family's connected component. Used to authorise which persons a
     * viewer is allowed to focus/see.
     *
     * @param  array<int,string>  $statuses  edge statuses to traverse
     * @return array<int,int>
     */
    public function connectedComponent(Person $root, array $statuses = ['confirmed']): array
    {
        $seen = [$root->id => true];
        $frontier = [$root->id];

        while ($frontier !== []) {
            $up = PersonParentLink::whereIn('status', $statuses)->whereIn('child_person_id', $frontier)->pluck('parent_person_id');
            $down = PersonParentLink::whereIn('status', $statuses)->whereIn('parent_person_id', $frontier)->pluck('child_person_id');
            $uLow = PersonUnion::whereIn('status', $statuses)->whereIn('person_low_id', $frontier)->pluck('person_high_id');
            $uHigh = PersonUnion::whereIn('status', $statuses)->whereIn('person_high_id', $frontier)->pluck('person_low_id');

            $frontier = [];
            foreach ([$up, $down, $uLow, $uHigh] as $coll) {
                foreach ($coll as $id) {
                    if (! isset($seen[$id])) {
                        $seen[$id] = true;
                        $frontier[] = $id;
                    }
                }
            }
        }

        return array_keys($seen);
    }

    // =====================================================================
    // Ego-centric neighborhood — the bounded window a tree view renders
    // =====================================================================

    /**
     * Build the tree window around $focus: a few generations up and down, plus
     * spouses, siblings, and any pending direct relatives — every node labelled
     * relative to $root ("you"). This is what makes an unlimited-depth tree
     * cheap to render: only a bounded slice is ever materialised, and tapping a
     * node re-centres the window on it.
     *
     * @return array{focus:int,root:int,nodes:array<int,array>,parentEdges:array,unions:array}
     */
    public function neighborhood(Person $focus, Person $root, int $up = 2, int $down = 2): array
    {
        // 1. Vertical window over confirmed edges (depth relative to focus).
        $depth = [];
        foreach ($this->ancestorMap($focus, $up) as $id => $d) {
            $depth[$id] = -$d;
        }
        foreach ($this->descendantMap($focus, $down) as $id => $d) {
            $depth[$id] = $d; // focus resolves to 0
        }

        // 2. Siblings of the focus (peers at depth 0).
        foreach ($this->siblingIds($focus) as $sid) {
            $depth[$sid] ??= 0;
        }

        // 3. Spouses of every node so far, so couples sit side by side.
        foreach (array_keys($depth) as $id) {
            if ($person = Person::find($id)) {
                foreach ($this->partnerIds($person) as $pid) {
                    $depth[$pid] ??= $depth[$id];
                }
            }
        }

        // 4. Pending direct relatives of the focus (awaiting confirmation).
        $pending = [];
        foreach (PersonParentLink::where('status', 'pending')
            ->where(fn ($q) => $q->where('child_person_id', $focus->id)
                ->orWhere('parent_person_id', $focus->id))->get() as $l) {
            $otherIsParent = $l->parent_person_id !== $focus->id;
            $otherId = $otherIsParent ? $l->parent_person_id : $l->child_person_id;
            $depth[$otherId] ??= $otherIsParent ? -1 : 1;
            $pending[$otherId] = true;
        }
        foreach (PersonUnion::where('status', 'pending')
            ->where(fn ($q) => $q->where('person_low_id', $focus->id)
                ->orWhere('person_high_id', $focus->id))->get() as $u) {
            if ($otherId = $u->partnerIdOf($focus->id)) {
                $depth[$otherId] ??= 0;
                $pending[$otherId] = true;
            }
        }

        $ids = array_keys($depth);
        $persons = Person::whereIn('id', $ids)->with('user:id,profile_picture,updated_at')->get()->keyBy('id');

        $nodes = [];
        foreach ($ids as $id) {
            if (! $p = $persons->get($id)) {
                continue;
            }
            $nodes[] = [
                'id' => $p->id,
                'name' => $p->full_name,
                'gender' => $p->gender,
                'depth' => $depth[$id],
                'deceased' => (bool) $p->is_deceased,
                'is_focus' => $p->id === $focus->id,
                'is_root' => $p->id === $root->id,
                'account' => $p->user_id !== null,
                'pending' => isset($pending[$id]),
                'label' => $p->id === $root->id ? 'you' : $this->relationshipLabel($root, $p),
                'photo' => $p->photo,
                'user_photo' => $p->user?->profile_picture,
                'user_v' => $p->user?->updated_at?->timestamp,
                'birth_year' => $p->birth_date?->format('Y'),
                'death_year' => $p->death_date?->format('Y'),
            ];
        }

        $parentEdges = PersonParentLink::whereIn('parent_person_id', $ids)
            ->whereIn('child_person_id', $ids)
            ->whereIn('status', ['confirmed', 'pending'])
            ->get()
            ->map(fn ($l) => [
                'id' => $l->id,
                'type' => 'parent',
                'p' => $l->parent_person_id,
                'c' => $l->child_person_id,
                'status' => $l->status,
                'created_by' => $l->created_by_user_id,
            ])
            ->all();

        $unions = PersonUnion::whereIn('person_low_id', $ids)
            ->whereIn('person_high_id', $ids)
            ->whereIn('status', ['confirmed', 'pending'])
            ->get()
            ->map(fn ($u) => [
                'id' => $u->id,
                'type' => 'union',
                'a' => $u->person_low_id,
                'b' => $u->person_high_id,
                'state' => $u->state,
                'status' => $u->status,
                'created_by' => $u->created_by_user_id,
            ])
            ->all();

        return [
            'focus' => $focus->id,
            'root' => $root->id,
            'nodes' => $nodes,
            'parentEdges' => $parentEdges,
            'unions' => $unions,
        ];
    }

    // =====================================================================
    // Relationship labelling — "what is TO to FROM?"
    // =====================================================================

    /**
     * Human kinship term for `$to` from `$from`'s point of view
     * (e.g. "grandfather", "niece", "1st cousin", "father-in-law").
     * Returns null when no relationship can be derived.
     */
    public function relationshipLabel(Person $from, Person $to): ?string
    {
        if ($from->id === $to->id) {
            return 'self';
        }

        // 1. Blood relationship via the nearest common ancestor.
        if ($blood = $this->bloodLabel($from, $to)) {
            return $blood;
        }

        // 2. Spouse / partner.
        if (in_array($to->id, $this->partnerIds($from), true)) {
            return $this->term($to->gender, 'husband', 'wife', 'spouse');
        }

        // 3. In-laws.
        return $this->inLawLabel($from, $to);
    }

    /** Blood label via nearest common ancestor, or null if unrelated by blood. */
    private function bloodLabel(Person $from, Person $to): ?string
    {
        $fromAnc = $this->ancestorMap($from);
        $toAnc = $this->ancestorMap($to);

        $bestSum = PHP_INT_MAX;
        $d1 = $d2 = null;
        foreach ($fromAnc as $id => $df) {
            if (isset($toAnc[$id]) && ($df + $toAnc[$id]) < $bestSum) {
                $bestSum = $df + $toAnc[$id];
                $d1 = $df;         // from → common ancestor
                $d2 = $toAnc[$id]; // to   → common ancestor
            }
        }

        if ($d1 === null) {
            return null; // no common ancestor
        }

        return $this->labelFromDistances($d1, $d2, $to->gender);
    }

    /**
     * Turn a (d1, d2) pair — the distances from `from` and `to` up to their
     * nearest common ancestor — into a gendered kinship term for `to`.
     */
    private function labelFromDistances(int $d1, int $d2, ?string $gender): string
    {
        // Direct line down: to is a descendant of from.
        if ($d1 === 0) {
            return $this->descendantTerm($d2, $gender);
        }
        // Direct line up: to is an ancestor of from.
        if ($d2 === 0) {
            return $this->ancestorTerm($d1, $gender);
        }
        // Siblings: both one step below the common ancestor.
        if ($d1 === 1 && $d2 === 1) {
            return $this->term($gender, 'brother', 'sister', 'sibling');
        }
        // to is a descendant of from's sibling → niece/nephew line.
        if ($d1 === 1) {
            return $this->nieceNephewTerm($d2, $gender);
        }
        // to is a sibling of one of from's ancestors → uncle/aunt line.
        if ($d2 === 1) {
            return $this->uncleAuntTerm($d1, $gender);
        }

        // Otherwise cousins.
        return $this->cousinTerm($d1, $d2);
    }

    /** Common in-law relationships. */
    private function inLawLabel(Person $from, Person $to): ?string
    {
        $fromPartnerIds = $this->partnerIds($from);
        $toPartnerIds = $this->partnerIds($to);

        // to is the parent of from's spouse → parent-in-law.
        // to is a sibling of from's spouse → sibling-in-law.
        foreach ($fromPartnerIds as $spouseId) {
            $spouse = Person::find($spouseId);
            if (! $spouse) {
                continue;
            }
            $rel = $this->bloodLabel($spouse, $to);
            if ($rel !== null) {
                if ($this->isTerm($rel, ['father', 'mother', 'parent'])) {
                    return $this->term($to->gender, 'father-in-law', 'mother-in-law', 'parent-in-law');
                }
                if ($this->isTerm($rel, ['brother', 'sister', 'sibling'])) {
                    return $this->term($to->gender, 'brother-in-law', 'sister-in-law', 'sibling-in-law');
                }
            }
        }

        // to is the spouse of one of from's blood relatives.
        foreach ($toPartnerIds as $partnerId) {
            $partner = Person::find($partnerId);
            if (! $partner) {
                continue;
            }
            $rel = $this->bloodLabel($from, $partner);
            if ($rel !== null) {
                if ($this->isTerm($rel, ['son', 'daughter', 'child'])) {
                    return $this->term($to->gender, 'son-in-law', 'daughter-in-law', 'child-in-law');
                }
                if ($this->isTerm($rel, ['brother', 'sister', 'sibling'])) {
                    return $this->term($to->gender, 'brother-in-law', 'sister-in-law', 'sibling-in-law');
                }
            }
        }

        return null;
    }

    // =====================================================================
    // Merge — collapse a duplicate person into a canonical one
    // =====================================================================

    /**
     * Merge $duplicate into $keep: re-point every edge to $keep, drop resulting
     * self/duplicate edges, adopt the account link if $keep lacks one, then
     * soft-delete $duplicate. Runs in a transaction.
     */
    public function merge(Person $keep, Person $duplicate): Person
    {
        if ($keep->id === $duplicate->id) {
            return $keep;
        }

        DB::transaction(function () use ($keep, $duplicate) {
            // Parent edges where duplicate is the CHILD.
            foreach (PersonParentLink::where('child_person_id', $duplicate->id)->get() as $link) {
                $this->repointParentLink($link, $keep->id, isChild: true);
            }
            // Parent edges where duplicate is the PARENT.
            foreach (PersonParentLink::where('parent_person_id', $duplicate->id)->get() as $link) {
                $this->repointParentLink($link, $keep->id, isChild: false);
            }
            // Union edges touching duplicate.
            foreach (PersonUnion::where('person_low_id', $duplicate->id)
                ->orWhere('person_high_id', $duplicate->id)->get() as $union) {
                $this->repointUnion($union, $duplicate->id, $keep->id);
            }

            // Adopt the account + fill any gaps on the surviving node.
            $fill = [];
            if ($keep->user_id === null && $duplicate->user_id !== null) {
                $fill['user_id'] = $duplicate->user_id;
                $duplicate->update(['user_id' => null]); // release unique before reassign
            }
            foreach (['gender', 'birth_date', 'death_date', 'photo', 'notes'] as $attr) {
                if (empty($keep->{$attr}) && ! empty($duplicate->{$attr})) {
                    $fill[$attr] = $duplicate->{$attr};
                }
            }
            if ($fill !== []) {
                $keep->update($fill);
            }

            $duplicate->delete(); // soft delete
        });

        return $keep->refresh();
    }

    private function repointParentLink(PersonParentLink $link, int $keepId, bool $isChild): void
    {
        $parent = $isChild ? $link->parent_person_id : $keepId;
        $child = $isChild ? $keepId : $link->child_person_id;

        // Self-loop or an equivalent edge already exists → drop this one.
        if ($parent === $child
            || PersonParentLink::where('parent_person_id', $parent)
                ->where('child_person_id', $child)
                ->where('id', '!=', $link->id)->exists()) {
            $link->delete();

            return;
        }

        $link->update($isChild ? ['child_person_id' => $keepId] : ['parent_person_id' => $keepId]);
    }

    private function repointUnion(PersonUnion $union, int $dupeId, int $keepId): void
    {
        $otherId = $union->partnerIdOf($dupeId);

        // Union between the two nodes being merged → drop it.
        if ($otherId === $keepId || $otherId === null) {
            $union->delete();

            return;
        }

        [$low, $high] = $keepId < $otherId ? [$keepId, $otherId] : [$otherId, $keepId];

        if (PersonUnion::where('person_low_id', $low)
            ->where('person_high_id', $high)
            ->where('id', '!=', $union->id)->exists()) {
            $union->delete();

            return;
        }

        $union->update(['person_low_id' => $low, 'person_high_id' => $high]);
    }

    // =====================================================================
    // Term builders
    // =====================================================================

    private function ancestorTerm(int $depth, ?string $gender): string
    {
        if ($depth === 1) {
            return $this->term($gender, 'father', 'mother', 'parent');
        }
        $grand = $this->term($gender, 'grandfather', 'grandmother', 'grandparent');

        return str_repeat('great-', $depth - 2).$grand;
    }

    private function descendantTerm(int $depth, ?string $gender): string
    {
        if ($depth === 1) {
            return $this->term($gender, 'son', 'daughter', 'child');
        }
        $grand = $this->term($gender, 'grandson', 'granddaughter', 'grandchild');

        return str_repeat('great-', $depth - 2).$grand;
    }

    private function uncleAuntTerm(int $d1, ?string $gender): string
    {
        // d1 == 2 → uncle/aunt; each extra ancestor step adds a "great-".
        return str_repeat('great-', $d1 - 2).$this->term($gender, 'uncle', 'aunt', 'uncle/aunt');
    }

    private function nieceNephewTerm(int $d2, ?string $gender): string
    {
        // d2 == 2 → niece/nephew; each extra descendant step adds a "great-".
        return str_repeat('great-', $d2 - 2).$this->term($gender, 'nephew', 'niece', 'niece/nephew');
    }

    private function cousinTerm(int $d1, int $d2): string
    {
        $degree = min($d1, $d2) - 1;      // 1st, 2nd, 3rd cousin…
        $removed = abs($d1 - $d2);         // "once removed" etc.

        $label = $this->ordinal($degree).' cousin';
        if ($removed === 1) {
            $label .= ' once removed';
        } elseif ($removed === 2) {
            $label .= ' twice removed';
        } elseif ($removed > 2) {
            $label .= " {$removed}x removed";
        }

        return $label;
    }

    // =====================================================================
    // Small helpers
    // =====================================================================

    /** Pick the male/female/neutral term by gender ('m' | 'f' | null). */
    private function term(?string $gender, string $male, string $female, string $neutral): string
    {
        return match ($gender) {
            'm' => $male,
            'f' => $female,
            default => $neutral,
        };
    }

    /** Does $label match any of the gendered variants in $family? */
    private function isTerm(string $label, array $family): bool
    {
        return in_array($label, $family, true);
    }

    private function ordinal(int $n): string
    {
        $suffix = match (true) {
            $n % 100 >= 11 && $n % 100 <= 13 => 'th',
            $n % 10 === 1 => 'st',
            $n % 10 === 2 => 'nd',
            $n % 10 === 3 => 'rd',
            default => 'th',
        };

        return $n.$suffix;
    }
}
