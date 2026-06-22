<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Give every post a short, unguessable token so it can have its own shareable
 * permalink (/p/{token}) that can't be enumerated by walking sequential IDs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_posts', function (Blueprint $table) {
            $table->string('token', 32)->nullable()->after('id');
        });

        // Backfill existing rows with a unique token.
        DB::table('user_posts')->select('id')->orderBy('id')->each(function ($row) {
            DB::table('user_posts')->where('id', $row->id)->update(['token' => Str::random(22)]);
        });

        Schema::table('user_posts', function (Blueprint $table) {
            $table->unique('token');
        });
    }

    public function down(): void
    {
        Schema::table('user_posts', function (Blueprint $table) {
            $table->dropUnique(['token']);
            $table->dropColumn('token');
        });
    }
};
