<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Member-owned, self-managed list of certifications / qualifications a
     * person holds (coaching certs, first-aid, belts, courses, licenses).
     * Editable by the member, their guardian, or a super-admin — same authz
     * as goals / event-log. Optional photo/scan is stored on the public disk.
     */
    public function up(): void
    {
        Schema::create('member_certifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 150);                    // certification name
            $table->string('issuer', 150)->nullable();       // issuing organization
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('credential_id', 120)->nullable();
            $table->string('credential_url', 300)->nullable(); // public verify link (http/https only)
            $table->string('image_path')->nullable();          // optional certificate photo/scan
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'issue_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_certifications');
    }
};
