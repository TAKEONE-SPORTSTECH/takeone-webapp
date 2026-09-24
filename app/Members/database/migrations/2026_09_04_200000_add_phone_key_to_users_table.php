<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A phone number you can actually look somebody up by.
 *
 * `users.mobile` is a JSON blob — `{"code":"+973","number":"3774 3277"}` — and
 * signing in by telephone number was already being attempted against it with
 * `json_extract(mobile, '$.number') = ?`. That only ever matched when the
 * digits were typed EXACTLY as stored: a space, a leading zero or a different
 * way of writing the country code and the same person is a stranger.
 *
 * So this stores the answer instead of computing it per request: digits only,
 * country code included, leading zeros stripped — see
 * App\Members\Models\User::normalisePhone(), which is the ONE place the rule
 * lives and which the model keeps in sync on every save.
 *
 * ⚠️ DELIBERATELY NOT UNIQUE.
 * A telephone belongs to a household, not to a person. A parent enters two
 * children at a kids' tournament on one number, and this platform models
 * guardians and dependents precisely because that is the normal case — there
 * is already one number shared by two accounts here. A unique index would
 * refuse the second child. Sign-in resolves the ambiguity by asking WHO is
 * competing after the password is checked, never by picking a row.
 *
 * Additive: a new nullable column plus an index. Nothing reads it until the
 * sign-in path is taught to, and `mobile` remains the source of truth.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_key', 32)->nullable()->after('mobile');
            $table->index('phone_key');
        });

        // Backfill from what is already on file, using the same rule the model
        // will apply from now on. Chunked, and it touches nothing else.
        DB::table('users')->whereNotNull('mobile')->orderBy('id')
            ->select('id', 'mobile')->chunk(200, function ($rows) {
                foreach ($rows as $row) {
                    $key = \App\Members\Models\User::normalisePhone($row->mobile);

                    if ($key !== null) {
                        DB::table('users')->where('id', $row->id)->update(['phone_key' => $key]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['phone_key']);
            $table->dropColumn('phone_key');
        });
    }
};
