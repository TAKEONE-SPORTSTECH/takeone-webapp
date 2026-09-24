<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Did this broadcast stay on one bout?"
 *
 * A mat's camera runs continuously while bouts come and go, and `attachBout`
 * deliberately repoints a running stream at whatever is now in front of it. That
 * is right for the live view and wrong for the recording: if the stream moved
 * across three fights, the resulting video is footage of the MAT, and publishing
 * it as one athlete's bout would put the wrong video on their record.
 *
 * So the session remembers whether it was ever repointed. Set once, when it
 * happens; read once, when the recording is filed. It is the difference between
 * "this file is that bout" and "this file is that afternoon".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->boolean('match_repointed')->default(false)->after('match_id');
        });
    }

    public function down(): void
    {
        Schema::table('live_streams', function (Blueprint $table) {
            $table->dropColumn('match_repointed');
        });
    }
};
