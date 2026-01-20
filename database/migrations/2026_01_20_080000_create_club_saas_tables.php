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
        // 1. Modify the existing users table to add Club SaaS columns
        Schema::table('users', function (Blueprint $table) {
            // Add full_name column
            $table->string('full_name')->after('id');

            // Make email nullable
            $table->string('email')->nullable()->change();

            // Add mobile column (nullable)
            $table->string('mobile')->nullable()->after('email');

            // Make password nullable
            $table->string('password')->nullable()->change();

            // Add gender enum column
            $table->enum('gender', ['m', 'f'])->nullable()->after('remember_token');

            // Add birthdate date column
            $table->date('birthdate')->nullable()->after('gender');

            // Add blood_type enum column with default
            $table->enum('blood_type', [
                'A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown'
            ])->default('Unknown')->after('birthdate');

            // Add nationality string column
            $table->string('nationality')->nullable()->after('blood_type');

            // Add addresses JSON column
            $table->json('addresses')->nullable()->after('nationality');

            // Add social_links JSON column
            $table->json('social_links')->nullable()->after('addresses');

            // Add media_gallery JSON column
            $table->json('media_gallery')->nullable()->after('social_links');
        });

        // 2. Create user_relationships table (Linking Guardians to Dependents)
        Schema::create('user_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('dependent_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('relationship_type'); // son, daughter, spouse, sponsor, other
            $table->boolean('is_billing_contact')->default(false);
            $table->timestamps();
        });

        // 3. Create tenants table (The Clubs)
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('club_name');
            $table->string('slug')->unique();
            $table->string('logo')->nullable();
            $table->decimal('gps_lat', 10, 7)->nullable();
            $table->decimal('gps_long', 10, 7)->nullable();
            $table->timestamps();
        });

        // 4. Create memberships table
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        // 5. Create invoices table (Crucial for Payer logic)
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('status');
            $table->date('due_date');
            $table->foreignId('student_user_id')->constrained('users')->cascadeOnDelete(); // Who took the class
            $table->foreignId('payer_user_id')->constrained('users')->cascadeOnDelete(); // Who pays (could be a parent/sponsor)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop tables in reverse order
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('tenants');
        Schema::dropIfExists('user_relationships');

        // Revert users table modifications
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'full_name',
                'mobile',
                'gender',
                'birthdate',
                'blood_type',
                'nationality',
                'addresses',
                'social_links',
                'media_gallery',
            ]);

            // Revert email to not nullable
            $table->string('email')->nullable(false)->change();

            // Revert password to not nullable
            $table->string('password')->nullable(false)->change();
        });
    }
};
