<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Federations, step 4: clubs entering a federation's event through the
 * invitation link.
 *
 *  - A club already affiliated (`federation_clubs`) gets a public uuid, a
 *    secret personal link and the officials it last named.
 *  - A club that is not on the platform applies (`federation_club_applications`)
 *    and stays an application until the federation approves it.
 *  - Every athlete has ONE federation identity (`federation_athletes`): the
 *    permanent Takeone ID and a national ID unique across the platform —
 *    stored encrypted, with a keyed hash for the uniqueness check — tied to
 *    the platform user who IS that person.
 *  - Which club an athlete competes for, per federation, with its history
 *    (`federation_athlete_clubs`); a transfer changes only this, never the
 *    club-side membership.
 *  - One entry per club per event, and its athletes.
 *
 * Purely additive — two columns on `federation_clubs`, seven new tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('federation_clubs', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->string('invite_token', 64)->nullable()->unique()->after('status');
            $table->json('officials')->nullable()->after('invite_token');
        });

        Schema::create('federation_club_applications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('federation_id')->constrained('federations')->cascadeOnDelete();
            $table->foreignId('federation_event_id')->nullable()->constrained('federation_events')->nullOnDelete();
            $table->string('name', 150);
            $table->string('coach', 150)->nullable();
            $table->string('email', 191)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('address', 300)->nullable();
            $table->string('logo')->nullable();
            $table->string('logo_shape', 10)->default('circle');
            $table->json('officials')->nullable();
            $table->string('invite_token', 64)->nullable()->unique();
            $table->string('status', 20)->default('Pending');   // Pending | Approved | Rejected
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->foreignId('federation_club_id')->nullable()->constrained('federation_clubs')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['federation_id', 'status']);
        });

        Schema::create('federation_athletes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('takeone_id', 16)->unique();          // ISO3-00000, permanent
            $table->text('national_id')->nullable();             // encrypted
            $table->string('national_id_hash', 64)->nullable()->unique();
            $table->string('nationality', 3)->nullable();        // ISO3, as the Takeone ID is built
            $table->timestamps();
        });

        Schema::create('federation_athlete_clubs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('federation_athlete_id')->constrained('federation_athletes')->cascadeOnDelete();
            $table->foreignId('federation_id')->constrained('federations')->cascadeOnDelete();
            $table->foreignId('federation_club_id')->nullable()->constrained('federation_clubs')->nullOnDelete();
            $table->foreignId('club_application_id')->nullable()->constrained('federation_club_applications')->nullOnDelete();
            $table->string('from_ym', 7);
            $table->string('to_ym', 7)->nullable();              // null = current
            $table->timestamps();

            $table->index(['federation_id', 'to_ym']);
        });

        Schema::create('federation_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('federation_id')->constrained('federations')->cascadeOnDelete();
            $table->foreignId('federation_athlete_id')->constrained('federation_athletes')->cascadeOnDelete();
            $table->foreignId('from_club_id')->nullable()->constrained('federation_clubs')->nullOnDelete();
            $table->foreignId('from_application_id')->nullable()->constrained('federation_club_applications')->nullOnDelete();
            $table->foreignId('to_club_id')->nullable()->constrained('federation_clubs')->nullOnDelete();
            $table->foreignId('to_application_id')->nullable()->constrained('federation_club_applications')->nullOnDelete();
            $table->string('requested_by', 150)->nullable();
            $table->string('status', 20)->default('Pending');    // Pending | Approved | Declined
            $table->string('old_club', 20)->default('Awaiting reply');   // Awaiting reply | Released | Objected
            $table->timestamp('due_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['federation_id', 'status']);
        });

        Schema::create('federation_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('federation_event_id')->constrained('federation_events')->cascadeOnDelete();
            $table->foreignId('federation_club_id')->nullable()->constrained('federation_clubs')->nullOnDelete();
            $table->foreignId('club_application_id')->nullable()->constrained('federation_club_applications')->nullOnDelete();
            $table->string('ref', 40)->unique();
            $table->json('teams')->nullable();
            $table->json('officials')->nullable();
            $table->json('payments')->nullable();
            $table->json('log')->nullable();
            $table->timestamps();

            $table->unique(['federation_event_id', 'federation_club_id']);
            $table->unique(['federation_event_id', 'club_application_id']);
        });

        Schema::create('federation_entry_athletes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('federation_entry_id')->constrained('federation_entries')->cascadeOnDelete();
            $table->foreignId('federation_athlete_id')->constrained('federation_athletes')->cascadeOnDelete();
            $table->decimal('weight', 5, 1)->nullable();
            $table->json('types')->nullable();
            $table->string('status', 20)->default('Active');     // Active | Withdrawn
            $table->string('approval', 20)->default('Pending');  // Pending | Approved (the federation's review)
            $table->boolean('late')->default(false);
            $table->boolean('chargeable')->default(false);       // a withdrawal after the deadline still owes its fee
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->unique(['federation_entry_id', 'federation_athlete_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_entry_athletes');
        Schema::dropIfExists('federation_entries');
        Schema::dropIfExists('federation_transfers');
        Schema::dropIfExists('federation_athlete_clubs');
        Schema::dropIfExists('federation_athletes');
        Schema::dropIfExists('federation_club_applications');

        Schema::table('federation_clubs', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropUnique(['invite_token']);
            $table->dropColumn(['uuid', 'invite_token', 'officials']);
        });
    }
};
