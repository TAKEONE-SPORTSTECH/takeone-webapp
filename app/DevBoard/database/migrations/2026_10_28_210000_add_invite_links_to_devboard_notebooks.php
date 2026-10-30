<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TakeNote invite LINKS (owner, 28 Oct 2026: "I just want to be able to provide
 * them a link and they can register, and start using the note app" → "let
 * them register straight away with the link").
 *
 * A link is a notebook waiting for its owner, like an email invitation, but
 * claimed by whoever opens it first — once. Only a SHA-256 of the link's secret
 * is kept (`invite_hash`); `label` is the owner's own note of who it is for;
 * `via_link` lets TakeNote — and nothing else on the platform — open before
 * the account's email is confirmed. Additive: three new nullable/defaulted columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devboard_notebooks', function (Blueprint $t) {
            if (! Schema::hasColumn('devboard_notebooks', 'invite_hash')) {
                $t->string('invite_hash', 64)->nullable()->unique();
            }
            if (! Schema::hasColumn('devboard_notebooks', 'label')) {
                $t->string('label', 80)->nullable();
            }
            if (! Schema::hasColumn('devboard_notebooks', 'via_link')) {
                $t->boolean('via_link')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('devboard_notebooks', function (Blueprint $t) {
            foreach (['invite_hash', 'label', 'via_link'] as $c) {
                if (Schema::hasColumn('devboard_notebooks', $c)) {
                    if ($c === 'invite_hash') {
                        $t->dropUnique('devboard_notebooks_invite_hash_unique');
                    }
                    $t->dropColumn($c);
                }
            }
        });
    }
};
