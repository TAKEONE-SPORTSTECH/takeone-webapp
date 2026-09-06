<?php

namespace App\EventLab\Services;

use App\Members\Models\User;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\EventCategory;
use App\Models\EventMatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Makes a sandbox twin of a real event.
 *
 * The twin is a genuine `club_events` row, which is the entire point: every
 * screen the platform has for an event — the poster, the draw, the participant
 * list, the entry flow, the organiser console — works on it without a line of
 * those screens being changed, because there is nothing special about it to
 * notice.
 *
 * Two rules make that safe.
 *
 * **It only ever ADDS.** The source event is read and never written. Not one
 * existing row is modified by anything in this class.
 *
 * **Its competitors are nobody.** A registration needs a `user_id` (NOT NULL)
 * and the draw needs registrations, so the twin cannot run on thin air — but
 * pointing it at the 29 real members would put a sandbox event inside 29 real
 * accounts. So each entrant is copied to a fresh UNCLAIMED person: the flag the
 * platform already uses for a competitor who is not on it (`is_unclaimed`,
 * `is_discoverable = false`), which every listing that means "members" leaves
 * out. Their names and faces come across so the screens look like the real
 * thing; nothing else about them exists.
 *
 * Deliberately NOT copied, and why:
 *  - `event_recordings`, `event_camera_clips` — real footage. A second row
 *    pointing at the same file means deleting the sandbox could reach it.
 *  - `event_cameras`, `event_screen_media`, screen pairings — a device is
 *    enrolled against one event; a copied pairing would fight the real one for
 *    the same hardware.
 *  - `event_entry_claims` — single-use secrets. Copying a hash hands out a
 *    second door to the same claim.
 *  - `event_notifications_sent` — the ledger of who has already been told.
 *    Copying it is how a sandbox re-notifies real people.
 *  - `event_public_entries` — a queue of real strangers asking to join a real
 *    event. It belongs to that event, not to a copy of it.
 */
class EventCloner
{
    /** Marks a twin, and is how a purge finds every row it made. */
    public const TITLE_PREFIX = 'SANDBOX — ';

    /** real user id => the sandbox person standing in for them, this clone only. */
    private array $people = [];

    /**
     * Copy an event, everything its screens read, and nothing else.
     */
    public function clone(ClubEvent $source, User $actor): ClubEvent
    {
        $this->people = [];

        return DB::transaction(function () use ($source, $actor) {
            $clone = $this->cloneEvent($source, $actor);

            $categoryMap = $this->cloneCategories($source, $clone);
            $registrationMap = $this->cloneRegistrations($source, $clone, $categoryMap, $actor);
            $matchMap = $this->cloneMatches($source, $clone, $categoryMap, $registrationMap);

            // What HAPPENED on the mat, and who was still queuing to get on it.
            // Without these a twin is a draw that has never been run: no scoring
            // history, no timeline, an empty claims list and an empty request
            // queue — which is exactly the half of the flow worth rebuilding.
            $this->cloneOfficiatingLog($source, $clone, $matchMap, $actor);
            $this->cloneMatStates($source, $clone, $matchMap);
            $this->cloneClaims($source, $clone, $registrationMap, $actor);
            $this->clonePublicEntries($source, $clone, $registrationMap, $actor);

            return $clone->fresh();
        });
    }

    /**
     * The event row itself.
     *
     * `replicate()` rather than reading attributes by hand — the project has
     * been bitten before by a copy that silently dropped a column added since it
     * was written. What follows is the short list of things a twin must NOT
     * inherit.
     */
    private function cloneEvent(ClubEvent $source, User $actor): ClubEvent
    {
        $clone = $source->replicate();

        $clone->uuid = null;                       // booted() mints a fresh one
        $clone->title = self::TITLE_PREFIX.$source->title;

        // Never notify anybody. This is the column that fans an event out to
        // every member in a country.
        $clone->notify_countries = null;

        // A twin has not happened yet, whatever the original has been through.
        $clone->started_at = null;
        $clone->started_by = null;
        $clone->start_overridden = false;
        $clone->results = null;
        $clone->is_archived = false;               // PublicEvent::isPublic() needs this

        // Reachable by link, which is what makes it testable.
        $clone->entry_mode = 'public';

        $clone->created_by = $actor->id;
        $clone->save();

        return $clone;
    }

