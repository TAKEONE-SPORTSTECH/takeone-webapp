<?php

namespace App\Duplicates\Support;

use App\Clubs\Models\Tenant;
use App\Duplicates\Models\DuplicateFinding;
use App\Events\Models\ClubEvent;
use App\Events\Models\EventClub;
use App\Members\Models\User;

/**
 * One finding as a screen or an integration should see it: what it is about
 * (public ids and names only — never an internal id, a phone or an email),
 * why it was raised, and what may be done about it. Shared by the super-admin
 * page and the MCP tool, so the two can never describe a finding differently.
 */
class FindingPresenter
{
    public function present(DuplicateFinding $f): array
    {
        return [
            'id' => $f->uuid,
            'kind' => $f->kind,
            'strength' => $f->strength,
            'status' => $f->status,
            'reasons' => array_values((array) $f->reasons),
            'subjects' => $this->subjects($f),
            'fix' => $f->fix,
            // What the page may OFFER. The endpoints re-check every one of these.
            'can_fix' => $f->isOpen() && ($f->isCertain() ? $f->kind !== DuplicateFinding::KIND_CLUB : ($f->kind === DuplicateFinding::KIND_EVENT_CLUB && (bool) $f->b_id)),
            'merge_on_members' => $f->isOpen() && $f->kind === DuplicateFinding::KIND_PERSON,
            'first_seen' => $f->first_seen_at?->toIso8601String(),
            'last_seen' => $f->last_seen_at?->toIso8601String(),
            'resolved' => $f->resolved_at?->toIso8601String(),
        ];
    }

    private function subjects(DuplicateFinding $f): array
    {
        $person = function ($id) {
            $u = $id ? User::withTrashed()->find($id) : null;

            return $u ? [
                'uuid' => $u->uuid, 'name' => $u->full_name ?: $u->name,
                'unclaimed' => (bool) $u->is_unclaimed, 'removed' => $u->trashed(),
                'birth_year' => $u->birthdate?->format('Y'),
                'url' => $u->trashed() ? null : rescue(fn () => route('member.show', $u->uuid), null, report: false),
            ] : null;
        };
        $club = function ($id) {
            $t = $id ? Tenant::withTrashed()->find($id) : null;

            return $t ? [
                'slug' => $t->slug, 'name' => $t->club_name, 'removed' => $t->trashed(),
                'logo' => $t->logo ? file_url($t->logo) : null,
                'url' => $t->trashed() ? null : rescue(fn () => route('admin.platform.clubs.edit', $t->slug), null, report: false),
            ] : null;
        };
        $event = function ($id) {
            $e = $id ? ClubEvent::find($id) : null;

            return $e ? ['uuid' => $e->uuid, 'title' => $e->title,
                'url' => rescue(fn () => route('me.events.show', $e->uuid), null, report: false)] : null;
        };

        return match ($f->kind) {
            DuplicateFinding::KIND_PERSON => ['a' => $person($f->a_id), 'b' => $person($f->b_id)],
            DuplicateFinding::KIND_CLUB => ['a' => $club($f->a_id), 'b' => $club($f->b_id)],
            DuplicateFinding::KIND_EVENT_CLUB => [
                'event' => $event($f->event_id),
                'typed_club' => ($ec = EventClub::find($f->a_id)) ? ['uuid' => $ec->uuid, 'name' => $ec->name] : null,
                'platform_club' => $club($f->b_id),
            ],
            DuplicateFinding::KIND_ENTRY => ['event' => $event($f->event_id), 'person' => $person($f->a_id)],
            default => [],
        };
    }
}
