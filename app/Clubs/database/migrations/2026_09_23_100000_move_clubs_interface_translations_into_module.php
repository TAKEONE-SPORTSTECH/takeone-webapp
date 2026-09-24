<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * The Clubs lang files moved from lang/<locale>/ into app/Clubs/resources/lang/,
 * so their keys are now read as `clubs::<group>.<key>`. The machine
 * translations stored in interface_translations are keyed by (namespace,
 * group); without this they would stop being found and every language but
 * English and Arabic would fall back to English on these screens.
 *
 * Data only, and exactly reversible.
 */
return new class extends Migration
{
    private const GROUPS = ['club', 'business', 'explore'];

    public function up(): void
    {
        DB::table('interface_translations')
            ->whereNull('namespace')
            ->whereIn('group', self::GROUPS)
            ->update(['namespace' => 'clubs']);
    }

    public function down(): void
    {
        DB::table('interface_translations')
            ->where('namespace', 'clubs')
            ->whereIn('group', self::GROUPS)
            ->update(['namespace' => null]);
    }
};
