<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('club_instructors', function (Blueprint $table) {
            // Lower number = higher rank (listed first).
            $table->unsignedInteger('sort_order')->default(0)->after('rating');
            $table->index(['tenant_id', 'sort_order']);
        });

        // Seed a stable initial order per club from current id order.
        foreach (DB::table('club_instructors')->select('tenant_id')->distinct()->pluck('tenant_id') as $tenantId) {
            $pos = 0;
            foreach (DB::table('club_instructors')->where('tenant_id', $tenantId)->orderBy('id')->pluck('id') as $id) {
                DB::table('club_instructors')->where('id', $id)->update(['sort_order' => $pos++]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('club_instructors', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
