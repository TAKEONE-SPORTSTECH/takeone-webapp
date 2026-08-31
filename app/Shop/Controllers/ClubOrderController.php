<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Tenant;
use App\Services\FinancialService;
use App\Support\ClubView;
use App\Traits\HandlesClubAuthorization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Incoming shop orders for a club to fulfil. Manual workflow:
 * pending → confirmed → fulfilled (or cancelled).
 */
class ClubOrderController extends Controller
{
    use HandlesClubAuthorization;

    public function index(Tenant $club): View
    {
        $this->authorizeClub($club);

        $orders = Order::where('tenant_id', $club->id)
            ->with(['items', 'user:id,full_name,profile_picture,updated_at,slug'])
            ->latest()
            ->get();

        // Unseen-orders badge: how many arrived since THIS admin last opened this club's
        // orders page. Read their last-seen mark first (used to flag each order as "new"),
        // then stamp it to now so the next visit only counts newer arrivals. Per (user, club).
        $viewerId = (int) Auth::id();
        $lastSeen = DB::table('club_order_views')
            ->where('user_id', $viewerId)->where('tenant_id', $club->id)->value('seen_at');
        DB::table('club_order_views')->updateOrInsert(
            ['user_id' => $viewerId, 'tenant_id' => $club->id],
            ['seen_at' => now(), 'updated_at' => now(), 'created_at' => now()],
        );

        // 'received' is the terminal delivered state (added after this page shipped): it counts
        // as completed AND as paid revenue, mirroring syncIncome()'s paid-status set — otherwise
        // a fully completed order shows 0s across the board.
        $stats = [
            'pending' => $orders->where('status', 'pending')->count(),
            'confirmed' => $orders->where('status', 'confirmed')->count(),
            'fulfilled' => $orders->whereIn('status', ['fulfilled', 'received'])->count(),
            'revenue' => $orders->whereIn('status', ['confirmed', 'fulfilled', 'received'])->sum('total'),
        ];

        return view(ClubView::pick('orders'), compact('club', 'orders', 'stats', 'lastSeen'));
    }

    public function updateStatus(Request $request, Tenant $club, Order $order): JsonResponse
    {
        $this->authorizeClub($club);
        abort_unless($order->tenant_id === $club->id, 404);

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', Order::STATUSES)],
        ]);

        // Status change and ledger booking are atomic — if the income entry
        // can't be written, the status change rolls back too (no stuck orders).
        \Illuminate\Support\Facades\DB::transaction(function () use ($club, $order, $data) {
            $order->update(['status' => $data['status']]);
            $this->syncIncome($club, $order, app(FinancialService::class));
        });

        // Notify the buyer of the status change (bell + live MQTT push).
        $icons = ['confirmed' => 'bi-check-circle-fill', 'fulfilled' => 'bi-bag-check-fill', 'cancelled' => 'bi-x-circle-fill', 'pending' => 'bi-hourglass-split'];
        \App\Models\UserNotification::notifyUser((int) $order->user_id, 'order',
            __('market.notify_order_'.$order->status, ['ref' => $order->reference, 'club' => $club->club_name]), [
                'actor_id' => \Illuminate\Support\Facades\Auth::id(),
                'tenant_id' => $club->id,
                'icon' => $icons[$order->status] ?? 'bi-bag',
                'body' => $club->club_name.' · '.$order->reference,
                'action_url' => route('me.orders'),
                'subject_type' => 'order',
                'subject_id' => $order->id,
            ]);

        return response()->json([
            'success' => true,
            'message' => __('market.order_status_updated'),
            'status' => $order->status,
        ]);
    }

    /**
     * Book (or reverse) the order's income in the club ledger based on its
     * status. A confirmed/fulfilled/received order counts as paid income;
     * pending/cancelled does not. Idempotent — never double-books, and reverses
     * the income if the order drops back to an unpaid state.
     */
    private function syncIncome(Tenant $club, Order $order, FinancialService $financials): void
    {
        $isPaid = in_array($order->status, ['confirmed', 'fulfilled', 'received'], true);

        if ($isPaid && ! $order->income_transaction_id) {
            $transaction = $financials->recordTransaction($club, [
                'user_id' => $order->user_id,
                'type' => 'income',
                'category' => 'shop',
                'amount' => $order->total,
                'payment_method' => 'other',
                'description' => __('market.income_shop_sale', ['ref' => $order->reference]),
                'transaction_date' => now(),
                'reference_number' => $order->reference,
            ]);
            $order->update(['income_transaction_id' => $transaction->id]);
        } elseif (! $isPaid && $order->income_transaction_id) {
            \App\Models\ClubTransaction::where('id', $order->income_transaction_id)->delete();
            $order->update(['income_transaction_id' => null]);
            \App\Support\ClubCache::flushFinancials($club->id);
        }
    }
}
