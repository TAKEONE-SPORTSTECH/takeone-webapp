<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An athlete deleted after they fought keeps their place in the draw as
 * "Deleted athlete": the record stays (the bracket names it by TakeOne ID),
 * the person is gone. This marks those records. Additive and nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('federation_athletes', 'anonymised_at')) {
            Schema::table('federation_athletes', function (Blueprint $table) {
                $table->timestamp('anonymised_at')->nullable()->after('nationality');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('federation_athletes', 'anonymised_at')) {
            Schema::table('federation_athletes', function (Blueprint $table) {
                $table->dropColumn('anonymised_at');
            });
        }
    }
};
