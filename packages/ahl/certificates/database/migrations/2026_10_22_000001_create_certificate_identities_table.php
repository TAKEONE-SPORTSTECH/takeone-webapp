<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1.2 — additive. A certificate's QR code and number belong to WHAT it certifies
 * (this holder, this context, this kind, this source), not to the row that
 * happens to carry them. Once a certificate has been issued, its token and number
 * are remembered here under that identity, so a certificate deleted and drafted
 * again for the same thing comes back with the same QR — the one already printed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificate_identities', function (Blueprint $table) {
            $table->id();
            $table->string('identity', 64)->unique();   // sha256 of the identity fields
            $table->string('verify_token');
            $table->string('certificate_no');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificate_identities');
    }
};
