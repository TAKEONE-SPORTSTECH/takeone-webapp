<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * How an athlete got into the event, and who they compete FOR.
 *
 * Two doors already existed — a coach entering a squad, and an athlete entering
 * themselves — but the row could not tell you which one someone came through,
 * and nothing at all recorded the club they represent. The roster was guessing
 * it from the first club the athlete happens to belong to, which is exactly the
 * field a federation sheet gets wrong.
 *
 * `club_disowned_at` is the club's only say over a self-entry: it is moderation
 * after the fact, never a gate before it. A claim that is disowned leaves the
 * athlete competing UNATTACHED — it never removes them from the event, because a
 * slow or absent coach must not be able to stop someone competing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_event_registrations', function (Blueprint $table) {
            $table->string('entry_channel', 16)->default('individual')->after('entered_by')
                ->comment('club = entered by a coach/owner, individual = self-entered');

            $table->foreignId('representing_tenant_id')->nullable()->after('entry_channel')
                ->constrained('tenants')->nullOnDelete()
                ->comment('The club this athlete competes FOR at this event');

            $table->timestamp('club_disowned_at')->nullable()->after('representing_tenant_id')
                ->comment('Set when the represented club rejects the claim; the athlete competes unattached');
        });

        // Backfill the channel from who entered them: a row with no `entered_by`
        // is one the athlete created themselves.
        DB::table('club_event_registrations')->whereNotNull('entered_by')->update(['entry_channel' => 'club']);

        // Backfill the represented club only where it is unambiguous — an athlete
        // with exactly one active membership can only have been competing for it.
        // Anyone in two clubs is left null rather than guessed at.
        DB::table('club_event_registrations')
            ->where('role', 'participant')
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                $clubs = DB::table('memberships')
                    ->whereIn('user_id', collect($rows)->pluck('user_id')->unique())
                    ->where('status', 'active')
                    ->select('user_id', 'tenant_id')
                    ->get()
                    ->groupBy('user_id');

                foreach ($rows as $row) {
                    $mine = $clubs->get($row->user_id);
                    if ($mine && $mine->count() === 1) {
                        DB::table('club_event_registrations')->where('id', $row->id)
                            ->update(['representing_tenant_id' => $mine->first()->tenant_id]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('club_event_registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('representing_tenant_id');
            $table->dropColumn(['entry_channel', 'club_disowned_at']);
        });
    }
};
