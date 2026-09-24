<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who entered this athlete.
 *
 * Null = the athlete entered themselves. Set = a coach or club admin entered
 * them on the club's behalf. Tournaments get entry disputes ("I never signed up
 * for that weight class"), so the entry needs an author, not just a timestamp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_event_registrations', function (Blueprint $table) {
            $table->foreignId('entered_by')->nullable()->after('registered_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('club_event_registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entered_by');
        });
    }
};
