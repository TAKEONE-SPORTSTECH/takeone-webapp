<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('duels', function (Blueprint $table) {
            // Optional link to a club event — when set, the duel takes place at the event's location.
            $table->foreignId('event_id')->nullable()->after('format')
                ->constrained('club_events')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('duels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
        });
    }
};
