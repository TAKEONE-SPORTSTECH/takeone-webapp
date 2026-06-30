<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot the joining fee charged for THIS enrollment (frozen at registration
     * time) and a group id that ties together every enrollment created in a single
     * family/group registration so combined payment + per-person approval can work.
     */
    public function up(): void
    {
        Schema::table('club_member_subscriptions', function (Blueprint $table) {
            // Registration fee charged with this enrollment (0 for returning members
            // and for secondary packages within the same registration).
            $table->decimal('registration_fee', 10, 2)->default(0)->after('amount_due');
            // Groups all enrollments from one family/group submission.
            $table->string('registration_group_id')->nullable()->after('registration_fee');
            $table->index('registration_group_id');
        });
    }

    public function down(): void
    {
        Schema::table('club_member_subscriptions', function (Blueprint $table) {
            $table->dropIndex(['registration_group_id']);
            $table->dropColumn(['registration_fee', 'registration_group_id']);
        });
    }
};
