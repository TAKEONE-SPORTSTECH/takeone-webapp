<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Entering athletes becomes a permission a club can delegate.
 *
 * Until now the right to enter a squad was hard-coded to two role slugs
 * (`owner`, `club-admin`), so the coach who actually knows who is fighting got
 * an empty roster and no explanation. It matters more once entering commits the
 * club to money: this is the grant an owner hands to a trusted coach, and takes
 * back, through the per-member access editor that already exists.
 *
 * The owner of a club holds it implicitly — ownership is not a role row.
 */
return new class extends Migration
{
    public function up(): void
    {
        $id = DB::table('permissions')->where('slug', 'enter-athletes')->value('id');

        if (! $id) {
            $id = DB::table('permissions')->insertGetId([
                'name' => 'Enter Athletes',
                'slug' => 'enter-athletes',
                'description' => 'Enter this club’s athletes into competitions, committing the club to the entry fees',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Whoever already administers a club keeps the ability they had before.
        $roleIds = DB::table('roles')->whereIn('slug', ['club-admin', 'owner'])->pluck('id');

        foreach ($roleIds as $roleId) {
            $exists = DB::table('role_permission')
                ->where('role_id', $roleId)->where('permission_id', $id)->exists();

            if (! $exists) {
                DB::table('role_permission')->insert([
                    'role_id' => $roleId,
                    'permission_id' => $id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        $id = DB::table('permissions')->where('slug', 'enter-athletes')->value('id');

        if ($id) {
            DB::table('role_permission')->where('permission_id', $id)->delete();
            DB::table('permissions')->where('id', $id)->delete();
        }
    }
};
