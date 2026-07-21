<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-club "require email verification" switch. When a club turns this OFF (an
 * escape hatch for when the mail service is down), members who register through
 * that club's page are marked verified on creation and skip the verification
 * email. Defaults to TRUE so every existing club keeps verifying by default —
 * disabling it is a deliberate, admin-only choice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'require_email_verification')) {
                $table->boolean('require_email_verification')->default(true)->after('registration_requirements');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'require_email_verification')) {
                $table->dropColumn('require_email_verification');
            }
        });
    }
};
