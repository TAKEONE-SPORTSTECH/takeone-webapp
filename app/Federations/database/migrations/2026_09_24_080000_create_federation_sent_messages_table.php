<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Federations, step 7b: which Messenger messages were written FOR the
 * federation (sent from its dashboard). Authorship cannot be read off roles —
 * a federation's president can also run a club — so it is recorded when the
 * message is sent. Purely additive — one new table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('federation_sent_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->unique()->constrained('messages')->cascadeOnDelete();
            $table->foreignId('federation_id')->constrained('federations')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_sent_messages');
    }
};
