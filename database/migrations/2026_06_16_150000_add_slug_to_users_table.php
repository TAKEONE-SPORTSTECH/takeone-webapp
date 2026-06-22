<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'slug')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('slug')->nullable()->unique()->after('uuid');
            });
        }

        // Backfill unique slugs for existing users (newest first is irrelevant;
        // generateUniqueSlug checks the DB so collisions get a -N suffix).
        User::withTrashed()->whereNull('slug')->orderBy('id')->get()->each(function (User $user) {
            $user->slug = User::generateUniqueSlug($user->full_name ?: $user->name ?: 'member', $user->id);
            $user->saveQuietly();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
