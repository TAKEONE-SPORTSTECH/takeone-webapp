<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Officiating is either volunteered or paid — and when it is paid, the amount
 * has to be stated, because it is a real cost of running the event.
 *
 * A paid appointment writes a matching row into event_expenses (see
 * EventOfficial::booted), so the event's P&L accounts for the people who ran it
 * without anyone re-keying it. `event_official_id` on the expense is what links
 * the two and marks the row as system-managed: it is kept in step with the
 * appointment and cannot be hand-deleted out of the ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_officials', function (Blueprint $table) {
            // 'volunteer' | 'paid' — volunteer is the safe default, so an
            // existing appointment never silently becomes a cost.
            $table->string('compensation', 20)->default('volunteer')->after('role');
            $table->decimal('fee', 10, 3)->nullable()->after('compensation');
        });

        Schema::table('event_expenses', function (Blueprint $table) {
            $table->foreignId('event_official_id')->nullable()->after('event_id')
                ->constrained('event_officials')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_expenses', function (Blueprint $table) {
            $table->dropForeign(['event_official_id']);
            $table->dropColumn('event_official_id');
        });

        Schema::table('event_officials', function (Blueprint $table) {
            $table->dropColumn(['compensation', 'fee']);
        });
    }
};
