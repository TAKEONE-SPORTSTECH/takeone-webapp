<?php

namespace App\Console\Commands;

use App\Models\ClubProduct;
use App\Services\StockAlertService;
use Illuminate\Console\Command;

/**
 * Daily sweep: for every shop item that is still at/under its low-stock threshold,
 * re-alert the club owner (StockAlertService enforces the once-per-24h cadence, the
 * per-item mute, and stops once the item is restocked).
 */
class RecheckLowStock extends Command
{
    protected $signature = 'alerts:recheck-low-stock';

    protected $description = 'Re-alert club owners about items still low on stock (24h cadence, until restocked).';

    public function handle(StockAlertService $stockAlerts): int
    {
        $sent = 0;

        ClubProduct::query()
            ->where(function ($q) {
                $q->whereNotNull('low_stock_alert')
                    ->orWhereHas('variants', fn ($v) => $v->whereNotNull('low_stock_alert'));
            })
            ->with(['variants', 'tenant'])
            ->chunkById(200, function ($products) use ($stockAlerts, &$sent) {
                foreach ($products as $product) {
                    if ($stockAlerts->maybeAlert($product)) {
                        $sent++;
                    }
                }
            });

        $this->info("Low-stock re-check complete. Alerts sent: {$sent}.");

        return self::SUCCESS;
    }
}
