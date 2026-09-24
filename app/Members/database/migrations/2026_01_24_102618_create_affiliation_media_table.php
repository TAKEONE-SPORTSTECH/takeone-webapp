<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('affiliation_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('club_affiliation_id')->constrained('club_affiliations')->onDelete('cascade');
            $table->enum('media_type', ['certificate', 'photo', 'video', 'document']);
            $table->string('media_url');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['club_affiliation_id', 'media_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliation_media');
    }
};
