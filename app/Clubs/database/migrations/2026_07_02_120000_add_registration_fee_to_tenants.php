<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // One-time registration fee, charged to first-time members ALONGSIDE the
            // enrollment fee (they are two separate line items). A package may
            // override this club-wide value via club_packages.registration_fee.
            $table->decimal('registration_fee', 10, 2)->default(0)->after('enrollment_fee');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('registration_fee');
        });
    }
};
