<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-package registration (joining) fee. Nullable: when null the club-wide
     * tenants.enrollment_fee is used as the fallback. The effective value is
     * snapshotted onto the member's subscription at registration time so future
     * price changes never affect historical records.
     */
    public function up(): void
    {
        Schema::table('club_packages', function (Blueprint $table) {
            $table->decimal('registration_fee', 10, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::table('club_packages', function (Blueprint $table) {
            $table->dropColumn('registration_fee');
        });
    }
};
