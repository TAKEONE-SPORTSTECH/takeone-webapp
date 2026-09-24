<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            // A club room is a Conversation with type='club' tied to a tenant.
            $table->foreignId('tenant_id')->nullable()->after('type')->constrained()->nullOnDelete();
        });

        Schema::table('conversation_user', function (Blueprint $table) {
            $table->boolean('muted')->default(false)->after('cleared_at');       // silence the tone
            $table->timestamp('banned_until')->nullable()->after('muted');       // admin timed kick
            $table->boolean('blocked')->default(false)->after('banned_until');   // admin permanent block
            $table->timestamp('left_at')->nullable()->after('blocked');          // member exited the room
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });
        Schema::table('conversation_user', function (Blueprint $table) {
            $table->dropColumn(['muted', 'banned_until', 'blocked', 'left_at']);
        });
    }
};
