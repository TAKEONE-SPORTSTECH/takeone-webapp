<?php

namespace Tests\Feature\Contracts;

use App\Models\ClubEvent;
use App\Models\ClubEventRegistration;
use App\Models\ClubMemberSubscription;
use App\Models\EventCategory;
use App\Models\EventMatch;
use App\Models\EventRecording;
use App\Models\HealthRecord;
use App\Models\MediaFile;
use App\Clubs\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Shared fixtures for the JSON RESPONSE-SHAPE CONTRACT tests.
 *
 * These tests pin the CURRENT shape of the endpoints the React islands will
 * consume. They deliberately assert nothing about whether a shape is good —
 * only that it does not move. A failure here means a consumer breaks.
 *
 * Everything is built with the plain model API + the helpers already on
 * Tests\TestCase, so this suite does not depend on any factory work landing
 * in parallel.
 */
abstract class ContractTestCase extends TestCase
{
    /** A club with the user as an owner-and-active-member. */
    protected function clubFor(User $user, array $attrs = []): Tenant
    {
        $club = $this->createClub($user, array_merge(['country' => 'BH', 'currency' => 'BHD'], $attrs));
        $user->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);

        return $club;
    }

    /** A CONFIRMED (active subscription) club membership — what discovery requires. */
    protected function joinClub(User $user, Tenant $club): void
    {
        DB::table('memberships')->updateOrInsert(
            ['tenant_id' => $club->id, 'user_id' => $user->id],
            ['status' => 'active', 'created_at' => now(), 'updated_at' => now()],
        );

        ClubMemberSubscription::create([
            'tenant_id' => $club->id,
            'user_id' => $user->id,
            'type' => 'regular',
            'status' => 'active',
            'payment_status' => 'paid',
            'amount_paid' => 0,
            'amount_due' => 0,
            'start_date' => now(),
            'end_date' => now()->addYear(),
        ]);
    }

    protected function championship(Tenant $club, User $organiser, array $attrs = []): ClubEvent
    {
        return ClubEvent::create(array_merge([
            'tenant_id' => $club->id,
            'created_by' => $organiser->id,
            'title' => 'Spring Open',
            'event_type' => 'championship',
            'sport' => 'taekwondo',
            'scope' => 'internal',
            'date' => now()->addWeeks(2)->toDateString(),
            'end_date' => now()->addWeeks(2)->toDateString(),
            'start_time' => '09:00',
            'end_time' => '17:00',
            'status' => 'active',
            'is_archived' => false,
        ], $attrs));
    }

    /**
     * A division with four entrants and a generated draw.
     *
     * @return array{0: EventCategory, 1: array<int, User>}
     */
    protected function drawnDivision(ClubEvent $event, Tenant $club): array
    {
        $category = EventCategory::create([
            'event_id' => $event->id, 'name' => 'Senior Men -58 kg', 'sort_order' => 1,
        ]);

        $athletes = [];

        foreach (range(1, 4) as $i) {
            $athlete = $this->createUser([
                'full_name' => 'Athlete '.$i,
                'gender' => 'Male',
                'birthdate' => now()->subYears(25)->toDateString(),
            ]);
            $athlete->memberClubs()->syncWithoutDetaching([$club->id => ['status' => 'active']]);
            HealthRecord::create(['user_id' => $athlete->id, 'weight' => 57, 'recorded_at' => now()]);

            ClubEventRegistration::create([
                'event_id' => $event->id, 'user_id' => $athlete->id, 'category_id' => $category->id,
                'role' => 'participant', 'paid' => true, 'weight' => 57,
            ]);

            $athletes[] = $athlete->fresh();
        }

        app(\App\Events\EventTypeRegistry::class)->for($event)->performAction($event, 'generate_draw');

        return [$category->fresh(), $athletes];
    }

    /** Attach a playable recording (plus an officiating log) to the first bout. */
    protected function filmFirstBout(ClubEvent $event): EventMatch
    {
        $match = EventMatch::where('event_id', $event->id)->orderBy('match_no')->firstOrFail();
        $match->forceFill(['a_corner' => 'red', 'b_corner' => 'blue', 'court' => '1'])->save();

        $file = MediaFile::create([
            'kind' => 'clip', 'rel_path' => 'events/x/matches/1/clips/a.mp4',
            'hls_rel_path' => 'cache/hls/a', 'original_name' => 'bout.mp4',
            'mime' => 'video/mp4', 'bytes' => 1024, 'duration_seconds' => 180,
            'width' => 1920, 'height' => 1080, 'status' => MediaFile::STATUS_READY,
            'meta' => ['event_id' => $event->id], 'created_by' => $event->created_by,
        ]);

        $anchor = now()->startOfMinute();

        EventRecording::create([
            'event_id' => $event->id, 'match_id' => $match->id, 'court' => '1', 'angle' => 'main',
            'anchor_at' => $anchor, 'started_at' => $anchor, 'ended_at' => $anchor->copy()->addSeconds(180),
            'media_file_id' => $file->id, 'status' => EventRecording::STATUS_LINKED,
        ]);

        foreach ([
            ['s' => 1, 'off' => 10, 'side' => 'a', 'p' => 1, 'a' => 1, 'b' => 0],
            ['s' => 2, 'off' => 40, 'side' => 'b', 'p' => 3, 'a' => 1, 'b' => 3],
        ] as $row) {
            DB::table('event_match_events')->insert([
                'event_id' => $event->id, 'match_id' => $match->id, 'court' => '1',
                'sport' => $event->sport, 'command' => 'point',
                'payload' => json_encode(['round' => 1]), 'side' => $row['side'], 'points' => $row['p'],
                'score_a' => $row['a'], 'score_b' => $row['b'],
                'occurred_at' => $anchor->copy()->addSeconds($row['off'])->toDateTimeString(),
                'sequence' => $row['s'], 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $match->fresh();
    }

    /**
     * Nothing in this payload may look like a secret, a server path or a
     * credential. Asserted as raw text so a nested key can't slip past.
     */
    protected function assertLeaksNothingSensitive(string $body, array $extra = []): void
    {
        $forbidden = array_merge([
            'password', 'remember_token', 'api_token', 'access_token',
            'rel_path', 'hls_rel_path', 'proof_of_payment',
            '/var/www', 'storage/app', 'database/', '.sqlite',
        ], $extra);

        foreach ($forbidden as $needle) {
            $this->assertStringNotContainsString(
                $needle, $body, "Response leaks '{$needle}' — it must not reach the client."
            );
        }
    }
}
