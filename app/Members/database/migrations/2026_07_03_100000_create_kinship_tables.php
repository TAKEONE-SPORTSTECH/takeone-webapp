<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Family-tree (kinship) layer.
 *
 * A "person" is a human being in the tree — alive or dead, app user or not.
 * Most persons will never have a login, which is what lets the tree go
 * unlimited depth (ancestors who predate the platform).
 *
 * Only two PRIMITIVE edge types are stored:
 *   - parent_of   (person_parent_links)
 *   - spouse/union (person_unions)
 * Every other relationship (grandparent, sibling, uncle, cousin, in-law…) is
 * DERIVED by walking the graph — never stored — so the data can never
 * contradict itself.
 *
 * This layer is ADDITIVE. The existing `user_relationships` table (guardianship
 * / billing) is left untouched and keeps serving the operational concern of
 * "who manages/pays for whom".
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---------------------------------------------------------------
        // persons — a node in the family tree (a human, not an account)
        // ---------------------------------------------------------------
        Schema::create('persons', function (Blueprint $table) {
            $table->id();

            // Optional link to a real account. Nullable + unique: a person may
            // exist with no login (deceased ancestor, baby); at most one account.
            $table->foreignId('user_id')->nullable()->unique()
                  ->constrained('users')->nullOnDelete();

            $table->string('full_name');
            $table->enum('gender', ['m', 'f'])->nullable();
            $table->date('birth_date')->nullable();
            $table->date('death_date')->nullable();
            $table->boolean('is_deceased')->default(false);
            $table->string('photo')->nullable();
            $table->text('notes')->nullable();

            // Who vouches for this node (authenticity for account-less persons).
            $table->foreignId('created_by_user_id')->nullable()
                  ->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index('full_name');
            $table->index('created_by_user_id');
        });

        // ---------------------------------------------------------------
        // person_parent_links — the "parent_of" edge (tree backbone)
        // ---------------------------------------------------------------
        Schema::create('person_parent_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_person_id')->constrained('persons')->cascadeOnDelete();
            $table->foreignId('child_person_id')->constrained('persons')->cascadeOnDelete();

            // Authenticity of the claim itself.
            $table->enum('status', ['pending', 'confirmed', 'rejected'])->default('pending');

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();

            $table->timestamps();

            // A given parent→child fact can exist only once.
            $table->unique(['parent_person_id', 'child_person_id']);
            $table->index('child_person_id');
        });

        // ---------------------------------------------------------------
        // person_unions — the "spouse/partner" edge
        // Stored normalised (low id / high id) so (A,B) == (B,A) and the
        // unique index actually prevents duplicates regardless of order.
        // ---------------------------------------------------------------
        Schema::create('person_unions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('person_low_id')->constrained('persons')->cascadeOnDelete();
            $table->foreignId('person_high_id')->constrained('persons')->cascadeOnDelete();

            // Authenticity of the link.
            $table->enum('status', ['pending', 'confirmed', 'rejected'])->default('pending');
            // State of the relationship over time.
            $table->enum('state', ['married', 'partner', 'engaged', 'divorced', 'widowed'])->default('married');

            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();

            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();

            $table->timestamps();

            $table->unique(['person_low_id', 'person_high_id']);
            $table->index('person_high_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('person_unions');
        Schema::dropIfExists('person_parent_links');
        Schema::dropIfExists('persons');
    }
};
