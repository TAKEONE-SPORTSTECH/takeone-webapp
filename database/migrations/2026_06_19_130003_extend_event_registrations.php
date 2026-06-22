<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A registration is a participant (takes part) or a spectator (ticket to
     * watch). `paid` tracks the manual proof-of-payment workflow; `category_id`
     * links a participant to a tournament weight category.
     */
    public function up(): void
    {
        Schema::table('club_event_registrations', function (Blueprint $table) {
            $table->string('role')->default('participant')->after('user_id'); // participant | spectator
            $table->boolean('paid')->default(false)->after('status');
            $table->foreignId('category_id')->nullable()->after('paid')
                ->constrained('event_categories')->nullOnDelete();
            $table->string('meta')->nullable()->after('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('club_event_registrations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['role', 'paid', 'meta']);
        });
    }
};
