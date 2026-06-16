<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Array of identity documents: [{type, number, file_path, uploaded_at}]
            $table->json('documents')->nullable()->after('addresses');
            // Array of emergency contacts: [{phone_code, phone, name, relationship}]
            $table->json('emergency_contacts')->nullable()->after('documents');
            // Chronic health conditions with timestamps: [{condition, noted_at, notes}]
            $table->json('health_conditions')->nullable()->after('emergency_contacts');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['documents', 'emergency_contacts', 'health_conditions']);
        });
    }
};
