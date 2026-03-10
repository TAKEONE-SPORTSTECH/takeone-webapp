<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->unsignedTinyInteger('cancel_within_days')->nullable()->after('max_capacity')
                ->comment('If set, members cannot leave the event after this many days from joining');
        });
    }

    public function down(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->dropColumn('cancel_within_days');
        });
    }
};
