<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Federations, step 6: the dashboard's strings table (Settings → Translations).
 *
 * The dashboard ships an English interface and a seed of Arabic / French
 * translations (resources/app/translations.seed.js). A federation's managers
 * correct and complete those for their own staff: each row here is one
 * federation's wording for one English string, laid over the seed. One
 * federation's edits never reach another's screens.
 *
 * Purely additive — one new table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('federation_strings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('federation_id')->constrained('federations')->cascadeOnDelete();
            $table->string('key', 300);            // the English string, as the interface shows it
            $table->text('ar')->nullable();
            $table->text('fr')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['federation_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_strings');
    }
};
