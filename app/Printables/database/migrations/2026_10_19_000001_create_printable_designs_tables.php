<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Printables (app/Printables): one row per design, one per uploaded picture.
 *
 * A design belongs to an OWNER (an event, a club, a member — by morph alias,
 * never a class name; see App\Support\MorphMap) and is one KIND of printable.
 * `slot` separates several designs of one kind for one owner (a certificate's
 * participation and podium); '' when the kind has one design per owner.
 *
 * The page itself is `document` — pages and elements, sanitised by the server
 * on every save — and `revision` lets two editors find out about each other
 * instead of silently overwriting.
 *
 * Purely additive: no existing table is touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printable_designs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('kind', 40);
            $table->string('owner_type', 60);
            $table->unsignedBigInteger('owner_id');
            $table->string('slot', 40)->default('');
            $table->string('title', 160)->nullable();
            $table->string('format', 30);
            $table->string('orientation', 10)->default('portrait');
            $table->decimal('width_mm', 7, 2);
            $table->decimal('height_mm', 7, 2);
            $table->longText('document');
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['kind', 'owner_type', 'owner_id', 'slot'], 'printable_designs_owner_unique');
            $table->index(['owner_type', 'owner_id']);
        });

        Schema::create('printable_assets', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            // No cascade: the files go first (CLAUDE.md → Delete Files Before Records).
            $table->foreignId('design_id')->constrained('printable_designs');
            $table->string('disk', 20)->default('local');
            $table->string('path', 255);
            $table->string('mime', 40);
            $table->unsignedInteger('width')->default(0);
            $table->unsignedInteger('height')->default(0);
            $table->unsignedInteger('bytes')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printable_assets');
        Schema::dropIfExists('printable_designs');
    }
};
