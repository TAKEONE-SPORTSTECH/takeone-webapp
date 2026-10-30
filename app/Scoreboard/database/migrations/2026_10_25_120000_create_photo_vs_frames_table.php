<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * How a person's photo is PLACED on the Karate VS screen — never cropped
 * (owner, 2026-10-25: "adjust it in the VS screen, no cropping, for each
 * person"). Keyed by the photo's storage path, because the framing belongs to
 * that picture: a person's photo has one home (users.profile_picture /
 * club_event_registrations.photo name the same file), so every entry that
 * shows it shows it the same way, and a new photo starts unframed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('photo_vs_frames', function (Blueprint $table) {
            $table->id();
            $table->string('path', 500)->unique();
            $table->decimal('zoom', 5, 3)->default(1);      // 1 = fills the panel's width
            $table->decimal('pos_x', 6, 3)->default(50);    // background-position, %
            $table->decimal('pos_y', 6, 3)->default(12);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('photo_vs_frames');
    }
};
