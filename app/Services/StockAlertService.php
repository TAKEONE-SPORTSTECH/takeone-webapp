<?php

namespace App\Services;

use App\Models\ClubProduct;
use App\Models\StockAlertState;
use App\Models\UserNotification;

/**
 * Decides when to nag a club owner about a low-stock item.
 *
 * A product that stays at/under its threshold re-alerts at most once every 24h
 * (tracked on stock_alert_states.last_notified_at) until it is restocked above the
 * threshold — which resets the clock so the next dip alerts immediately. An owner
 * can mute a single item indefinitely. The alert itself is a `type=stock`
 * UserNotification (bell + MQTT + the persistent centered card).
 */
class StockAlertService
{
    /** Is the product (or any of its variants) at/under a configured threshold? */
    public function isLow(ClubProduct $product): bool
    {
        $t = $product->low_stock_alert;
        if ($product->quantity !== null && $t !== null && $t > 0 && $product->quantity <= $t) {
            return true;
        }

        return $this->variants($product)->contains(
            fn ($v) => $v->quantity !== null && $v->low_stock_alert !== null && $v->low_stock_alert > 0 && $v->quantity <= $v->low_stock_alert
        );
    }

    /** The number to surface on the alert — the lowest at-risk stock count. */
    public function remaining(ClubProduct $product): ?int
    {
        $candidates = [];
        $t = $product->low_stock_alert;
        if ($product->quantity !== null && $t !== null && $t > 0 && $product->quantity <= $t) {
            $candidates[] = (int) $product->quantity;
        }
        foreach ($this->variants($product) as $v) {
            if ($v->quantity !== null && $v->low_stock_alert !== null && $v->low_stock_alert > 0 && $v->quantity <= $v->low_stock_alert) {
                $candidates[] = (int) $v->quantity;
            }
        }

        return $candidates ? min($candidates) : ($product->quantity !== null ? (int) $product->quantity : null);
    }

    /**
     * Alert the owner if this item is low, not muted, has no still-unread alert
     * pending, and wasn't alerted in the last 24h. Restocked items reset the clock.
     * Returns true only when a fresh alert was actually sent.
     */
    public function maybeAlert(ClubProduct $product): bool
    {
        $ownerId = $product->tenant?->owner_user_id;
        if (! $ownerId) {
            return false;
        }

        if (! $this->isLow($product)) {
            // Back above threshold — clear the nag clock so a later dip fires at once.
            StockAlertState::where('tenant_id', $product->tenant_id)
                ->where('club_product_id', $product->id)
                ->whereNotNull('last_notified_at')
                ->update(['last_notified_at' => null]);

            return false;
        }

        $state = StockAlertState::firstOrCreate(
            ['tenant_id' => $product->tenant_id, 'club_product_id' => $product->id],
        );

        if ($state->muted_at) {
            return false;   // owner silenced this item indefinitely
        }

        // Don't stack: an alert they haven't acknowledged is still on screen.
        $pending = UserNotification::where('user_id', $ownerId)
            ->where('type', 'stock')->where('subject_id', $product->id)
            ->where('is_read', false)->exists();
        if ($pending) {
            return false;
        }

        // At most once per 24h.
        if ($state->last_notified_at && $state->last_notified_at->gt(now()->subDay())) {
            return false;
        }

        $remaining = $this->remaining($product);
        UserNotification::notifyUser((int) $ownerId, 'stock', __('market.low_stock_title_one'), [
            'tenant_id' => $product->tenant_id,
            'icon' => 'bi-exclamation-triangle-fill',
            'body' => $product->name.' · '.__('market.low_stock_units_left', ['count' => $remaining]),
            'action_url' => route('admin.club.shop', $product->tenant),
            'subject_type' => 'product',
            'subject_id' => $product->id,
        ]);

        $state->update(['last_notified_at' => now()]);

        return true;
    }

    /**
     * Called after a product is saved. If it is no longer low, clear the nag clock
     * and dismiss any low-stock alert still on the owner's screen (marks the row read
     * and pushes a live remove so the open card vanishes).
     */
    public function syncAfterRestock(ClubProduct $product): void
    {
        if ($this->isLow($product)) {
            return;
        }

        StockAlertState::where('tenant_id', $product->tenant_id)
            ->where('club_product_id', $product->id)
            ->whereNotNull('last_notified_at')
            ->update(['last_notified_at' => null]);

        $ownerId = $product->tenant?->owner_user_id;
        if (! $ownerId) {
            return;
        }

        $ids = UserNotification::where('user_id', $ownerId)
            ->where('type', 'stock')->where('subject_id', $product->id)
            ->where('is_read', false)->pluck('id');

        if ($ids->isEmpty()) {
            return;
        }

        UserNotification::whereIn('id', $ids)->update(['is_read' => true, 'read_at' => now()]);

        try {
            if (function_exists('Realtime') && Realtime()->enabled()) {
                Realtime()->publishToUser((int) $ownerId, 'notifications', ['action' => 'remove', 'ids' => $ids->all()]);
            }
        } catch (\Throwable $e) {
            // best-effort; the card is already gone on the next render
        }
    }

    /** Silence low-stock alerts for this item until explicitly unmuted. */
    public function mute(ClubProduct $product): void
    {
        StockAlertState::updateOrCreate(
            ['tenant_id' => $product->tenant_id, 'club_product_id' => $product->id],
            ['muted_at' => now()],
        );
    }

    public function unmute(ClubProduct $product): void
    {
        StockAlertState::where('tenant_id', $product->tenant_id)
            ->where('club_product_id', $product->id)
            ->update(['muted_at' => null]);
    }

    private function variants(ClubProduct $product)
    {
        return $product->relationLoaded('variants') ? $product->variants : $product->variants()->get();
    }
}
