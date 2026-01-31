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
        Schema::table('tenants', function (Blueprint $table) {
            // Add established_date if it doesn't exist
            if (!Schema::hasColumn('tenants', 'established_date')) {
                $table->date('established_date')->nullable()->after('description');
            }

            // Add status if it doesn't exist
            if (!Schema::hasColumn('tenants', 'status')) {
                $table->string('status')->default('active')->after('settings');
            }

            // Add public_profile_enabled if it doesn't exist
            if (!Schema::hasColumn('tenants', 'public_profile_enabled')) {
                $table->boolean('public_profile_enabled')->default(true)->after('status');
            }
        });

        Schema::table('club_bank_accounts', function (Blueprint $table) {
            // Add benefitpay_account if it doesn't exist
            if (!Schema::hasColumn('club_bank_accounts', 'benefitpay_account')) {
                $table->string('benefitpay_account')->nullable()->after('swift_code');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn(['established_date', 'status', 'public_profile_enabled']);
        });

        Schema::table('club_bank_accounts', function (Blueprint $table) {
            $table->dropColumn('benefitpay_account');
        });
    }
};
