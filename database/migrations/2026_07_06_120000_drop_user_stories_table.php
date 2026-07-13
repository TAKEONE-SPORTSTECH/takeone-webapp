<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The member "stories" (24h feed circles) feature was removed. Drop its table
 * on databases that already ran the original add_polls_and_stories migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('user_stories');
    }

    public function down(): void
    {
        // No-op — the stories feature is gone for good; nothing to restore.
    }
};
