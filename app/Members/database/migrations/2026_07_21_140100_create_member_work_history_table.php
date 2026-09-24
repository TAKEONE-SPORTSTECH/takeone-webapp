<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Member-owned, self-managed work / coaching history (roles a person has
     * held). A null end_date means the role is current/ongoing. Editable by
     * the member, their guardian, or a super-admin — same authz as goals.
     */
    public function up(): void
    {
        Schema::create('member_work_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);                     // role / position
            $table->string('organization', 150);
            $table->string('employment_type', 40)->nullable(); // Full-time / Part-time / Volunteer / Freelance
            $table->string('location', 150)->nullable();
            $table->date('start_date');
            $table->date('end_date')->nullable();              // null = current
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'start_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_work_history');
    }
};
