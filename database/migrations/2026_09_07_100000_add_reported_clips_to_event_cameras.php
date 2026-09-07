<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the phone says it is HOLDING, not what the event was told about.
 *
 * The two are different, and the gap is where footage goes missing. A clip only
 * gets a row here when the phone manages to file it — one call, at the end of a
 * bout, over a hall's wifi. A camera that was somewhere else that day, or was
 * re-paired onto another competition since, is left carrying files that no
 * console can see, name, or clear. Two week-old recordings sat on a phone
 * exactly like that while its mat panel showed nothing at all.
 *
 * The beat already carries the phone's file list — this is where it is kept, so
 * the panel can say "the camera is holding these" separately from "this event
 * has these", and an operator can play or delete a file the index never heard
 * of. Capped and rewritten whole on every beat: it is a snapshot, not a record.
 *
 * Additive and nullable; null means no beat has said yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_cameras', function (Blueprint $table) {
            $table->json('reported_clips')->nullable()->after('reported_settings');
        });
    }

    public function down(): void
    {
        Schema::table('event_cameras', function (Blueprint $table) {
            $table->dropColumn('reported_clips');
        });
    }
};
