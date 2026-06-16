<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // WhatsApp-style: edited messages keep their row and show an "edited"
            // marker; deleted messages keep their row as a tombstone ("This
            // message was deleted") rather than vanishing, so both sides stay
            // in sync. Not Laravel SoftDeletes — we WANT deleted rows visible.
            $table->timestamp('edited_at')->nullable()->after('body');
            $table->timestamp('deleted_at')->nullable()->after('edited_at');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['edited_at', 'deleted_at']);
        });
    }
};
