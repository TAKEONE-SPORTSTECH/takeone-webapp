<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Copy full_name → name for all users where they differ and full_name is set
        DB::table('users')
            ->whereNotNull('full_name')
            ->whereRaw('name != full_name')
            ->update(['name' => DB::raw('full_name')]);
    }

    public function down(): void
    {
        // Not reversible — original name values are lost
    }
};
