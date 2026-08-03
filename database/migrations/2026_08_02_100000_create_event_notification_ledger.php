<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Idempotency ledger for event notifications.
 *
 * Time-triggered milestones (enrolment closing, weigh-in day, event-morning
 * reminder) are fired by a scheduler, so a cron re-run, a queue retry or two
 * workers racing would otherwise notify the same people twice. One row per
 * (event, milestone) with a unique index makes the send exactly-once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_notifications_sent', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();
            $table->string('milestone', 40);          // created | enrolment_opens | weigh_in | …
            $table->unsignedInteger('recipients')->default(0);
            $table->unsignedInteger('skipped')->default(0);   // over the fan-out cap — never silent
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'milestone']);
        });

        Schema::table('club_events', function (Blueprint $table) {
            // Which countries a regional/worldwide announcement reaches. Null =
            // the host country only, so a mis-set scope can never fan out wider
            // than intended by default.
            $table->json('notify_countries')->nullable()->after('scope');
        });
    }

    public function down(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->dropColumn('notify_countries');
        });
        Schema::dropIfExists('event_notifications_sent');
    }
};
