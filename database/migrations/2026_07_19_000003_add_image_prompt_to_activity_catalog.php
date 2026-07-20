<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-entry AI image prompt (used later to generate the hero poster for the
 * activity). English is enough — it's an instruction to an image model — but
 * it lives on the directory entry alongside the bilingual description.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_catalog', function (Blueprint $table) {
            $table->text('image_prompt')->nullable()->after('variants');
        });
    }

    public function down(): void
    {
        Schema::table('activity_catalog', function (Blueprint $table) {
            $table->dropColumn('image_prompt');
        });
    }
};
