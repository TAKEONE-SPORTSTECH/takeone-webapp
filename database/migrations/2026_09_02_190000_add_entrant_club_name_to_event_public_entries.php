<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The club a public entrant names when it is NOT on this platform.
 *
 * `representing_tenant_id` already carries the club they compete for when that
 * club is a tenant here — which is the case worth having, because the entry
 * list, the draw and the flag beside their name all read it, and the club can
 * disown a claim it did not make. But a stranger arriving from an Instagram
 * link often trains somewhere that has never heard of us, and refusing to
 * record the name means the organiser gets an entry with no idea who sent it.
 *
 * So: one nullable column beside the foreign key, never instead of it. A
 * picked club fills the FK and leaves this null; a typed one fills this and
 * leaves the FK null. Purely additive — nothing reads it unless it is set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_public_entries', function (Blueprint $table) {
            $table->string('club_name', 120)->nullable()->after('representing_tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('event_public_entries', function (Blueprint $table) {
            $table->dropColumn('club_name');
        });
    }
};
