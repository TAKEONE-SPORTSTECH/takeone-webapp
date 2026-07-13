<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Super-admin moderation for member posts. A hidden post is force-removed from
 * everyone's feed but kept in the DB so a super-admin can still review it (and
 * unhide it if it turns out to be fine). Distinct from a hard delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_posts', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable()->after('cover');
            $table->foreignId('hidden_by')->nullable()->after('hidden_at')
                ->constrained('users')->nullOnDelete();
            $table->index('hidden_at');
        });
    }

    public function down(): void
    {
        Schema::table('user_posts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hidden_by');
            $table->dropIndex(['hidden_at']);
            $table->dropColumn('hidden_at');
        });
    }
};
