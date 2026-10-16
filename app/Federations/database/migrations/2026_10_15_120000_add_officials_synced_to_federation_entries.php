<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the entry sync last copied from a club's named officials (team manager,
 * referee 1, referee 2) into the event — so it acts only when the CLUB changes
 * its form, and never overrides what the federation did since: renaming a
 * person, removing an appointment (2026-10-15: matching by name on every run
 * created new people and swapped them in). Additive; existing entries are
 * marked as already applied, so nothing moves on the first run after this.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('federation_entries', 'officials_synced')) {
            Schema::table('federation_entries', function (Blueprint $t) {
                $t->text('officials_synced')->nullable();
            });
        }

        DB::table('federation_entries')->whereNull('officials_synced')->orderBy('id')->each(function ($row) {
            $named = json_decode((string) $row->officials, true) ?: [];
            $applied = [];
            foreach (['mgr', 'ref1', 'ref2'] as $slot) {
                $name = trim(strip_tags((string) ($named[$slot]['name'] ?? '')));
                $applied[$slot] = \App\Federations\Support\NobodyName::is($name) ? '' : $name;
            }
            DB::table('federation_entries')->where('id', $row->id)->update(['officials_synced' => json_encode($applied, JSON_UNESCAPED_UNICODE)]);
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('federation_entries', 'officials_synced')) {
            Schema::table('federation_entries', fn (Blueprint $t) => $t->dropColumn('officials_synced'));
        }
    }
};
