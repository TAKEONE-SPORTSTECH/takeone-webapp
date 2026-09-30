<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Category systems and belt systems, chosen per event (2026-09-28).
 *
 * A sport is not one rule book. Karate alone is WKF, Shotokan, Kyokushin and
 * more — each with its own age groups, weight classes and belt ladder. The
 * organiser now chooses, per event, which CATEGORY system (age groups ×
 * weight classes) and which BELT system it runs under; the classifier, the
 * divisions, the draw, the entry forms and the invitation all follow.
 *
 *   club_events.category_system / belt_system — a built-in key ('wkf',
 *     'shotokan', …) or 'custom:{uuid}'. NULL = the sport's own default, which
 *     is exactly what every existing event already uses.
 *   category_systems — the custom ones an organiser typed, owned by their host
 *     (a club or a federation) and reusable across its events.
 *
 * Additive — two nullable columns and one new table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_events', function (Blueprint $t) {
            $t->string('category_system', 64)->nullable();
            $t->string('belt_system', 64)->nullable();
        });

        Schema::create('category_systems', function (Blueprint $t) {
            $t->id();
            $t->uuid('uuid')->unique();
            $t->string('sport', 32)->index();
            $t->string('kind', 16);                      // 'weights' | 'belts'
            $t->string('label', 120);
            $t->string('host_type', 32);                 // 'club' | a module's host kind
            $t->unsignedBigInteger('host_id');
            $t->json('data');
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();

            $t->index(['host_type', 'host_id', 'sport', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_systems');
        Schema::table('club_events', function (Blueprint $t) {
            $t->dropColumn(['category_system', 'belt_system']);
        });
    }
};
