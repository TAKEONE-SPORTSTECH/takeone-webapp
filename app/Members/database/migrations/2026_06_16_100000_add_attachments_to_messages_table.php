<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Durable chat attachments: the file is stored encrypted-at-rest on a
            // private disk and served through an authorised route. It auto-expires
            // (attachment_expires_at); a scheduled prune deletes the bytes and
            // leaves the row so both sides render an "attachment expired" note.
            $table->string('attachment_path')->nullable()->after('body');
            $table->string('attachment_name')->nullable()->after('attachment_path');
            $table->string('attachment_mime', 120)->nullable()->after('attachment_name');
            $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_mime');
            $table->string('attachment_kind', 10)->nullable()->after('attachment_size'); // image|file
            $table->timestamp('attachment_expires_at')->nullable()->after('attachment_kind');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn([
                'attachment_path', 'attachment_name', 'attachment_mime',
                'attachment_size', 'attachment_kind', 'attachment_expires_at',
            ]);
        });
    }
};
