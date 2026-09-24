<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-member opt-out for event notifications.
 *
 * Two separate switches on purpose — they are different appetites:
 *  - announcements: "a championship near you is open for entry" (broadcast,
 *    can reach a whole country, the one people will want to silence)
 *  - reminders: "your weigh-in is tomorrow", "you're up next on Mat 2"
 *    (about events they already joined — almost nobody wants these off)
 *
 * Default on, so existing members keep today's behaviour; the resolver filters
 * on these before a single row is written.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('notify_event_announcements')->default(true)->after('is_discoverable');
            $table->boolean('notify_event_reminders')->default(true)->after('notify_event_announcements');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notify_event_announcements', 'notify_event_reminders']);
        });
    }
};
