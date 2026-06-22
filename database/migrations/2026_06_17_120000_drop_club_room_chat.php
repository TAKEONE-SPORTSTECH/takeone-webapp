<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the Club Room Chat feature (per-club group chat). The 1:1 Messenger
 * DMs (conversations of type='direct') are untouched.
 *
 * Drops the club-room schema added by 2026_06_16_120000_add_club_room_chat and
 * purges any club-room data (type='club' conversations, their messages, hides
 * and participant rows).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Purge club-room data first (while the discriminating column still exists).
        $clubConversationIds = DB::table('conversations')->where('type', 'club')->pluck('id');

        if ($clubConversationIds->isNotEmpty()) {
            // message_hides cascades off messages.message_id; delete messages explicitly
            // in case the FK cascade isn't relied upon, then participants, then rooms.
            DB::table('messages')->whereIn('conversation_id', $clubConversationIds)->delete();
            DB::table('conversation_user')->whereIn('conversation_id', $clubConversationIds)->delete();
            DB::table('conversations')->whereIn('id', $clubConversationIds)->delete();
        }

        Schema::table('conversation_user', function (Blueprint $table) {
            $table->dropColumn(['muted', 'banned_until', 'blocked', 'left_at']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('type')->constrained()->nullOnDelete();
        });

        Schema::table('conversation_user', function (Blueprint $table) {
            $table->boolean('muted')->default(false)->after('cleared_at');
            $table->timestamp('banned_until')->nullable()->after('muted');
            $table->boolean('blocked')->default(false)->after('banned_until');
            $table->timestamp('left_at')->nullable()->after('blocked');
        });
    }
};
