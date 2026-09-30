<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grading sessions — a belt test held for ONE ranked activity — and who is
 * being graded in each. Additive. A candidate who passes is promoted through
 * App\Ranks\Support\MemberRanks::set(), whose history row points back here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gradings')) {
            Schema::create('gradings', function (Blueprint $t) {
                $t->id();
                $t->uuid('uuid')->unique();
                $t->foreignId('activity_id')->constrained('club_activities')->cascadeOnDelete();
                $t->string('title', 120);
                $t->date('held_on');
                $t->string('status', 12)->default('planned');   // planned | done
                $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamp('finished_at')->nullable();
                $t->timestamps();
                $t->index(['activity_id', 'held_on']);
            });
        }

        if (! Schema::hasTable('grading_candidates')) {
            Schema::create('grading_candidates', function (Blueprint $t) {
                $t->id();
                $t->foreignId('grading_id')->constrained('gradings')->cascadeOnDelete();
                $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $t->string('target_belt', 40);
                $t->string('target_grade', 40)->nullable();
                $t->string('result', 12)->default('pending');   // pending | passed | failed | absent
                $t->string('note', 300)->nullable();
                $t->timestamps();
                $t->unique(['grading_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('grading_candidates');
        Schema::dropIfExists('gradings');
    }
};
