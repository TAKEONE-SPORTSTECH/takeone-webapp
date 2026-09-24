<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The Members lang files moved from lang/<locale>/ into app/Members/resources/lang/,
 * so their keys are now read as `members::<group>.<key>`. The machine
 * translations stored in interface_translations are keyed by (namespace,
 * group); without this they would stop being found and every language but
 * English and Arabic would fall back to English on these screens.
 *
 * Data only, and exactly reversible.
 */
return new class extends Migration
{
    private const GROUPS = ['member', 'family', 'messenger', 'settings', 'security'];

    public function up(): void
    {
        DB::table('interface_translations')
            ->whereNull('namespace')
            ->whereIn('group', self::GROUPS)
            ->update(['namespace' => 'members']);
    }

    public function down(): void
    {
        DB::table('interface_translations')
            ->where('namespace', 'members')
            ->whereIn('group', self::GROUPS)
            ->update(['namespace' => null]);
    }
};
