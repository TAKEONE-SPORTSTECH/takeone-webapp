<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Gives self-added skills real provenance + the same verification layer as medals.
 * `activity_id`/`package_id`/`instructor_id` already exist; this adds the free-text
 * `activity_name` (off-platform clubs), a denormalized `user_id` (so a skill can be
 * owned directly — e.g. a migrated instructor certification with no affiliation),
 * makes `club_affiliation_id` nullable, and adds the shared verification columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('skill_acquisitions', function (Blueprint $table) {
            if (! Schema::hasColumn('skill_acquisitions', 'uuid')) {
                $table->uuid('uuid')->nullable()->after('id');
            }
            if (! Schema::hasColumn('skill_acquisitions', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('uuid')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('skill_acquisitions', 'activity_name')) {
                $table->string('activity_name')->nullable()->after('skill_name');
            }
            $table->string('verification_status')->default('self_reported')->after('proficiency_level');
            $table->string('verification_method')->nullable()->after('verification_status');
            $table->foreignId('verified_by_tenant_id')->nullable()->after('verification_method')->constrained('tenants')->nullOnDelete();
            $table->foreignId('verified_by_user_id')->nullable()->after('verified_by_tenant_id')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by_user_id');
            $table->string('verification_note')->nullable()->after('verified_at');
        });

        // Backfill uuid + owner (from the affiliation's member) for existing rows.
        DB::table('skill_acquisitions')->whereNull('uuid')->orderBy('id')->each(function ($row) {
            $ownerId = $row->user_id ?? DB::table('club_affiliations')->where('id', $row->club_affiliation_id)->value('member_id');
            DB::table('skill_acquisitions')->where('id', $row->id)->update([
                'uuid' => (string) Str::uuid(),
                'user_id' => $ownerId,
            ]);
        });

        Schema::table('skill_acquisitions', function (Blueprint $table) {
            $table->unique('uuid');
            $table->index(['user_id', 'verification_status']);
        });

        // A skill can now exist without a club affiliation (migrated certifications).
        Schema::table('skill_acquisitions', function (Blueprint $table) {
            $table->foreignId('club_affiliation_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('skill_acquisitions', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropIndex(['user_id', 'verification_status']);
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('verified_by_tenant_id');
            $table->dropConstrainedForeignId('verified_by_user_id');
            $table->dropColumn([
                'uuid', 'activity_name', 'verification_status',
                'verification_method', 'verified_at', 'verification_note',
            ]);
        });
    }
};
