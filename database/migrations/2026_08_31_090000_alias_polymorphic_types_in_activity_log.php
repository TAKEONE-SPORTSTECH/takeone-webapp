<?php

use App\Support\MorphMap;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rewrite the fully-qualified class names already sitting in the activity log's
 * polymorphic columns into the stable aliases from App\Support\MorphMap.
 *
 * Until now `activity_log.subject_type` held strings like `App\Clubs\Models\Tenant`,
 * which welds the audit trail to the PHP namespace — moving a model into a
 * module folder would leave every historical row pointing at a class that no
 * longer exists there. Nothing would error; the history would just stop
 * resolving. Aliasing first makes every later move free.
 *
 * Only the two REAL morph columns are touched. `user_notifications.subject_type`
 * is deliberately left alone: it is a free-form tag column (it also holds
 * 'post', 'order', 'class', 'product') that code reads back with the same
 * literal it wrote, not a morphTo relation.
 *
 * Idempotent (a class name that is already an alias matches nothing) and
 * reversible.
 */
return new class extends Migration
{
    /** The genuine morphTo columns. */
    private const COLUMNS = [
        'activity_log' => ['subject_type', 'causer_type'],
    ];

    public function up(): void
    {
        $this->rewrite(array_flip(MorphMap::map()));   // class => alias
    }

    public function down(): void
    {
        $this->rewrite(MorphMap::map());               // alias => class
    }

    /** @param array<string, string> $replacements */
    private function rewrite(array $replacements): void
    {
        foreach (self::COLUMNS as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }

                foreach ($replacements as $from => $to) {
                    DB::table($table)->where($column, $from)->update([$column => $to]);
                }
            }
        }
    }
};
