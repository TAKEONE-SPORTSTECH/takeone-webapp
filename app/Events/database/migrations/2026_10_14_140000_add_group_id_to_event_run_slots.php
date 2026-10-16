<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Which GROUP a running-order class belongs to (App\Events\Support\RunSessions).
 *
 * A group used to own "every class below its header until the next one", which
 * left its edges invisible and a break impossible to keep outside it. Now a
 * class names its group outright: `group_id` is the id of the group's own
 * slot. Breaks, ceremonies and groups themselves never carry one.
 *
 * Additive and nullable (RULE #1). Groups made in the first version (only on
 * stage, 2026-10-14) are carried over: each class directly under a group
 * header, up to the next non-class row, joins that group.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_run_slots', function (Blueprint $table) {
            $table->unsignedBigInteger('group_id')->nullable()->after('mode')->index();
        });

        $slots = DB::table('event_run_slots')
            ->whereIn('event_id', DB::table('event_run_slots')->where('kind', 'group')->pluck('event_id'))
            ->orderBy('event_id')->orderBy('day')->orderBy('position')->orderBy('id')
            ->get(['id', 'event_id', 'day', 'kind']);

        $current = null;
        $key = null;
        foreach ($slots as $slot) {
            if ($key !== $slot->event_id.':'.$slot->day) {
                $key = $slot->event_id.':'.$slot->day;
                $current = null;
            }
            if ($slot->kind === 'group') {
                $current = $slot->id;
            } elseif ($slot->kind === 'division' && $current !== null) {
                DB::table('event_run_slots')->where('id', $slot->id)->update(['group_id' => $current]);
            } else {
                $current = null;
            }
        }
    }

    public function down(): void
    {
        Schema::table('event_run_slots', function (Blueprint $table) {
            $table->dropIndex(['group_id']);
            $table->dropColumn('group_id');
        });
    }
};
