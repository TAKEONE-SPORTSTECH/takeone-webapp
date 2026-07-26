<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Once-only guard so a record's "verified" milestone is announced to the feed exactly
// once (recompute() can run many times; a re-confirm shouldn't re-post).
return new class extends Migration
{
    public function up(): void
    {
        foreach (['tournament_events', 'skill_acquisitions'] as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'verification_announced_at')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->timestamp('verification_announced_at')->nullable()->after('verified_at');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['tournament_events', 'skill_acquisitions'] as $table) {
            if (Schema::hasColumn($table, 'verification_announced_at')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn('verification_announced_at'));
            }
        }
    }
};
