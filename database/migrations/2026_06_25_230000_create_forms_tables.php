<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete(); // null = platform form
            $table->string('title');
            $table->string('type', 30)->default('generic');   // registration | intake | survey | generic
            $table->text('description')->nullable();
            $table->json('schema')->nullable();    // { pages: [ { id,title,fields:[...] } ] }
            $table->json('settings')->nullable();  // { submitText, successMessage, loginRequired, oncePerUser }
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['tenant_id', 'type']);
        });

        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('data');                 // { fieldKey: value }
            $table->json('files')->nullable();    // [ { path, disk } ] for cleanup
            $table->string('ip', 45)->nullable();
            $table->timestamps();
            $table->index('form_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('forms');
    }
};
