<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The penalty for entering late.
 *
 * A flat amount added once to an entry taken after `late_fee_from`, on top of
 * whatever that entrant selected. Deliberately flat and deliberately per ENTRY
 * rather than per option or a percentage: it is a penalty for being late, not a
 * different product, and an organiser who wants "20% more" can say the number.
 *
 * Participants only. A spectator buying a ticket on the day is the normal way
 * anyone watches sport, not a late entry.
 *
 * Both columns nullable and both required together to mean anything — an amount
 * with no date has no moment to start from, and a date with no amount charges
 * nothing. `EventFee::lateFeeApplies()` is the single place that asks.
 *
 * The charge is frozen onto the entry as its own fee line, so moving the
 * deadline afterwards never re-bills, or un-bills, anybody already in.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->decimal('late_fee_amount', 10, 3)->nullable()->after('spectator_fee_amount');
            $table->dateTime('late_fee_from')->nullable()->after('late_fee_amount');
        });
    }

    public function down(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->dropColumn(['late_fee_amount', 'late_fee_from']);
        });
    }
};
