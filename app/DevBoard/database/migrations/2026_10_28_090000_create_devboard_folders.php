<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Folders for the notebook (owner, 27 Oct 2026: "I need the notes to have
 * folders so I keep notes inside folders"). A folder may sit inside another,
 * the way Trilium's tree does. A note is in one folder or none. Additive only:
 * every existing note stays where it is (in no folder).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('devboard_folders')) {
            Schema::create('devboard_folders', function (Blueprint $t) {
                $t->id();
                $t->uuid('uuid')->unique();
                $t->foreignId('parent_id')->nullable()->constrained('devboard_folders')->nullOnDelete();
                $t->string('name', 80);
                $t->string('color', 9)->default('#7c6bf5');
                $t->double('position');
                $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();
                $t->index(['parent_id', 'position']);
            });
        }

        if (! Schema::hasColumn('devboard_cards', 'folder_id')) {
            Schema::table('devboard_cards', function (Blueprint $t) {
                $t->foreignId('folder_id')->nullable()->after('lane_id')->constrained('devboard_folders')->nullOnDelete();
                $t->index(['folder_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('devboard_cards', 'folder_id')) {
            Schema::table('devboard_cards', function (Blueprint $t) {
                $t->dropConstrainedForeignId('folder_id');
            });
        }
        Schema::dropIfExists('devboard_folders');
    }
};
