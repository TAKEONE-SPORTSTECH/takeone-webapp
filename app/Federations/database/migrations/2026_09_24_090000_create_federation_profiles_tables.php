<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Federations, step 8: the federation's own record — what the platform needs
 * to know about a governing body before it runs one, takes money for one, and
 * calls one verified.
 *
 * `federations` keeps what the dashboard already reads (name, short name,
 * country, sports, owner, branding, time zone, currency). Everything else is
 * one `federation_profiles` row per federation — contact and the HQ pin,
 * registration, tax and invoicing, the bank account clubs pay into, officers
 * and board, affiliations — and `federation_documents` holds the files that
 * back it up (certificate, statutes, recognition letters), private, served
 * only through an authorising controller.
 *
 * Purely additive — two new tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('federation_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('federation_id')->unique()->constrained('federations')->cascadeOnDelete();

            // Contact & HQ
            $table->string('email', 191)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('office_hours', 120)->nullable();
            $table->string('website', 255)->nullable();
            $table->json('socials')->nullable();
            $table->unsignedSmallInteger('founded_year')->nullable();
            $table->json('languages')->nullable();
            $table->string('address', 300)->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            // Legal & registration
            $table->string('registration_number', 60)->nullable();
            $table->string('registration_authority', 150)->nullable();

            // Tax & invoicing
            $table->string('tax_number', 40)->nullable();
            $table->string('invoice_name', 150)->nullable();
            $table->string('invoice_address', 300)->nullable();

            // The account clubs pay into
            $table->string('bank_account_name', 150)->nullable();
            $table->string('bank_name', 150)->nullable();
            $table->string('iban', 34)->nullable();
            $table->string('swift', 11)->nullable();

            // Governance & affiliations
            $table->json('officers')->nullable();   // role => {name, email, phone}
            $table->json('board')->nullable();      // [{name, role}]
            $table->string('international_federation', 150)->nullable();
            $table->boolean('olympic_recognised')->default(false);

            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('federation_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('federation_id')->constrained('federations')->cascadeOnDelete();
            $table->string('kind', 40);              // registration_certificate | statutes | international_letter | olympic_letter
            $table->string('path');                  // private disk, generated name
            $table->string('original_name', 190)->nullable();   // untrusted, display only
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['federation_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('federation_documents');
        Schema::dropIfExists('federation_profiles');
    }
};
