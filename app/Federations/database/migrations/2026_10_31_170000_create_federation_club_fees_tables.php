<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The yearly fee a club pays its federation to be recognised for a season —
 * and, only when a federation switches it on, the athlete's own licence.
 *
 *   federation_fee_seasons  one season of one federation: dates, the club fee,
 *                           the grace after it ends, and the athlete-licence
 *                           option (off unless the federation turns it on).
 *   federation_fees         one fee owed: a club's (kind=club) or, later, an
 *                           athlete's (kind=athlete) — due → submitted (proof
 *                           uploaded) → approved / rejected, or waived.
 *
 * Behind `federations.club_fees` (FEDERATION_CLUB_FEES, default off). Additive
 * only (2026-10-31): nothing existing is altered.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('federation_fee_seasons')) {
            Schema::create('federation_fee_seasons', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('federation_id')->constrained('federations')->cascadeOnDelete();
                $table->string('name', 60);
                $table->date('starts_on');
                $table->date('ends_on');
                $table->date('due_on')->nullable();
                $table->unsignedSmallInteger('grace_days')->default(30);
                $table->decimal('club_fee', 10, 3)->default(0);
                $table->string('currency', 3)->default('BHD');
                $table->boolean('athlete_licence')->default(false);
                $table->decimal('athlete_fee', 10, 3)->nullable();
                $table->string('athlete_payer', 10)->nullable();   // club · athlete
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['federation_id', 'name']);
                $table->index(['federation_id', 'starts_on']);
            });
        }

        if (! Schema::hasTable('federation_fees')) {
            Schema::create('federation_fees', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('season_id')->constrained('federation_fee_seasons')->cascadeOnDelete();
                $table->string('kind', 10)->default('club');        // club · athlete
                $table->foreignId('federation_club_id')->constrained('federation_clubs')->cascadeOnDelete();
                $table->foreignId('federation_athlete_id')->nullable()->constrained('federation_athletes')->cascadeOnDelete();
                $table->decimal('amount', 10, 3);
                $table->string('currency', 3);
                $table->string('status', 12)->default('due');       // due · submitted · approved · rejected · waived
                $table->string('proof_path')->nullable();
                $table->string('proof_mime', 60)->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('decided_at')->nullable();
                $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('reason')->nullable();
                $table->timestamps();
                $table->index(['season_id', 'kind', 'status']);
                $table->index(['federation_club_id', 'kind']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_fees');
        Schema::dropIfExists('federation_fee_seasons');
    }
};
