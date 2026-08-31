<?php

use App\Support\MorphMap;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rewrite fully-qualified class names in morph columns to their MorphMap alias.
 *
 * Several call sites wrote `X::class` straight into a `*_type` column instead of
 * `$model->getMorphClass()`, so the row stored `App\Models\ClubEvent` where every
 * other row stored `club_event`. Nothing is broken while the class sits at that
 * namespace — but the moment it moves the row resolves to nothing, silently, and
 * that is exactly how the audit trail was welded to the namespace before.
 *
 * Data-only: no schema change. Both directions are derived from MorphMap, so the
 * pair stays correct if the map grows.
 */
return new class extends Migration
{
    /** column => table, for every mapped morph column that can hold a class name. */
    private const COLUMNS = [
        'user_notifications' => ['subject_type'],
        'activity_log' => ['subject_type', 'causer_type'],
        'achievement_vouches' => ['vouchable_type'],
        'media_file_subjects' => ['subject_type'],
        'notes_media' => ['notable_type'],
        'media_files' => ['owner_type'],
    ];

    public function up(): void
    {
        foreach (MorphMap::map() as $alias => $class) {
            $this->rewrite($class, $alias);
        }
    }

    public function down(): void
    {
        foreach (MorphMap::map() as $alias => $class) {
            $this->rewrite($alias, $class);
        }
    }

    private function rewrite(string $from, string $to): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! DB::getSchemaBuilder()->hasColumn($table, $column)) {
                    continue;
                }

                DB::table($table)->where($column, $from)->update([$column => $to]);
            }
        }
    }
};
