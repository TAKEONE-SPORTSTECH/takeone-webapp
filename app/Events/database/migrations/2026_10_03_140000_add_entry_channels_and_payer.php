<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PLAN.md Phase 7 — how an event takes entries, and who pays for each.
 *
 * club_events.entry_channels: the organiser's list (individual · club). NULL
 * means the platform default — exactly today's behaviour (App\Events\Support\
 * EntryChannels).
 *
 * club_event_registrations.payer_type / payer_id: who OWES the entry fee — the
 * person ('person', their user id) for an entry they made themselves, the club
 * ('club', the tenant id) for an athlete the club entered — or the event's
 * off-platform club ('event_club', the event_clubs id). Separate from
 * `paid_by`, which records who VERIFIED a payment and keeps that meaning.
 * Filled in for every existing entry from the channel it came through.
 *
 * Additive: three nullable columns; the existing columns are only read.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('club_events', 'entry_channels')) {
            Schema::table('club_events', function (Blueprint $table) {
                $table->json('entry_channels')->nullable();
            });
        }

        if (! Schema::hasColumn('club_event_registrations', 'payer_type')) {
            Schema::table('club_event_registrations', function (Blueprint $table) {
                $table->string('payer_type', 16)->nullable();
                $table->unsignedBigInteger('payer_id')->nullable();
                $table->index(['payer_type', 'payer_id'], 'cer_payer');
            });
        }

        // Who owes each existing entry: the club for a club entry naming a
        // platform club; the event's own off-platform club (event_clubs) for a
        // club entry made under one; the person for everything else.
        DB::table('club_event_registrations')->whereNull('payer_type')
            ->where('entry_channel', 'club')->whereNotNull('representing_tenant_id')
            ->update(['payer_type' => 'club', 'payer_id' => DB::raw('representing_tenant_id')]);
        foreach (DB::table('event_club_entrants')->get(['registration_id', 'event_club_id']) as $link) {
            DB::table('club_event_registrations')->where('id', $link->registration_id)
                ->whereNull('payer_type')->where('entry_channel', 'club')
                ->update(['payer_type' => 'event_club', 'payer_id' => $link->event_club_id]);
        }
        DB::table('club_event_registrations')->whereNull('payer_type')
            ->update(['payer_type' => 'person', 'payer_id' => DB::raw('user_id')]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('club_event_registrations', 'payer_type')) {
            Schema::table('club_event_registrations', function (Blueprint $table) {
                $table->dropIndex('cer_payer');
                $table->dropColumn(['payer_type', 'payer_id']);
            });
        }
        if (Schema::hasColumn('club_events', 'entry_channels')) {
            Schema::table('club_events', function (Blueprint $table) {
                $table->dropColumn('entry_channels');
            });
        }
    }
};
