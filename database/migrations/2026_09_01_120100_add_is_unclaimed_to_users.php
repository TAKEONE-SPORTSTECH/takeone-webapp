<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A person record created on somebody else's behalf, that nobody has signed
 * into yet.
 *
 * The roster, the draw and the results all need a person the moment a coach
 * commits an entry — but that is not an ACCOUNT until the athlete claims it.
 * Kept out of search, discovery, messaging and every listing that means
 * "members", so an unclaimed name is a competitor and nothing more.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_unclaimed')->default(false)->after('is_discoverable');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_unclaimed');
        });
    }
};