    /** @return array<int,int> old category id => new category id */
    private function cloneCategories(ClubEvent $source, ClubEvent $clone): array
    {
        $map = [];

        foreach ($source->categories()->get() as $category) {
            $copy = $category->replicate();
            $copy->event_id = $clone->id;
            $copy->save();

            $map[$category->id] = $copy->id;
        }

        return $map;
    }

    /**
     * Every entrant, as a person who is not on the platform.
     *
     * @param  array<int,int>  $categoryMap
     * @return array<int,int> old registration id => new registration id
     */
    private function cloneRegistrations(ClubEvent $source, ClubEvent $clone, array $categoryMap, User $actor): array
    {
        $map = [];

        $registrations = $source->registrations()
            ->with('user:id,full_name,name,gender,birthdate,nationality,profile_picture,profile_picture_is_public')
            ->get();

        foreach ($registrations as $registration) {
            $person = $this->unclaimedTwinOf($registration->user);

            if ($registration->user_id) {
                $this->people[$registration->user_id] = $person->id;
            }

            $copy = $registration->replicate();
            $copy->event_id = $clone->id;
            $copy->user_id = $person->id;
            $copy->category_id = $categoryMap[$registration->category_id] ?? null;
            $copy->entered_by = $actor->id;

            // `photo` comes across so the board and the entrant list look like
            // the real thing — but it is a PATH, and the file behind it belongs
            // to the real entry. Nothing here may ever delete it, which is why
            // purge() detaches these paths before it removes a row. See the
            // note there: measured on the first twin, 21 of 28 real photos
            // would otherwise have gone with it.

            // Money and paperwork do not come across: a proof-of-payment file
            // belongs to the real subscription that paid for it, and a second
            // row naming the same file is exactly the shared-reference bug that
            // has already cost this platform six member photos.
            $copy->payment_proof = null;
            $copy->paid_by = null;
            $copy->paid_at = null;

            $copy->save();

            $map[$registration->id] = $copy->id;
        }

        return $map;
    }

    /**
     * A person for the sandbox, modelled on a real one.
     *
     * The face is a shared reference on purpose — read-only, and never deleted
     * from here (see the note on `payment_proof` above; `EntryPhoto` guards the
     * delete side). Everything that makes an account an account is absent: no
     * email, no password, not discoverable.
     */
    private function unclaimedTwinOf(?User $original): User
    {
        $name = $original?->full_name ?: ($original?->name ?: 'Sandbox entrant');

        return User::create([
            'full_name' => $name,
            'name' => $name,
            'email' => null,
            'password' => null,
            'is_unclaimed' => true,
            'is_discoverable' => false,
            'gender' => $original?->gender,
            'birthdate' => $original?->birthdate,
            'nationality' => $original?->nationality,
            'profile_picture' => $original?->profile_picture,
            // Their choice about their own face travels with it. The picture is
            // the real athlete's, so a twin that defaulted this to public would
            // publish a face its owner had kept private — on a copy they have
            // never heard of. Absent an original, nothing is public.
            'profile_picture_is_public' => (bool) ($original?->profile_picture_is_public ?? false),
        ]);
    }

    /**
     * The draw, with both sides re-pointed at the twin's own competitors.
     *
     * @param  array<int,int>  $categoryMap
     * @param  array<int,int>  $registrationMap
     */
    private function cloneMatches(ClubEvent $source, ClubEvent $clone, array $categoryMap, array $registrationMap): array
    {
        $map = [];

        foreach ($source->matches()->get() as $match) {
            $copy = $match->replicate();
            $copy->event_id = $clone->id;
            $copy->category_id = $categoryMap[$match->category_id] ?? $match->category_id;
            $copy->a_competitor_id = $registrationMap[$match->a_competitor_id] ?? null;
            $copy->b_competitor_id = $registrationMap[$match->b_competitor_id] ?? null;
            $copy->save();

            $map[$match->id] = $copy->id;
        }

        return $map;
    }

