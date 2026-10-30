<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The card tools Trello has (owner, 28 Oct 2026: "build all of them"):
 * labels you name and colour (several per card), several people per card and
 * watchers, start and due dates with a time, a reminder and a "complete" tick,
 * colour covers in two sizes, archive, templates, and reactions on comments.
 * Additive only: every existing card keeps what it had — its one person
 * becomes its first member, its priority becomes a label.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('devboard_labels')) {
            Schema::create('devboard_labels', function (Blueprint $t) {
                $t->id();
                $t->uuid('uuid')->unique();
                $t->string('name', 40)->nullable();
                $t->string('color', 9);
                $t->double('position');
                $t->timestamps();
            });
            // Trello's starting six — the three old priorities named, the rest left to name
            $now = now();
            foreach ([['Urgent', '#c9372c'], ['High', '#e2770f'], ['Low', '#4bce97'], [null, '#f5cd47'], [null, '#9f8fef'], [null, '#579dff']] as $i => [$name, $color]) {
                DB::table('devboard_labels')->insert(['uuid' => (string) Str::uuid(), 'name' => $name, 'color' => $color, 'position' => ($i + 1) * 1024, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        if (! Schema::hasTable('devboard_card_label')) {
            Schema::create('devboard_card_label', function (Blueprint $t) {
                $t->foreignId('card_id')->constrained('devboard_cards')->cascadeOnDelete();
                $t->foreignId('label_id')->constrained('devboard_labels')->cascadeOnDelete();
                $t->primary(['card_id', 'label_id']);
            });
            $byName = DB::table('devboard_labels')->whereNotNull('name')->pluck('id', 'name');
            foreach (['urgent' => 'Urgent', 'high' => 'High', 'low' => 'Low'] as $priority => $name) {
                foreach (DB::table('devboard_cards')->where('priority', $priority)->pluck('id') as $cardId) {
                    DB::table('devboard_card_label')->insertOrIgnore(['card_id' => $cardId, 'label_id' => $byName[$name]]);
                }
            }
        }

        foreach (['devboard_card_members', 'devboard_card_watchers'] as $table) {
            if (! Schema::hasTable($table)) {
                Schema::create($table, function (Blueprint $t) {
                    $t->foreignId('card_id')->constrained('devboard_cards')->cascadeOnDelete();
                    $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                    $t->timestamp('created_at')->nullable();
                    $t->primary(['card_id', 'user_id']);
                });
            }
        }
        // a card's one person becomes its first member
        foreach (DB::table('devboard_cards')->whereNotNull('assignee_id')->get(['id', 'assignee_id']) as $c) {
            DB::table('devboard_card_members')->insertOrIgnore(['card_id' => $c->id, 'user_id' => $c->assignee_id, 'created_at' => now()]);
        }

        if (! Schema::hasTable('devboard_comment_reactions')) {
            Schema::create('devboard_comment_reactions', function (Blueprint $t) {
                $t->foreignId('comment_id')->constrained('devboard_comments')->cascadeOnDelete();
                $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $t->string('kind', 12);
                $t->primary(['comment_id', 'user_id', 'kind']);
            });
        }

        if (! Schema::hasColumn('devboard_cards', 'done_at')) {
            Schema::table('devboard_cards', function (Blueprint $t) {
                $t->date('start_on')->nullable()->after('due_on');
                $t->string('due_time', 5)->nullable()->after('start_on');      // "17:00", the board's local time
                $t->unsignedInteger('reminder_minutes')->nullable()->after('due_time'); // before the due moment
                $t->timestamp('reminded_at')->nullable()->after('reminder_minutes');
                $t->timestamp('done_at')->nullable()->after('reminded_at');     // Trello's "complete"
                $t->string('cover_color', 9)->nullable()->after('cover_uuid');
                $t->string('cover_size', 5)->nullable()->after('cover_color');  // half | full
                $t->timestamp('archived_at')->nullable()->after('cover_size');
                $t->boolean('is_template')->default(false)->after('archived_at');
                $t->index(['kind', 'archived_at']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('devboard_cards', 'done_at')) {
            Schema::table('devboard_cards', function (Blueprint $t) {
                $t->dropIndex(['kind', 'archived_at']);
                $t->dropColumn(['start_on', 'due_time', 'reminder_minutes', 'reminded_at', 'done_at', 'cover_color', 'cover_size', 'archived_at', 'is_template']);
            });
        }
        foreach (['devboard_comment_reactions', 'devboard_card_watchers', 'devboard_card_members', 'devboard_card_label', 'devboard_labels'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
