<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * TakeNote for friends (owner, 28 Oct 2026: "I want my friends from around
 * the world to use it, but don't share my notes with them — let them have
 * their own"). Each person gets a NOTEBOOK of their own; every card, folder,
 * lane and label belongs to exactly one. Everything below a card (its text,
 * pictures, clips, files, comments, steps) belongs to the card's notebook.
 *
 * Additive: one new table, one nullable column on four tables. Every row that
 * exists today goes into the MAIN notebook — the owner's, which their listed
 * board members keep sharing exactly as before.
 */
return new class extends Migration
{
    private const TABLES = ['devboard_cards', 'devboard_folders', 'devboard_lanes', 'devboard_labels'];

    public function up(): void
    {
        if (! Schema::hasTable('devboard_notebooks')) {
            Schema::create('devboard_notebooks', function (Blueprint $t) {
                $t->id();
                $t->uuid('uuid')->unique();
                $t->boolean('is_main')->default(false);
                $t->foreignId('owner_id')->nullable()->unique()->constrained('users')->nullOnDelete();
                $t->string('email')->nullable()->index();        // who was invited, until they open it
                $t->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
                $t->timestamp('joined_at')->nullable();
                $t->timestamp('revoked_at')->nullable();
                $t->timestamps();
            });
        }

        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'notebook_id')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->unsignedBigInteger('notebook_id')->nullable()->index();
                });
            }
        }

        $main = DB::table('devboard_notebooks')->where('is_main', true)->value('id');
        if (! $main) {
            // the owner: whoever wrote the board's first card, else whoever added its first member
            $owner = DB::table('devboard_cards')->orderBy('id')->value('created_by')
                ?? DB::table('devboard_members')->orderBy('id')->value('added_by');
            $main = DB::table('devboard_notebooks')->insertGetId([
                'uuid' => (string) Str::uuid(), 'is_main' => true, 'owner_id' => $owner,
                'joined_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        foreach (self::TABLES as $table) {
            DB::table($table)->whereNull('notebook_id')->update(['notebook_id' => $main]);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'notebook_id')) {
                Schema::table($table, function (Blueprint $t) use ($table) {
                    $t->dropIndex($table.'_notebook_id_index');
                    $t->dropColumn('notebook_id');
                });
            }
        }
        Schema::dropIfExists('devboard_notebooks');
    }
};
