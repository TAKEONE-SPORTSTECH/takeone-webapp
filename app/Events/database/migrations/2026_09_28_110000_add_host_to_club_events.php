<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * An event's HOST is not always a club (2026-09-28).
 *
 * A federation runs competitions too, and a federation is not a club. So an
 * event may now be hosted by something else, named by KIND rather than by
 * class — `host_type` is a key a module answers for through the
 * App\Support\Modules\Surfaces\HostsEvents surface ('federation' today), and
 * `host_id` is that host's own id. The Events module never names the module
 * that hosts: take the Federations module away and its events simply have no
 * host to draw.
 *
 *   tenant_id set, host_type NULL      a club's event — every existing row
 *   tenant_id NULL, host_type set      an event another module hosts
 *
 * tenant_id becomes NULLABLE for the second case. On SQLite that is a table
 * rebuild, done with foreign keys OFF: 29 tables point at club_events, and
 * dropping the old table with them on would cascade-delete their rows. The
 * data is copied, never touched.
 *
 * Additive for every existing event: it keeps its club, and host_type stays NULL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::withoutForeignKeyConstraints(function () {
            Schema::table('club_events', function (Blueprint $t) {
                $t->unsignedBigInteger('tenant_id')->nullable()->change();
            });
        });

        Schema::table('club_events', function (Blueprint $t) {
            $t->string('host_type', 32)->nullable();
            $t->unsignedBigInteger('host_id')->nullable();
            $t->index(['host_type', 'host_id']);
        });
    }

    public function down(): void
    {
        // Only reversible while every event still has a club.
        if (DB::table('club_events')->whereNull('tenant_id')->exists()) {
            throw new RuntimeException('Events without a club exist; they must be removed before tenant_id can be required again.');
        }

        Schema::table('club_events', function (Blueprint $t) {
            $t->dropIndex(['host_type', 'host_id']);
            $t->dropColumn(['host_type', 'host_id']);
        });

        Schema::withoutForeignKeyConstraints(function () {
            Schema::table('club_events', function (Blueprint $t) {
                $t->unsignedBigInteger('tenant_id')->nullable(false)->change();
            });
        });
    }
};
