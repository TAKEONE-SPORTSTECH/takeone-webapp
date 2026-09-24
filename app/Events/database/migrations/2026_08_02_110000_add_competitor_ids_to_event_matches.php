<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Give every bout a real competitor, not just a name.
 *
 * Bouts stored athletes as free text (`a_name`), which meant nothing downstream
 * could tell WHO fought: no "you're up next, Mat 3" to the athlete, no result on
 * their profile, no ranking points, no medal tally by club, no head-to-head.
 *
 * Each side now points at the entry that produced it (club_event_registrations),
 * from which the user, the club and the division all follow. The name column
 * stays as a display cache — and remains the only thing set for a hand-typed
 * entrant who has no registration (an invited athlete from outside the platform),
 * so manual brackets keep working.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_matches', function (Blueprint $table) {
            $table->foreignId('a_competitor_id')->nullable()->after('a_name')
                ->constrained('club_event_registrations')->nullOnDelete();
            $table->foreignId('b_competitor_id')->nullable()->after('b_name')
                ->constrained('club_event_registrations')->nullOnDelete();
        });

        $this->backfill();
    }

    /**
     * Best-effort link for brackets that already exist: within each bout's own
     * division, match the stored name against the entrants' names. Exact,
     * case-insensitive, and skipped when a name is ambiguous — a wrong link is
     * worse than none, because it would put a result on the wrong athlete.
     */
    private function backfill(): void
    {
        $entrants = DB::table('club_event_registrations as r')
            ->join('users as u', 'u.id', '=', 'r.user_id')
            ->whereNotNull('r.category_id')
            ->where('r.role', 'participant')
            ->get(['r.id', 'r.category_id', 'u.full_name', 'u.name']);

        // category_id => lower(name) => registration id (null when ambiguous)
        $byCategory = [];
        foreach ($entrants as $e) {
            // Dedupe an entrant's own labels first — full_name and name are
            // frequently identical, and nobody may be mistaken for a duplicate
            // of themselves.
            $labels = array_unique(array_map(
                fn ($l) => mb_strtolower(trim($l)),
                array_filter([$e->full_name, $e->name]),
            ));

            foreach ($labels as $key) {
                if (array_key_exists($key, $byCategory[$e->category_id] ?? [])) {
                    $byCategory[$e->category_id][$key] = null;   // two entrants share a name → ambiguous
                } else {
                    $byCategory[$e->category_id][$key] = $e->id;
                }
            }
        }

        if (! $byCategory) {
            return;
        }

        DB::table('event_matches')->orderBy('id')->chunkById(500, function ($matches) use ($byCategory) {
            foreach ($matches as $m) {
                $map = $byCategory[$m->category_id] ?? null;
                if (! $map) {
                    continue;
                }

                $update = [];
                foreach (['a', 'b'] as $side) {
                    $name = $m->{$side.'_name'};
                    if (! $name) {
                        continue;
                    }
                    $id = $map[mb_strtolower(trim($name))] ?? null;
                    if ($id) {
                        $update[$side.'_competitor_id'] = $id;
                    }
                }

                if ($update) {
                    DB::table('event_matches')->where('id', $m->id)->update($update);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('event_matches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('a_competitor_id');
            $table->dropConstrainedForeignId('b_competitor_id');
        });
    }
};
