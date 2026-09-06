<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What this entry was actually charged, frozen at the moment of entry.
 *
 * The bug this fixes predates multi-pricing
 * -----------------------------------------
 * `AbstractEventType::finance()` computed revenue as
 * `paid_count × the event's CURRENT fee`. So an organiser correcting a price
 * silently re-priced every entry ever taken and the event's profit and loss
 * changed underneath them, with nothing recorded anywhere about what anybody had
 * actually agreed to pay. Nobody noticed because there was only ever one number.
 *
 * The moment two entrants can pay different amounts, `count × fee` is not merely
 * imprecise — it cannot be expressed at all. So the charge stops being a
 * calculation and becomes a RECORD: one row per thing charged, written when the
 * entry is taken, never recomputed. `finance()` becomes a SUM over these, which
 * is both correct and simpler than what it replaces.
 *
 * The label is COPIED, not looked up
 * ----------------------------------
 * `label` and `amount` are stored on the line itself rather than read through
 * `fee_option_id`. An organiser renaming "T-shirt" to "Event T-shirt (2027)" or
 * repricing it must not rewrite what a competitor was charged last season. The
 * option id is kept alongside purely for provenance — which is also why options
 * are deactivated rather than deleted — and is null for a line that never came
 * from an option at all: the base entry fee, and the late-entry penalty.
 *
 * Deleting a registration takes its lines with it (cascade). That is right while
 * money is only ever recorded here; when Phase 3 invoices land, the invoice
 * becomes the durable record and this stays the breakdown behind it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registration_fee_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('registration_id')
                ->constrained('club_event_registrations')
                ->cascadeOnDelete();

            // Provenance only. Null for the base fee and the late penalty, which
            // are properties of the event rather than options anybody picked.
            // nullOnDelete so a hard-deleted option can never take a charge
            // record with it — the label and amount on this row stand alone.
            $table->foreignId('fee_option_id')
                ->nullable()
                ->constrained('event_fee_options')
                ->nullOnDelete();

            // What this line IS, so a total can always be explained without
            // joining anything: base | option | late.
            $table->string('kind', 20)->default('option');

            $table->string('label');
            $table->decimal('amount', 10, 3)->default(0);

            // Copied from the event at entry time for the same reason the amount
            // is: an event that later switches currency must not restate what
            // somebody already paid.
            $table->string('currency', 8)->nullable();

            $table->timestamps();

            $table->index(['registration_id', 'kind'], 'event_fee_lines_registration');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registration_fee_lines');
    }
};
