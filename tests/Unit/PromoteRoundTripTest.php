<?php

namespace Tests\Unit;

use App\Support\Promote\Planner;
use PDO;
use Tests\TestCase;

/**
 * Promoting data back to where it came from links it, never copies it
 * (fix, 2026-10-31).
 *
 * On 30 Oct staging's events went to production, then production's data was
 * promoted back into staging. Events were matched by uuid, but their divisions,
 * entries and bouts have no uuid, so all of them came back as second copies —
 * every weight class and podium twice on the Results pages.
 */
class PromoteRoundTripTest extends TestCase
{
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $f) {
            @unlink($f);
        }
        parent::tearDown();
    }

    private function database(): PDO
    {
        $path = tempnam(sys_get_temp_dir(), 'promote-');
        $this->files[] = $path;
        $db = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $db->exec('create table users (id integer primary key, uuid text, full_name text, email text, mobile text, deleted_at text)');
        $db->exec('create table tenants (id integer primary key, slug text, club_name text)');
        $db->exec('create table memberships (id integer primary key, tenant_id integer references tenants(id), user_id integer references users(id))');
        $db->exec('create table club_events (id integer primary key, uuid text, title text, date text)');
        $db->exec('create table club_event_registrations (id integer primary key, event_id integer references club_events(id), user_id integer references users(id))');
        $db->exec('create table event_categories (id integer primary key, event_id integer not null references club_events(id), name text)');
        $db->exec('create table promotion_map (source_table text, source_key text, target_id integer, promoted_at text, primary key (source_table, source_key))');

        return $db;
    }

    public function test_rows_that_came_from_the_target_are_linked_back_not_added(): void
    {
        // The target: the original event and its division.
        $target = $this->database();
        $target->exec("insert into club_events values (104, 'ev-uuid', 'Premier League', '2026-10-17')");
        $target->exec("insert into event_categories values (979, 104, 'Under 10 Kumite Male -25 kg')");

        // The source received both from the target earlier (104 -> 70, 979 -> 208),
        // and has one division of its own (209).
        $source = $this->database();
        $source->exec("insert into club_events values (70, 'ev-uuid', 'Premier League', '2026-10-17')");
        $source->exec("insert into event_categories values (208, 70, 'Under 10 Kumite Male -25 kg')");
        $source->exec("insert into event_categories values (209, 70, 'Cadets Kumite Male -52 kg')");
        $source->exec("insert into promotion_map values ('club_events', '104', 70, '2026-10-30'), ('event_categories', '979', 208, '2026-10-30')");

        $planner = new Planner($this->files[1], $this->files[0]);
        $report = $planner->plan();
        $decisions = $planner->decisions();

        $this->assertSame('link', $decisions['state']['event_categories'][208]);
        $this->assertSame(979, $decisions['map']['event_categories'][208]);
        $this->assertSame('add', $decisions['state']['event_categories'][209]);
        $this->assertSame(1, $report['tables']['event_categories']['how']['came from here on an earlier run']);
        $this->assertArrayNotHasKey('promotion_map', $decisions['state']);
    }

    public function test_a_returning_row_removed_from_the_target_is_not_brought_back(): void
    {
        $target = $this->database();
        $target->exec("insert into club_events values (104, 'ev-uuid', 'Premier League', '2026-10-17')");

        $source = $this->database();
        $source->exec("insert into club_events values (70, 'ev-uuid', 'Premier League', '2026-10-17')");
        $source->exec("insert into event_categories values (208, 70, 'Removed division')");
        $source->exec("insert into promotion_map values ('event_categories', '979', 208, '2026-10-30')");

        $planner = new Planner($this->files[1], $this->files[0]);
        $planner->plan();

        $this->assertSame('cascade', $planner->decisions()['state']['event_categories'][208]);
    }
}
