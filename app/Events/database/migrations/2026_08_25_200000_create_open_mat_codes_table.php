<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The six characters somebody reads off a screen and types on a phone.
 *
 * ── Why this is a table and not a cache entry ───────────────────────────────
 *
 * It was a cache entry with a four-hour TTL, and the cache store here is the
 * FILE driver. Which means any deploy — any `cache:clear`, any `config:cache`
 * during a session — voided every live join code in the middle of an open mat.
 * And the failure is deliberately indistinguishable from a mistyped code
 * ("that code doesn't match a mat"), so nobody in the hall could ever tell the
 * difference between fat fingers and a cleared cache. Unfalsifiable in
 * production, which is the worst kind of bug to leave in place.
 *
 * A code now lives as long as its mat does. The cache keeps its role as the fast
 * reverse lookup, but it is an INDEX over this table rather than the only copy —
 * so a cleared cache costs one query, not an evening.
 *
 * Package-owned, per the Events-Are-Packages rule: this table means nothing to
 * any other event type, and deleting the Open Mat directory plus this table
 * removes the feature whole.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('open_mat_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('club_events')->cascadeOnDelete();

            // The mat, in the same vocabulary as everywhere else in this package
            // ("Mat 1"): there is no mats table — mats are derived from
            // club_events.courts — so the label IS the identity.
            $table->string('court');

            // Uppercase, from an alphabet with no O/0/I/1: this gets read off a
            // screen across a room and typed by somebody in a hurry.
            $table->string('code', 6);

            // Rotating a code is an act worth remembering: it is how an operator
            // shuts out somebody who should not still have it.
            $table->timestamp('rotated_at')->nullable();
            $table->timestamps();

            // One live code per mat, and a code points at exactly one mat.
            $table->unique(['event_id', 'court']);
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('open_mat_codes');
    }
};
