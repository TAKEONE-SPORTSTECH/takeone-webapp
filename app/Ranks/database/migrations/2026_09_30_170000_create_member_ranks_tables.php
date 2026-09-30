<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Ranks, held against the ACTIVITY (never the club), and their history.
 *
 * Additive. `activity_packages` gains a uuid so a ranked activity is addressed
 * by a key nobody can guess or count (Unpredictable Resource Identifiers).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('activity_packages', 'uuid')) {
            Schema::table('activity_packages', function (Blueprint $t) {
                $t->uuid('uuid')->nullable()->unique();
            });
            foreach (DB::table('activity_packages')->whereNull('uuid')->pluck('id') as $id) {
                DB::table('activity_packages')->where('id', $id)->update(['uuid' => (string) Str::uuid()]);
            }
        }

        if (! Schema::hasTable('member_ranks')) {
            Schema::create('member_ranks', function (Blueprint $t) {
                $t->id();
                $t->foreignId('activity_id')->constrained('club_activities')->cascadeOnDelete();
                $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $t->string('belt', 40);             // a value from the sport's ladder, e.g. 'blue'
                $t->string('grade', 40)->nullable(); // one of that belt's grades, e.g. '2 stripes'
                $t->date('since');
                $t->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();
                $t->unique(['activity_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('rank_promotions')) {
            Schema::create('rank_promotions', function (Blueprint $t) {
                $t->id();
                $t->foreignId('activity_id')->constrained('club_activities')->cascadeOnDelete();
                $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $t->string('from_belt', 40)->nullable();
                $t->string('from_grade', 40)->nullable();
                $t->string('to_belt', 40);
                $t->string('to_grade', 40)->nullable();
                $t->date('promoted_on');
                $t->foreignId('promoted_by')->nullable()->constrained('users')->nullOnDelete();
                $t->unsignedBigInteger('grading_id')->nullable();
                $t->string('note', 500)->nullable();
                $t->timestamps();
                $t->index(['activity_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rank_promotions');
        Schema::dropIfExists('member_ranks');
    }
};
