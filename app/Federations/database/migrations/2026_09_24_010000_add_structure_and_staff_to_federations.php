<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Federations, step 1 of wiring the designed dashboard: the federation TREE
 * (up to three levels, each body paying its own bill or its parent's), its
 * region settings, and the staff who run it.
 *
 * Purely additive — new nullable columns on `federations`, one new table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('federations', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('uuid')->constrained('federations')->nullOnDelete();
            $table->string('payer', 10)->default('self')->after('parent_id');   // self | parent
            $table->json('sports')->nullable()->after('sport');                  // ["Karate", …]
            $table->string('logo_shape', 10)->default('circle')->after('logo');  // circle | rect
            $table->string('timezone', 64)->nullable()->after('country');
            $table->string('currency', 3)->nullable()->after('timezone');
            $table->timestamp('suspended_at')->nullable()->after('approved_by');
        });

        // Who runs a federation. The owner is always a manager without a row;
        // a row with `sports` set limits that person to those sports.
        Schema::create('federation_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('federation_id')->constrained('federations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 80)->nullable();
            $table->json('sports')->nullable();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['federation_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_staff');

        Schema::table('federations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['payer', 'sports', 'logo_shape', 'timezone', 'currency', 'suspended_at']);
        });
    }
};
