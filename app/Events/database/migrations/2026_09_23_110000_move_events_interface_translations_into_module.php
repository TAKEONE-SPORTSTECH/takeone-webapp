<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * lang/<locale>/events.php moved into app/Events/resources/lang/, so its keys
 * are now read as `events::events.<key>`. The machine (and three human)
 * translations in interface_translations are keyed by (namespace, group);
 * without this every language but English and Arabic would fall back to
 * English on the event screens. Data only, and exactly reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('interface_translations')
            ->whereNull('namespace')
            ->where('group', 'events')
            ->update(['namespace' => 'events']);
    }

    public function down(): void
    {
        DB::table('interface_translations')
            ->where('namespace', 'events')
            ->where('group', 'events')
            ->update(['namespace' => null]);
    }
};
