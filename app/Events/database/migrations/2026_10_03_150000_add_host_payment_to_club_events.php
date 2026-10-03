<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A self-organised event's payment details (owner, 2026-10-03; PLAN.md Phase
 * 6): an event a PERSON hosts has no club bank account, so the organiser types
 * their own — the same fields a club's bank account has (bank_name,
 * account_name, account_number, iban, benefitpay). Used only when the event
 * has no club. Additive: one nullable column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('club_events', 'host_payment')) {
            Schema::table('club_events', function (Blueprint $table) {
                $table->json('host_payment')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('club_events', 'host_payment')) {
            Schema::table('club_events', function (Blueprint $table) {
                $table->dropColumn('host_payment');
            });
        }
    }
};
