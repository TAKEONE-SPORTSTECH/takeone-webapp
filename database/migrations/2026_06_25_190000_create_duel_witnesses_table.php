<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duel_witnesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('duel_id')->constrained('duels')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // if the witness is a platform user
            $table->string('name');
            $table->foreignId('added_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->index('duel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duel_witnesses');
    }
};
