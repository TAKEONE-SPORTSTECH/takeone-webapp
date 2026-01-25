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
            // Basic Information
            $table->string('slogan')->nullable()->after('club_name');
            $table->text('description')->nullable()->after('slogan');
            $table->decimal('enrollment_fee', 10, 2)->default(0)->after('description');
            $table->string('commercial_reg_number')->nullable()->after('enrollment_fee');
            $table->string('vat_reg_number')->nullable()->after('commercial_reg_number');
            $table->decimal('vat_percentage', 5, 2)->default(0)->after('vat_reg_number');

            // Contact Information
            $table->string('email')->nullable()->after('vat_percentage');
            $table->json('phone')->nullable()->after('email'); // {code: '+973', number: '12345678'}
            $table->string('currency', 3)->default('BHD')->after('phone'); // ISO 4217 currency code
            $table->string('timezone')->default('Asia/Bahrain')->after('currency');
            $table->string('country')->default('Bahrain')->after('timezone');
            $table->text('address')->nullable()->after('country');

            // Branding Assets
            $table->string('favicon')->nullable()->after('logo');
            $table->string('cover_image')->nullable()->after('favicon');

            // Owner Information (denormalized for quick access)
            $table->string('owner_name')->nullable()->after('owner_user_id');
            $table->string('owner_email')->nullable()->after('owner_name');

            // Settings (JSON for code prefixes and other configurations)
            $table->json('settings')->nullable()->after('gps_long');
            // Example settings structure:
            // {
            //   "member_code_prefix": "MEM",
            //   "child_code_prefix": "CHILD",
            //   "invoice_code_prefix": "INV",
            //   "receipt_code_prefix": "REC",
            //   "expense_code_prefix": "EXP",
            //   "specialist_code_prefix": "SPEC"
            // }

            // Soft Deletes
            $table->softDeletes()->after('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropColumn([
                'slogan',
                'description',
                'enrollment_fee',
                'commercial_reg_number',
                'vat_reg_number',
                'vat_percentage',
                'email',
                'phone',
                'currency',
                'timezone',
                'country',
                'address',
                'favicon',
                'cover_image',
                'owner_name',
                'owner_email',
                'settings',
            ]);
        });
    }
};
