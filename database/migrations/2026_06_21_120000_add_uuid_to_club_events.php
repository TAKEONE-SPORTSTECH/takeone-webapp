<?php

use App\Models\ClubEvent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /** Unpredictable public identifier for event URLs (instead of the sequential id). */
    public function up(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        ClubEvent::withoutGlobalScopes()->whereNull('uuid')->get()->each(function ($e) {
            $e->uuid = (string) Str::uuid();
            $e->saveQuietly();
        });

        Schema::table('club_events', function (Blueprint $table) {
            $table->unique('uuid');
        });
    }

    public function down(): void
    {
        Schema::table('club_events', function (Blueprint $table) {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
