<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The organiser's certificate choices for one event (App\Events\Support\EventCertificates):
 * which kinds it issues and when drafts are made. The certificates themselves live in the
 * package's `certificates` table, keyed to the event as their context.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_certificate_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->unique()->constrained('club_events')->cascadeOnDelete();
            $table->boolean('participation')->default(true);
            $table->boolean('podium')->default(true);
            // division: as each division's podium is decided · event: when the event is completed · manual
            $table->string('trigger', 12)->default('division');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_certificate_settings');
    }
};