    /**
     * The officiating log — every point, penalty and clock nudge.
     *
     * Written straight through the query builder rather than through a model:
     * these tables are an append-only record with no Eloquent behaviour to
     * honour, and a bout can carry a couple of hundred rows.
     *
     * `operator_id` is re-pointed at whoever made the twin. It names the
     * official who actually worked that bout, and a sandbox row claiming their
     * name would put fictional work in a real person's history.
     *
     * @param  array<int,int>  $matchMap
     */
    private function cloneOfficiatingLog(ClubEvent $source, ClubEvent $clone, array $matchMap, User $actor): void
    {
        foreach (['event_match_events', 'bjj_match_events'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            // id => new id, so `reverses_id` can point at the copy of the row it
            // undoes. Rows are taken in id order, and a reversal is always
            // written after what it reverses, so the target is always already in
            // the map.
            $idMap = [];

            foreach (DB::table($table)->where('event_id', $source->id)->orderBy('id')->get() as $row) {
                $data = (array) $row;
                unset($data['id']);

                $data['event_id'] = $clone->id;
                $data['match_id'] = $matchMap[$row->match_id] ?? null;

                if (array_key_exists('reverses_id', $data)) {
                    $data['reverses_id'] = $idMap[$row->reverses_id] ?? null;
                }

                if (array_key_exists('operator_id', $data)) {
                    $data['operator_id'] = $actor->id;
                }

                $idMap[$row->id] = DB::table($table)->insertGetId($data);
            }
        }
    }

    /**
     * What each mat was showing when the event stopped.
     *
     * @param  array<int,int>  $matchMap
     */
    private function cloneMatStates(ClubEvent $source, ClubEvent $clone, array $matchMap): void
    {
        foreach (['bjj_mat_states'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (DB::table($table)->where('event_id', $source->id)->get() as $row) {
                $data = (array) $row;
                unset($data['id']);

                $data['event_id'] = $clone->id;
                $data['match_id'] = $matchMap[$row->match_id] ?? null;

                DB::table($table)->insert($data);
            }
        }
    }

    /**
     * The claim links — as ROWS, never as the same secret twice.
     *
     * A claim link is a credential: a public uuid plus a secret half, stored
     * only as a hash, single-use and bound to one entry. Copying the hash across
     * would mean one link opening two entries — the real one and the twin's —
     * which is the precise thing single-use is for. So the row comes across for
     * its shape (who is owed a link, what state it is in, when it expires) with a
     * hash of a fresh random secret NOBODY holds. The link is dead on arrival by
     * design; the console re-issues one when the flow needs a live link to test.
     *
     * @param  array<int,int>  $registrationMap
     */
    private function cloneClaims(ClubEvent $source, ClubEvent $clone, array $registrationMap, User $actor): void
    {
        if (! Schema::hasTable('event_entry_claims')) {
            return;
        }

        foreach (DB::table('event_entry_claims')->where('event_id', $source->id)->get() as $row) {
            $data = (array) $row;
            unset($data['id']);

            $data['uuid'] = (string) Str::uuid();
            $data['token_hash'] = hash('sha256', Str::random(64));
            $data['event_id'] = $clone->id;
            $data['registration_id'] = $registrationMap[$row->registration_id] ?? null;
            $data['user_id'] = $this->twinUserId($clone, $row->user_id);
            $data['created_by'] = $actor->id;

            DB::table('event_entry_claims')->insert($data);
        }
    }

    /**
     * The queue of strangers asking to be let in.
     *
     * Their `ip` does not come across. It is the one field here that is personal
     * data about a real person and serves no purpose in a copy — a request queue
     * tests the same either way.
     *
     * @param  array<int,int>  $registrationMap
     */
    private function clonePublicEntries(ClubEvent $source, ClubEvent $clone, array $registrationMap, User $actor): void
    {
        if (! Schema::hasTable('event_public_entries')) {
            return;
        }

        foreach (DB::table('event_public_entries')->where('event_id', $source->id)->get() as $row) {
            $data = (array) $row;
            unset($data['id']);

            $data['uuid'] = (string) Str::uuid();
            $data['event_id'] = $clone->id;
            $data['registration_id'] = $registrationMap[$row->registration_id] ?? null;
            $data['user_id'] = $this->twinUserId($clone, $row->user_id);
            $data['decided_by'] = $row->decided_by ? $actor->id : null;
            $data['ip'] = null;

            DB::table('event_public_entries')->insert($data);
        }
    }

    /**
     * The twin's own person for a real user id.
     *
     * A registration already has one. A pending REQUEST does not — nobody has
     * been entered yet — so one is made here rather than letting the row point
     * at the real account it names.
     */
    private function twinUserId(ClubEvent $clone, ?int $realUserId): ?int
    {
        if (! $realUserId) {
            return null;
        }

        if (isset($this->people[$realUserId])) {
            return $this->people[$realUserId];
        }

        $twin = $this->unclaimedTwinOf(User::find($realUserId));

        return $this->people[$realUserId] = $twin->id;
    }

    /**
     * Remove a twin and everything it brought with it.
     *
     * Its entrants are deleted too — they exist for nothing else. A person who
     * has since been claimed (somebody signed into them) is left alone: at that
     * point they are a member, whatever they started as.
     */
    public function purge(ClubEvent $clone): array
    {
        return DB::transaction(function () use ($clone) {
            // Every person this twin points at, not just its competitors: a
            // pending REQUEST names somebody who was never entered, and a
            // sandbox person made for one of those rows is referenced nowhere
            // else. Collecting only from the registrations left two of them
            // behind on the first run.
            $userIds = ClubEventRegistration::where('event_id', $clone->id)
                ->pluck('user_id');

            foreach (['event_public_entries', 'event_entry_claims'] as $table) {
                if (Schema::hasTable($table)) {
                    $userIds = $userIds->concat(
                        DB::table($table)->where('event_id', $clone->id)->pluck('user_id')
                    );
                }
            }

            $userIds = $userIds->filter()->unique()->all();

            $matches = EventMatch::where('event_id', $clone->id)->count();

            // Let go of the photographs BEFORE letting go of the rows.
            //
            // A twin's `photo` is the real entry's file, named a second time.
            // `ClubEventRegistration::deleting` discards that file, and
            // `EntryPhoto` only spares a path some user's profile claims — an
            // organiser-taken competitor photo is claimed by nobody, so the
            // delete would take the real event's picture with it. Clearing the
            // column touches no file and leaves the hook nothing to discard.
            ClubEventRegistration::where('event_id', $clone->id)->update(['photo' => null]);

            // The crests of clubs written down on this event. Files first, and
            // only the ones this event owns: an INVITED club's logo is the
            // club's own, and deleting it here would take it off their profile.
            if (Schema::hasTable('sandbox_event_clubs')) {
                foreach (DB::table('sandbox_event_clubs')
                    ->where('event_id', $clone->id)
                    ->whereNull('tenant_id')
                    ->whereNotNull('logo')
                    ->pluck('logo') as $logo) {
                    rescue(fn () => \Illuminate\Support\Facades\Storage::disk('public')->delete($logo), null, false);
                }
            }

            // Everything the twin's own tables hold. Scoped by event_id, so a
            // purge can only ever reach rows this twin made.
            foreach (['event_match_events', 'bjj_match_events', 'bjj_mat_states',
                'event_entry_claims', 'event_public_entries', 'sandbox_event_clubs'] as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->where('event_id', $clone->id)->delete();
                }
            }

            // Model-by-model so the file-deleting hooks run.
            ClubEventRegistration::where('event_id', $clone->id)->get()->each->delete();
            EventMatch::where('event_id', $clone->id)->get()->each->delete();
            EventCategory::where('event_id', $clone->id)->get()->each->delete();

            // Unclaimed, and named by nothing outside this twin. The second
            // half matters: `is_unclaimed` is also true of real people a coach
            // entered by name into a real event, and one of them appearing in a
            // twin's queue must not make them deletable.
            $people = User::whereIn('id', $userIds)
                ->where('is_unclaimed', true)
                ->whereDoesntHave('eventRegistrations', fn ($q) => $q->where('event_id', '!=', $clone->id))
                ->get();

            $peopleCount = $people->count();
            $people->each->delete();

            $title = $clone->title;
            $clone->delete();

            return [
                'title' => $title,
                'matches' => $matches,
                'people' => $peopleCount,
            ];
        });
    }
}
