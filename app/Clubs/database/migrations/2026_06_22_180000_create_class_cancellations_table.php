<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A cancelled (no-show) occurrence of a club class slot for a specific date. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_cancellations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_activity_id')->constrained('club_package_activities')->cascadeOnDelete();
            $table->string('slot_day', 12);
            $table->string('slot_start', 12)->nullable();
            $table->date('date');
            $table->string('reason', 300)->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['package_activity_id', 'slot_day', 'slot_start', 'date'], 'class_cancel_unique');
            $table->index('date');
        });
    }

    public function down(): void { Schema::dropIfExists('class_cancellations'); }
};
