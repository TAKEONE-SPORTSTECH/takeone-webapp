<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The memberships.status column was created as an enum limited to
     * ('active', 'inactive'), but the application uses additional statuses
     * — notably 'former' (ended membership) and 'pending'. The restrictive
     * CHECK constraint caused a 500 when ending a member's affiliation.
     * Relax it to a plain string; allowed values are enforced in app code.
     */
    public function up(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->string('status')->default('active')->change();
        });
    }

    public function down(): void
    {
        Schema::table('memberships', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive'])->default('active')->change();
        });
    }
};
