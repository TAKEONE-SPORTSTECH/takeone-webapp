<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make user_notifications self-contained so non-club events (follows, likes,
 * comments, billing) can create notifications without a ClubNotification.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->string('type')->nullable()->after('id');
            $table->string('title')->nullable()->after('type');
            $table->text('body')->nullable()->after('title');
            $table->string('action_url')->nullable()->after('body');
            $table->string('icon')->nullable()->after('action_url');
            $table->foreignId('actor_user_id')->nullable()->after('user_id');
        });

        // Club-broadcast linkage is now optional (self-contained rows leave it null).
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->unsignedBigInteger('club_notification_id')->nullable()->change();
            $table->unsignedBigInteger('tenant_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropColumn(['type', 'title', 'body', 'action_url', 'icon', 'actor_user_id']);
        });
    }
};
