<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A pinned note sits at the top of the notebook's list (owner, 28 Oct 2026:
 * the notebook laid out like the notes apps — Pinned, Today, Previous 7 days).
 * Additive: NULL is "not pinned", which every note is today.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('devboard_cards', 'pinned_at')) {
            Schema::table('devboard_cards', function (Blueprint $t) {
                $t->timestamp('pinned_at')->nullable()->after('in_notebook');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('devboard_cards', 'pinned_at')) {
            Schema::table('devboard_cards', function (Blueprint $t) {
                $t->dropColumn('pinned_at');
            });
        }
    }
};
