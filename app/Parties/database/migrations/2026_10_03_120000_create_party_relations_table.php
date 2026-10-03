<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ONE dated relationship between two parties (PLAN.md §3.2).
 *
 * "who (from) holds what role in which party (to), from when, until when, with
 * what share". Ending a relationship sets `ends_at` — rows are never deleted,
 * so the history (past owners, past presidents, a coach's earlier stint) is
 * the table itself.
 *
 * Party types are App\Parties\Kinds keys ('person', 'club', 'business',
 * 'federation', 'event'), deliberately NOT the Eloquent morph map: adding
 * aliases there would orphan history rows already stored under class names.
 *
 * Additive only: a new table, no foreign keys into existing tables (a party is
 * polymorphic, and history must outlive a deleted party).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('party_relations')) {
            return;
        }

        Schema::create('party_relations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('from_type', 32);
            $table->unsignedBigInteger('from_id');
            $table->string('to_type', 32);
            $table->unsignedBigInteger('to_id');
            $table->string('role', 40);
            $table->unsignedSmallInteger('share_bp')->nullable();   // owners: basis points, 10000 = 100%
            $table->string('title')->nullable();
            $table->timestamp('starts_at')->useCurrent();
            $table->timestamp('ends_at')->nullable();
            $table->string('end_reason', 40)->nullable();
            $table->unsignedBigInteger('granted_by')->nullable();
            $table->unsignedBigInteger('ended_by')->nullable();
            $table->string('source', 120)->nullable();            // backfill origin, e.g. "tenants.owner_user_id:7"
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['to_type', 'to_id', 'role', 'ends_at'], 'party_rel_to');
            $table->index(['from_type', 'from_id', 'ends_at'], 'party_rel_from');
            $table->index('source', 'party_rel_source');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('party_relations');
    }
};
