<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What an entry actually costs, as a number.
 *
 * `participant_fee` is a varchar that every money calculation had to guess at:
 * `feeAmount()` scraped the first number out of it, so "10-15 BHD" silently
 * billed 10, "BHD 20 / team" billed 20 per person, and a currency was whatever
 * the club happened to be set to when the total was rendered. Now the amount and
 * its currency are columns, and the string stays as what it always really was —
 * the DISPLAY line ("Free", "Qualified finalists", "BHD 20").
 *
 * NULL amount means "not a money amount" — free, by qualification, or unstated.
 * A real fee is a number, and 0 is a stated zero.
 *
 * The regex scrape lives here, once, on data that already exists. Nothing after
 * this migration is allowed to guess a price out of prose.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->decimal('participant_fee_amount', 10, 3)->nullable()->after('participant_fee');
            $table->decimal('spectator_fee_amount', 10, 3)->nullable()->after('spectator_fee');
            $table->string('fee_currency', 3)->nullable()->after('spectator_fee_amount');
        });

        DB::table('club_events')
            ->leftJoin('tenants', 'tenants.id', '=', 'club_events.tenant_id')
            ->orderBy('club_events.id')
            ->select('club_events.id', 'club_events.participant_fee', 'club_events.spectator_fee', 'tenants.currency')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('club_events')->where('id', $row->id)->update([
                        'participant_fee_amount' => $this->scrape($row->participant_fee),
                        'spectator_fee_amount' => $this->scrape($row->spectator_fee),
                        'fee_currency' => $row->currency ?: 'BHD',
                    ]);
                }
            }, 'club_events.id', 'id');
    }

    /** The last time a price is ever read out of prose. */
    private function scrape(?string $fee): ?float
    {
        return ($fee && preg_match('/[\d.]+/', $fee, $m)) ? (float) $m[0] : null;
    }

    public function down(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->dropColumn(['participant_fee_amount', 'spectator_fee_amount', 'fee_currency']);
        });
    }
};
