<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A competitor's height, on the person rather than on a measurement.
 *
 * The arena VS screen introduces two athletes with a stat line — age, height,
 * weight. Age comes from the birthdate and weight from the official weigh-in,
 * but height had nowhere to live: health_records.height exists and is the right
 * home for a TRACKED measurement (it is a dated series, alongside body fat and
 * BMI, and a member may have none, one or fifty), while what a hall screen wants
 * is the single standing fact about a person.
 *
 * So this is the profile answer to "how tall is this athlete", and the resolver
 * falls back to the most recent health record when it is not set — no data is
 * wasted and nothing is invented.
 *
 * Centimetres, integer: the screen prints "178cm" and nobody measures an athlete
 * to the millimetre. Nullable, because it is optional everywhere — an athlete
 * with no height recorded gets a stat line without one, never a made-up number.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedSmallInteger('height_cm')->nullable()->after('blood_type');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('height_cm');
        });
    }
};
