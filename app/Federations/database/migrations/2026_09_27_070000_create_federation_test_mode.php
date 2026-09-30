<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Test mode for a federation event (App\Federations\Support\FederationTestMode).
 *
 * `federation_events.test_mode_at` — when test mode was switched on; NULL = off.
 * `federation_test_records` — the ledger: every row created while an event is
 * in test mode ('created'), and the pre-test copy of every real row it changed
 * ('snapshot'). Switching test mode off restores the snapshots and deletes the
 * created rows, so the event is left exactly as it was before the test.
 *
 * Additive only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('federation_events', function (Blueprint $t) {
            $t->timestamp('test_mode_at')->nullable();
        });

        Schema::create('federation_test_records', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('federation_event_id')->index();
            $t->string('kind', 10);                 // created | snapshot
            $t->string('table_name', 64);
            $t->string('key_name', 64)->default('id');
            $t->string('record_key', 64);
            $t->json('original')->nullable();       // snapshot: the row as it was before the test
            $t->timestamp('created_at')->nullable();
            $t->unique(['federation_event_id', 'kind', 'table_name', 'record_key'], 'fed_test_records_unique');
            $t->index(['table_name', 'record_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_test_records');
        Schema::table('federation_events', function (Blueprint $t) {
            $t->dropColumn('test_mode_at');
        });
    }
};
