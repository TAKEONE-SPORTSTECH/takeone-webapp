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
        Schema::table('skill_acquisitions', function (Blueprint $table) {
            // Add start and end dates for skill training
            $table->date('start_date')->nullable()->after('duration_months');
            $table->date('end_date')->nullable()->after('start_date');

            // Link to package, activity, and instructor
            $table->foreignId('package_id')->nullable()->after('club_affiliation_id')->constrained('club_packages')->nullOnDelete();
            $table->foreignId('activity_id')->nullable()->after('package_id')->constrained('club_activities')->nullOnDelete();
            $table->foreignId('instructor_id')->nullable()->after('activity_id')->constrained('club_instructors')->nullOnDelete();

            // Add notes about the skill acquisition
            $table->text('notes')->nullable()->after('proficiency_level');

            // Add indexes
            $table->index('start_date');
            $table->index('end_date');
            $table->index('package_id');
            $table->index('instructor_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('skill_acquisitions', function (Blueprint $table) {
            $table->dropForeign(['package_id']);
            $table->dropForeign(['activity_id']);
            $table->dropForeign(['instructor_id']);

            $table->dropIndex(['start_date']);
            $table->dropIndex(['end_date']);
            $table->dropIndex(['package_id']);
            $table->dropIndex(['instructor_id']);

            $table->dropColumn([
                'start_date',
                'end_date',
                'package_id',
                'activity_id',
                'instructor_id',
                'notes'
            ]);
        });
    }
};
