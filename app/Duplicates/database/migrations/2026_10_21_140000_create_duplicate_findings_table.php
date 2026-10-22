<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * What the duplicate watch found (App\Duplicates).
 *
 * One row per suspected duplicate — two people, two clubs, a typed event club
 * that is a platform club, a spare entry — keyed by a SIGNATURE so the hourly
 * run updates the same row instead of adding another. A finding is open until
 * it is fixed (by the watch itself or a person), dismissed, or simply stops
 * being true (gone).
 *
 * The ids are plain columns, not foreign keys, on purpose: a merge folds one
 * person into another, and the finding must keep saying who it was about.
 *
 * Purely additive — one new table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duplicate_findings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('kind', 20);            // person | club | event_club | entry
            $table->string('signature', 190)->unique();
            $table->unsignedBigInteger('a_id')->nullable();
            $table->unsignedBigInteger('b_id')->nullable();
            $table->unsignedBigInteger('event_id')->nullable();
            $table->json('reasons')->nullable();
            $table->string('strength', 10);        // certain | likely
            $table->string('status', 12)->default('open'); // open | fixed | dismissed | gone
            $table->json('fix')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamps();

            $table->index(['status', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duplicate_findings');
    }
};
