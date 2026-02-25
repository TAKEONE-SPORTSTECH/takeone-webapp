<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('bio')->nullable()->after('motto');
            $table->json('skills')->nullable()->after('bio');
            $table->unsignedSmallInteger('experience_years')->nullable()->after('skills');
            $table->boolean('is_personal_trainer')->default(false)->after('experience_years');
        });

        // Copy existing trainer profile data from club_instructors to users
        DB::table('club_instructors')
            ->select('user_id', 'bio', 'skills', 'experience_years')
            ->get()
            ->each(function ($row) {
                if ($row->bio || $row->skills || $row->experience_years) {
                    DB::table('users')->where('id', $row->user_id)->update([
                        'bio'              => $row->bio,
                        'skills'           => $row->skills,
                        'experience_years' => $row->experience_years,
                    ]);
                }
            });

        Schema::table('club_instructors', function (Blueprint $table) {
            $table->dropColumn(['bio', 'skills', 'experience_years']);
        });
    }

    public function down(): void
    {
        Schema::table('club_instructors', function (Blueprint $table) {
            $table->text('bio')->nullable();
            $table->json('skills')->nullable();
            $table->unsignedSmallInteger('experience_years')->nullable();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['bio', 'skills', 'experience_years', 'is_personal_trainer']);
        });
    }
};
