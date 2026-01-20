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
        // 1. Drop the existing users table
        Schema::dropIfExists('users');

        // 2. Create a new users table with all required columns
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email')->nullable()->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('mobile')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->enum('gender', ['male', 'female']);
            $table->date('birthdate');
            $table->string('blood_type')->nullable();
            $table->string('nationality');
            $table->json('addresses');
            $table->json('social_links');
            $table->json('media_gallery');
            $table->timestamps();
        });

        // 3. Create user_relationships table
        Schema::create('user_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guardian_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('dependent_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('relationship_type'); // son, daughter, spouse, sponsor, other
            $table->boolean('is_billing_contact')->default(false);
            $table->timestamps();
        });

        // 4. Create tenants table (The Clubs)
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

        // 5. Create memberships table
        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        // 6. Create invoices table
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('status');
            $table->date('due_date');
            $table->foreignId('student_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('payer_user_id')->constrained('users')->cascadeOnDelete();
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
        Schema::dropIfExists('users');

        // Recreate the original users table
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }
};
