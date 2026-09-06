<?php

namespace Tests\Feature\Events;

use App\Events\Support\EntryEditor;
use App\Members\Models\User;
use App\Members\Models\UserRelationship;
use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * WHO may change the PERSON behind an entry — their date of birth and gender.
 *
 * These are not entry facts. A weight belongs to the entry; a date of birth
 * belongs to the human, decides their age group, and carries the safeguarding
 * rules with it. So the editor's question is not "may this organiser edit this
 * event" — it is "does this actor hold authority over this PERSON", and the
 * answer must be the SAME one the rest of the platform gives
 * (MemberController::authorizeMemberWrite): super-admin → self → confirmed
 * guardian, plus a person nobody has claimed yet.
 *
 * The rule used to be "any organiser, but only for an unclaimed person", which
 * locked out the two people who could already make the identical edit on the
 * member's own profile screen — so it protected nobody and only moved the work
 * to another screen.
 *
 * ⚠️ `php artisan config:clear` before running — see CLAUDE.md.
 */
class EntryIdentityEditingTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: ClubEvent, 2: ClubEventRegistration, 3: User} */
    private function scenario(bool $unclaimed = false): array
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner, ['country' => 'BH', 'currency' => 'BHD']);
        $owner->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        $event = ClubEvent::create([
            'tenant_id' => $club->id,
            'created_by' => $owner->id,
            'title' => 'Gulf Open',
            'event_type' => 'championship',
            'sport' => 'bjj',
            'scope' => 'internal',
            'date' => now()->addWeeks(2)->toDateString(),
            'end_date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'active',
            'is_archived' => false,
        ]);

        $athlete = $this->createUser(['is_unclaimed' => $unclaimed]);

        $entry = ClubEventRegistration::create([
            'event_id' => $event->id,
            'user_id' => $athlete->id,
            'tenant_id' => $club->id,
            'status' => 'registered',
        ]);

        return [$owner, $event, $entry, $athlete];
    }

    private function fields(ClubEvent $event, ClubEventRegistration $entry, User $actor): array
    {
        return app(EntryEditor::class)->permissions($event, $entry, $actor)['fields'];
    }

    public function test_a_super_admin_may_correct_a_claimed_members_identity(): void
    {
        [, $event, $entry] = $this->scenario();

        $super = $this->createUser();
        $this->makeSuperAdmin($super);

        $fields = $this->fields($event, $entry, $super->fresh());

        $this->assertTrue($fields['birthdate']['editable'], 'a super-admin may fix a date of birth');
        $this->assertTrue($fields['gender']['editable']);
    }

    /** The case the lock exists for, and it still holds. */
    public function test_an_organiser_with_no_claim_on_the_person_may_not(): void
    {
        [$owner, $event, $entry] = $this->scenario();

        $fields = $this->fields($event, $entry, $owner);

        $this->assertFalse($fields['birthdate']['editable']);
        $this->assertFalse($fields['gender']['editable']);
        $this->assertNotNull($fields['birthdate']['reason'], 'a locked field must say why');

        // …and the things that ARE the entry stay editable for them.
        $this->assertTrue($fields['weight']['editable']);
        $this->assertTrue($fields['name']['editable']);
    }

    public function test_a_confirmed_guardian_may(): void
    {
        [$owner, $event, $entry, $athlete] = $this->scenario();

        UserRelationship::create([
            'guardian_user_id' => $owner->id,
            'dependent_user_id' => $athlete->id,
            'relationship_type' => 'parent',
        ]);

        $fields = $this->fields($event, $entry, $owner->fresh());

        $this->assertTrue($fields['birthdate']['editable']);
        $this->assertTrue($fields['gender']['editable']);
    }

    /** Unchanged: a person nobody has claimed has no identity to protect yet. */
    public function test_an_unclaimed_person_stays_editable_by_the_organiser(): void
    {
        [$owner, $event, $entry] = $this->scenario(unclaimed: true);

        $fields = $this->fields($event, $entry, $owner);

        $this->assertTrue($fields['birthdate']['editable']);
        $this->assertTrue($fields['gender']['editable']);
    }

    /** The athlete themself, always. */
    public function test_the_athlete_may_always_correct_their_own(): void
    {
        [, $event, $entry, $athlete] = $this->scenario();

        $fields = $this->fields($event, $entry, $athlete);

        $this->assertTrue($fields['birthdate']['editable']);
        $this->assertTrue($fields['gender']['editable']);
    }

    /* ── The SAVE agrees with the sheet ─────────────────────────────────────
       The field states above are what the sheet renders; these are what the
       server actually does with a POST. A screen that offers a field the
       endpoint refuses is the worse bug of the two, and so is the reverse. */

    public function test_a_super_admin_save_actually_changes_the_person(): void
    {
        [, $event, $entry, $athlete] = $this->scenario();

        $super = $this->createUser();
        $this->makeSuperAdmin($super);

        $result = app(EntryEditor::class)->apply(
            $event, $entry, ['birthdate' => '1990-04-17', 'gender' => 'Female'], $super->fresh()
        );

        $this->assertTrue($result['ok'], $result['message'] ?? '');
        $this->assertSame('1990-04-17', $athlete->fresh()->birthdate?->toDateString());
        $this->assertSame('Female', $athlete->fresh()->gender);
    }

    public function test_an_organiser_save_is_refused_and_changes_nothing(): void
    {
        [$owner, $event, $entry, $athlete] = $this->scenario();

        $before = $athlete->birthdate?->toDateString();

        $result = app(EntryEditor::class)->apply(
            $event, $entry, ['birthdate' => '1990-04-17'], $owner
        );

        $this->assertFalse($result['ok']);
        $this->assertSame($before, $athlete->fresh()->birthdate?->toDateString());
    }
}
