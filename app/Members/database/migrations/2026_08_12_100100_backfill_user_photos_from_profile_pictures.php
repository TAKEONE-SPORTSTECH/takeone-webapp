<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Every profile that already has a picture gets it as its first photo, so the
 * avatar and the photo list are the same thing from day one — otherwise the
 * photo sheet would open on "no pictures" for a member who plainly has one.
 *
 * Nothing is moved on disk: the row points at the existing path.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNotNull('profile_picture')
            ->where('profile_picture', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function ($users) {
                $now = now();
                $rows = [];

                foreach ($users as $user) {
                    $exists = DB::table('user_photos')
                        ->where('user_id', $user->id)
                        ->where('path', $user->profile_picture)
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $rows[] = [
                        'uuid' => (string) Str::uuid(),
                        'user_id' => $user->id,
                        'path' => $user->profile_picture,
                        'sort_order' => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if ($rows) {
                    DB::table('user_photos')->insert($rows);
                }
            });
    }

    public function down(): void
    {
        // The rows are indistinguishable from ones a member added afterwards,
        // so removing them on rollback would delete real data. Left in place.
    }
};
