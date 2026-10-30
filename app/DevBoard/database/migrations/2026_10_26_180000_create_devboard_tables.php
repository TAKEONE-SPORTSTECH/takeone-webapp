<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Margin, the development board. Every table is new and owned by app/DevBoard;
 * nothing existing is touched.
 *
 * `position` is a fractional key (a double): moving a card writes ONE row — the
 * new value sits between its two neighbours — instead of renumbering every row
 * after it. The service rebalances a lane when two neighbours get too close.
 *
 * Lanes are NOT seeded here: Services\Board creates the default set (from
 * config/devboard.php) the first time the board opens with none.
 *
 * `version` on cards and items is optimistic concurrency: two people saving the
 * same note see a conflict instead of one silently overwriting the other.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('devboard_members')) {
            Schema::create('devboard_members', function (Blueprint $t) {
                $t->id();
                $t->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $t->string('ink', 9);
                $t->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('devboard_lanes')) {
            Schema::create('devboard_lanes', function (Blueprint $t) {
                $t->id();
                $t->uuid('uuid')->unique();
                $t->string('name', 60);
                $t->string('color', 9);
                $t->double('position');
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('devboard_cards')) {
            Schema::create('devboard_cards', function (Blueprint $t) {
                $t->id();
                $t->uuid('uuid')->unique();
                $t->foreignId('lane_id')->constrained('devboard_lanes')->restrictOnDelete();
                $t->string('title', 200);
                $t->string('priority', 8)->default('normal');
                $t->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
                $t->date('due_on')->nullable();
                $t->string('source_url', 500)->nullable();
                $t->double('position');
                $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $t->unsignedInteger('version')->default(1);
                $t->timestamps();
                $t->softDeletes();
                $t->index(['lane_id', 'position']);
            });
        }

        if (! Schema::hasTable('devboard_items')) {
            Schema::create('devboard_items', function (Blueprint $t) {
                $t->id();
                $t->uuid('uuid')->unique();
                $t->foreignId('card_id')->constrained('devboard_cards')->cascadeOnDelete();
                $t->string('type', 12);
                $t->double('position');
                $t->text('body')->nullable();
                $t->string('path', 300)->nullable();
                $t->string('preview_path', 300)->nullable();
                $t->longText('strokes')->nullable();
                $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $t->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $t->unsignedInteger('version')->default(1);
                $t->timestamps();
                $t->softDeletes();
                $t->index(['card_id', 'position']);
            });
        }

        if (! Schema::hasTable('devboard_steps')) {
            Schema::create('devboard_steps', function (Blueprint $t) {
                $t->id();
                $t->uuid('uuid')->unique();
                $t->foreignId('item_id')->constrained('devboard_items')->cascadeOnDelete();
                $t->string('text', 500);
                $t->timestamp('done_at')->nullable();
                $t->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
                $t->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
                $t->date('due_on')->nullable();
                $t->unsignedSmallInteger('pin_no')->nullable();
                $t->double('position');
                $t->timestamps();
                $t->index(['item_id', 'position']);
            });
        }

        if (! Schema::hasTable('devboard_comments')) {
            Schema::create('devboard_comments', function (Blueprint $t) {
                $t->id();
                $t->uuid('uuid')->unique();
                $t->foreignId('card_id')->constrained('devboard_cards')->cascadeOnDelete();
                $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $t->text('body');
                $t->timestamps();
                $t->softDeletes();
            });
        }

        if (! Schema::hasTable('devboard_activity')) {
            Schema::create('devboard_activity', function (Blueprint $t) {
                $t->id();
                $t->foreignId('card_id')->constrained('devboard_cards')->cascadeOnDelete();
                $t->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $t->string('action', 40);
                $t->json('meta')->nullable();
                $t->timestamp('created_at')->nullable();
                $t->index(['card_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        foreach (['devboard_activity', 'devboard_comments', 'devboard_steps', 'devboard_items', 'devboard_cards', 'devboard_lanes', 'devboard_members'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
