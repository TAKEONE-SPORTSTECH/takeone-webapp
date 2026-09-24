<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Federations, step 7: the dashboard's messages to clubs, carried by the
 * platform Messenger.
 *
 * One Messenger conversation per affiliated club (`conversations.type =
 * 'federation'`), whose participants are that club's CURRENT admins; the
 * federation's staff write into it from the dashboard. This table says which
 * affiliation a conversation belongs to — the Messenger's own tables are not
 * touched.
 *
 * Purely additive — one new table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('federation_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('federation_club_id')->unique()->constrained('federation_clubs')->cascadeOnDelete();
            $table->foreignId('conversation_id')->unique()->constrained('conversations')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_conversations');
    }
};
