<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who verified the payment.
 *
 * `paid` alone only says money is expected to have changed hands — a member can
 * upload a proof and an organiser can tick the box. It does not say an official
 * checked it. The roster needs to tell those apart: claimed (amber) versus
 * verified (green).
 *
 * The weigh-in already had somewhere to record this — `weighed_in_by` — which
 * nothing was writing. This gives payment the same.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_event_registrations', function (Blueprint $table) {
            $table->foreignId('paid_by')->nullable()->after('paid_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('club_event_registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('paid_by');
        });
    }
};
