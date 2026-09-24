<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a camera is set to, and what is still ON the phone.
 *
 * Two things the console could not answer before, and both are needed the
 * moment an organiser is given camera controls at the mat rather than a live
 * dot and a battery percentage.
 *
 *  · `settings` is INTENT — what the scoring table asked this camera to run at.
 *    Written by the console, published over MQTT, and read back by the phone on
 *    its next config beat, so an order given while a phone was asleep is not
 *    simply lost. It is deliberately separate from…
 *  · `reported_settings`, which is FACT: what the phone says it is actually
 *    running. The console shows the two apart, exactly as `broadcasting` and
 *    `on_air` are shown apart — a panel that showed one as the other would
 *    claim a camera is filming at 60fps because somebody pressed 60.
 *  · `on_device` on a clip is the other half of the same honesty: the server
 *    keeps a clip's row forever, but the FILE can be deleted at the phone. The
 *    beat reports the phone's inventory, and a row nobody can still find is
 *    shown as gone rather than offered for upload.
 *
 * Additive: nullable columns, no backfill, nothing reads them until the code
 * that writes them ships.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_cameras', function (Blueprint $table) {
            $table->json('settings')->nullable()->after('app_version');
            $table->json('reported_settings')->nullable()->after('settings');
        });

        Schema::table('event_camera_clips', function (Blueprint $table) {
            // NULL means "no phone has told us either way" — which is what
            // every existing row is, and is not the same as false.
            $table->boolean('on_device')->nullable()->after('local_ref');
        });
    }

    public function down(): void
    {
        Schema::table('event_cameras', function (Blueprint $table) {
            $table->dropColumn(['settings', 'reported_settings']);
        });

        Schema::table('event_camera_clips', function (Blueprint $table) {
            $table->dropColumn('on_device');
        });
    }
};
