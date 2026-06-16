<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_user', function (Blueprint $table) {
            // "Delete chat" (per user, WhatsApp-style): hides the conversation and
            // its history from THIS user only. It reappears if a newer message
            // arrives; the other participant is unaffected.
            $table->timestamp('cleared_at')->nullable()->after('last_read_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_user', function (Blueprint $table) {
            $table->dropColumn('cleared_at');
        });
    }
};
