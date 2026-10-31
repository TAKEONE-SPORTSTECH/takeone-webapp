<?php

namespace Tests\Feature\Events;

use App\Events\Models\ClubEvent;
use App\Events\Models\ClubEventRegistration;
use App\Events\Support\PodiumFaces;
use App\Members\Models\User;
use App\Members\Models\UserBlock;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A result sheet links a medallist's profile only where this viewer could open
 * it — and it decides that for the whole sheet with one block lookup, not two
 * queries per medal (2026-10-31: 328 of them on one event page).
 */
class PodiumFacesBlockTest extends TestCase
{
    /** @return array{0: array, 1: User, 2: User, 3: User} */
    private function sheet(): array
    {
        $organiser = $this->createUser();
        $club = $this->createClub($organiser, ['country' => 'BH']);
        $event = ClubEvent::create([
            'tenant_id' => $club->id, 'created_by' => $organiser->id, 'title' => 'Autumn Cup',
            'event_type' => 'championship', 'sport' => 'karate', 'scope' => 'internal',
            'date' => now()->toDateString(), 'end_date' => now()->toDateString(),
            'start_time' => '09:00', 'end_time' => '17:00', 'status' => 'active', 'is_archived' => false,
        ]);

        $gold = $this->createUser(['full_name' => 'Gold Fighter']);
        $silver = $this->createUser(['full_name' => 'Silver Fighter']);
        $medals = [];
        foreach ([1 => $gold, 2 => $silver] as $place => $athlete) {
            $reg = ClubEventRegistration::create([
                'event_id' => $event->id, 'user_id' => $athlete->id, 'role' => 'participant', 'paid' => true,
            ]);
            $medals[] = ['place' => $place, 'name' => $athlete->full_name, 'competitor_id' => $reg->id];
        }

        return [[['name' => 'Cadets Kumite Male -52 kg', 'medals' => $medals]], $this->createUser(), $gold, $silver];
    }

    public function test_a_medallist_who_blocked_the_viewer_is_not_linked(): void
    {
        [$rows, $viewer, $gold, $silver] = $this->sheet();
        UserBlock::create(['blocker_id' => $gold->id, 'blocked_id' => $viewer->id]);
        $this->actingAs($viewer);

        $medals = PodiumFaces::enrich($rows)[0]['medals'];

        $this->assertNull($medals[0]['person']);
        $this->assertSame($silver->uuid, $medals[1]['person']);
    }

    public function test_a_medallist_the_viewer_blocked_is_not_linked(): void
    {
        [$rows, $viewer, $gold, $silver] = $this->sheet();
        UserBlock::create(['blocker_id' => $viewer->id, 'blocked_id' => $silver->id]);
        $this->actingAs($viewer);

        $medals = PodiumFaces::enrich($rows)[0]['medals'];

        $this->assertSame($gold->uuid, $medals[0]['person']);
        $this->assertNull($medals[1]['person']);
    }

    public function test_the_block_lookup_runs_once_per_sheet_not_per_medal(): void
    {
        [$rows, $viewer] = $this->sheet();
        $this->actingAs($viewer);

        $blockQueries = 0;
        DB::listen(function ($q) use (&$blockQueries) {
            if (str_contains($q->sql, 'user_blocks')) {
                $blockQueries++;
            }
        });

        PodiumFaces::enrich($rows);

        $this->assertSame(2, $blockQueries);
    }
}
