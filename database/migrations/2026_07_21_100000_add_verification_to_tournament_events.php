<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Adds a provenance/verification layer to member self-claimed tournament records.
 *
 * A TournamentEvent is created by the member (self_reported). It only ever becomes
 * "verified" once a trusted authority attests to it: the awarding club confirms it,
 * or credible coach/teammate vouches accrue past threshold. Status is set exclusively
 * by App\Services\AchievementVerificationService — never mass-assigned or client-trusted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_events', function (Blueprint $table) {
            // Public, non-guessable identifier for verification/vouch routes
            // (the table previously exposed only the auto-increment id).
            $table->uuid('uuid')->nullable()->after('id');

            $table->string('verification_status')->default('self_reported')->after('participants_count');
            $table->string('verification_method')->nullable()->after('verification_status');
            $table->foreignId('verified_by_tenant_id')->nullable()->after('verification_method')
                ->constrained('tenants')->nullOnDelete();
            $table->foreignId('verified_by_user_id')->nullable()->after('verified_by_tenant_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by_user_id');
            $table->string('verification_note')->nullable()->after('verified_at');
            $table->string('evidence_path')->nullable()->after('verification_note');
        });

        // Backfill a uuid for every existing row.
        DB::table('tournament_events')->whereNull('uuid')->orderBy('id')->each(function ($row) {
            DB::table('tournament_events')->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
        });

        Schema::table('tournament_events', function (Blueprint $table) {
            $table->unique('uuid');
            $table->index(['user_id', 'verification_status']);
        });
    }

    public function down(): void
    {
        Schema::table('tournament_events', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropIndex(['user_id', 'verification_status']);
            $table->dropConstrainedForeignId('verified_by_tenant_id');
            $table->dropConstrainedForeignId('verified_by_user_id');
            $table->dropColumn([
                'uuid', 'verification_status', 'verification_method',
                'verified_at', 'verification_note', 'evidence_path',
            ]);
        });
    }
};
