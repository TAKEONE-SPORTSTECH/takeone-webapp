<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The event sandbox — `app/EventLab/`.
 *
 * Six tables that exist ONLY to try out a new way of running a competition,
 * while the one that is actually running keeps using the code it runs on today.
 * Nothing here alters an existing table, and nothing outside this module reads
 * any of them: the whole experiment is removed by dropping these six, deleting
 * `app/EventLab/` and its one line in `config/modules.php`.
 *
 * What is being tried:
 *
 *  1. **An entrant is not an account.** `lab_entrants` is a person on a start
 *     list, not a platform member — `user_id` stays NULL until somebody decides,
 *     after the competition, that this person really did compete and should
 *     become a member. Duplicates are expected and are the point: two rows for
 *     the same human are cheap here, whereas two `users` rows are forever. The
 *     precedent is `open_mat_people`, which already carries a nullable
 *     `user_id` for the same reason.
 *
 *  2. **A club need not exist yet.** `lab_clubs` is a club named on an entry
 *     form. It may later be matched to a real `tenants` row or promoted into
 *     one; until then it is a name, a country and a logo, and the competition
 *     runs perfectly well on that.
 *
 *  3. **One event, several kinds of competition.** `lab_variants` is a thing an
 *     athlete can enter WITHIN one event (Gi, No-Gi, …), each with its own fee.
 *     An entry selects one or more, which is why the money lives on
 *     `lab_entry_variants` per selection and is TOTALLED onto `lab_entries`.
 *     The live platform models this as separate events because
 *     `club_event_registrations` is unique per (event, user); here the unique
 *     key is (event, entrant) and the variants hang off it.
 *
 *  4. **Late entry costs more.** A flat penalty on the event, stamped onto the
 *     entry when it is committed after `late_entry_from`.
 *
 * Money is SNAPSHOTTED, never recomputed: `lab_entry_variants.fee_amount` and
 * `lab_entries.fee_total` record what was charged at the moment of entry, so
 * changing a variant's price tomorrow cannot silently rewrite what somebody
 * already owes.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The sandbox's own event. `source_event_id` records which real event it
        // was modelled on — a reference for reading, never a foreign key, so the
        // real row can never be dragged along by anything that happens here.
        Schema::create('lab_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('source_event_id')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('title');
            $table->string('sport', 32)->nullable();
            $table->date('date')->nullable();
            $table->date('end_date')->nullable();
            $table->dateTime('weigh_in_at')->nullable();
            $table->dateTime('entries_open_at')->nullable();
            $table->dateTime('entries_close_at')->nullable();

            // The moment an entry starts counting as late. Null = no late fee.
            $table->dateTime('late_entry_from')->nullable();
            $table->decimal('late_fee_amount', 10, 3)->nullable();

            $table->string('fee_currency', 3)->default('BHD');
            $table->string('status', 16)->default('draft');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('source_event_id');
        });

        // A kind of competition inside one event. Gi and No-Gi are two of these,
        // not two events.
        Schema::create('lab_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_event_id')->constrained('lab_events')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 48);
            $table->text('description')->nullable();
            $table->decimal('fee_amount', 10, 3)->default(0);
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['lab_event_id', 'slug']);
        });

        // A club as it was named on an entry form. `tenant_id` is set when it has
        // been MATCHED to a club that already exists; `promoted_tenant_id` when a
        // brand new club was created from it after the competition.
        Schema::create('lab_clubs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('lab_event_id')->constrained('lab_events')->cascadeOnDelete();
            $table->string('name');
            $table->string('country', 3)->nullable();
            $table->string('logo')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('promoted_tenant_id')->nullable();
            $table->dateTime('promoted_at')->nullable();
            $table->timestamps();

            $table->index(['lab_event_id', 'name']);
        });

        // A person on the start list. NOT a platform member: `user_id` is filled
        // in only by promotion, once the competition is over and somebody has
        // confirmed this person actually took part.
        Schema::create('lab_entrants', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('lab_event_id')->constrained('lab_events')->cascadeOnDelete();

            $table->string('full_name');
            $table->date('birthdate')->nullable();
            $table->string('gender', 8)->nullable();
            $table->string('phone_code', 8)->nullable();
            $table->string('phone', 24)->nullable();
            $table->string('email')->nullable();
            $table->string('nationality', 3)->nullable();
            $table->string('photo')->nullable();
            $table->string('belt_colour', 32)->nullable();
            $table->string('belt_grade', 32)->nullable();

            // Either a provisional club, or a real one the entrant named.
            $table->foreignId('lab_club_id')->nullable()->constrained('lab_clubs')->nullOnDelete();
            $table->unsignedBigInteger('tenant_id')->nullable();

            // entered | withdrawn | no_show | competed | promoted | discarded
            $table->string('status', 16)->default('entered');

            // Set when a reviewer says this row is the same human as another one.
            // Kept, not deleted — the duplicate is evidence of what was typed.
            $table->unsignedBigInteger('duplicate_of_id')->nullable();

            // The platform member this entrant BECAME. Null for everyone until
            // the competition is over.
            $table->unsignedBigInteger('user_id')->nullable();
            $table->dateTime('promoted_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['lab_event_id', 'status']);
            $table->index(['lab_event_id', 'full_name']);
            $table->index('user_id');
            $table->index('duplicate_of_id');
        });

        // The entry itself: one per entrant per event, carrying state and money.
        Schema::create('lab_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('lab_event_id')->constrained('lab_events')->cascadeOnDelete();
            $table->foreignId('lab_entrant_id')->constrained('lab_entrants')->cascadeOnDelete();

            // draft | submitted | accepted | declined | withdrawn
            $table->string('state', 16)->default('submitted');
            $table->dateTime('entered_at')->nullable();

            // Stamped at entry time, never recomputed.
            $table->boolean('is_late')->default(false);
            $table->decimal('late_fee_amount', 10, 3)->default(0);
            $table->decimal('fee_total', 10, 3)->default(0);
            $table->string('fee_currency', 3)->default('BHD');
            $table->text('fee_breakdown')->nullable();

            // unpaid | pending_approval | paid | waived | refunded
            $table->string('payment_state', 20)->default('unpaid');
            $table->dateTime('paid_at')->nullable();
            $table->unsignedBigInteger('paid_by')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['lab_event_id', 'lab_entrant_id']);
            $table->index(['lab_event_id', 'state']);
        });

        // What this entry actually entered. One row per variant chosen, each
        // holding the price as it stood when it was chosen.
        Schema::create('lab_entry_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_entry_id')->constrained('lab_entries')->cascadeOnDelete();
            $table->foreignId('lab_variant_id')->constrained('lab_variants')->cascadeOnDelete();
            $table->decimal('weight', 6, 2)->nullable();
            $table->string('division_label')->nullable();
            $table->decimal('fee_amount', 10, 3)->default(0);
            $table->timestamps();

            $table->unique(['lab_entry_id', 'lab_variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_entry_variants');
        Schema::dropIfExists('lab_entries');
        Schema::dropIfExists('lab_entrants');
        Schema::dropIfExists('lab_clubs');
        Schema::dropIfExists('lab_variants');
        Schema::dropIfExists('lab_events');
    }
};
