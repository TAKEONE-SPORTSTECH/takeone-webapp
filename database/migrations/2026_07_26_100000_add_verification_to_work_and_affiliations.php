<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// Extend the shared verification/attestation state to work history + club
// affiliations (matching tournament_events / skill_acquisitions), so those records
// can be club-confirmed or peer-vouched like medals and skills.
return new class extends Migration
{
    private array $tables = ['member_work_history', 'club_affiliations'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $t) use ($table) {
                if (! Schema::hasColumn($table, 'uuid')) {
                    $t->uuid('uuid')->nullable()->after('id');
                }
                if (! Schema::hasColumn($table, 'verification_status')) {
                    $t->string('verification_status')->default('self_reported');
                }
                if (! Schema::hasColumn($table, 'verification_method')) {
                    $t->string('verification_method')->nullable();
                }
                if (! Schema::hasColumn($table, 'verified_by_tenant_id')) {
                    $t->unsignedBigInteger('verified_by_tenant_id')->nullable();
                }
                if (! Schema::hasColumn($table, 'verified_by_user_id')) {
                    $t->unsignedBigInteger('verified_by_user_id')->nullable();
                }
                if (! Schema::hasColumn($table, 'verified_at')) {
                    $t->timestamp('verified_at')->nullable();
                }
                if (! Schema::hasColumn($table, 'verification_note')) {
                    $t->text('verification_note')->nullable();
                }
                if (! Schema::hasColumn($table, 'verification_announced_at')) {
                    $t->timestamp('verification_announced_at')->nullable();
                }
            });

            // Backfill uuids for existing rows so route-key resolution never sees null.
            DB::table($table)->whereNull('uuid')->orderBy('id')->each(function ($row) use ($table) {
                DB::table($table)->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
            });
        }
    }

    public function down(): void
    {
        $cols = ['verification_status', 'verification_method', 'verified_by_tenant_id',
            'verified_by_user_id', 'verified_at', 'verification_note', 'verification_announced_at'];
        foreach ($this->tables as $table) {
            foreach ($cols as $c) {
                if (Schema::hasColumn($table, $c)) {
                    Schema::table($table, fn (Blueprint $t) => $t->dropColumn($c));
                }
            }
            // uuid left in place (harmless, and other code may now rely on it).
        }
    }
};
