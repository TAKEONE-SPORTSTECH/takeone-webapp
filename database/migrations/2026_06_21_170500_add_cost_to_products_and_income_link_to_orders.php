<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wire the club Shop into the financial ledger:
 *  - club_products.cost — what a unit costs the club (purchase price). Used to
 *    auto-record an expense when stock is added/restocked, and for profit.
 *  - orders.income_transaction_id — the income ClubTransaction booked when the
 *    order is confirmed; kept so we can avoid double-booking and reverse it if
 *    the order is later cancelled.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('club_products', 'cost')) {
            Schema::table('club_products', function (Blueprint $table) {
                $table->decimal('cost', 10, 2)->nullable()->after('old_price');
            });
        }

        if (! Schema::hasColumn('orders', 'income_transaction_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreignId('income_transaction_id')->nullable()->after('received_at')
                    ->constrained('club_transactions')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('income_transaction_id');
        });

        Schema::table('club_products', function (Blueprint $table) {
            $table->dropColumn('cost');
        });
    }
};
