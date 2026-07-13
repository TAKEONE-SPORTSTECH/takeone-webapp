<?php

namespace App\Http\Controllers;

use App\Models\ClubProduct;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\UserNotification;
use App\Traits\StoresBase64Images;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Member shop orders. Checkout creates one order per club (tenant). No payment
 * gateway — the buyer must attach proof of payment at checkout; orders stay
 * 'pending' until a club admin verifies it and confirms.
 */
class OrderController extends Controller
{
    use StoresBase64Images;

    public function index(): View
    {
        $orders = Order::where('user_id', Auth::id())
            ->with(['items', 'tenant:id,club_name,logo,slug'])
            ->latest()
            ->get();

        return view('personal.orders', compact('orders'));
    }

    /**
     * The buyer confirms they received a fulfilled order. They may (optionally)
     * rate the seller and the products in the same step.
     */
    public function receive(Request $request, Order $order): JsonResponse
    {
        abort_unless($order->user_id === Auth::id(), 403);
        abort_unless($order->status === 'fulfilled', 422, __('shared.error'));

        $data = $request->validate([
            'seller_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'products' => ['nullable', 'array', 'max:50'],
            'products.*.id' => ['required', 'integer'],
            'products.*.rating' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        DB::transaction(function () use ($order, $data) {
            $order->update(['status' => 'received', 'received_at' => now()]);

            // Seller rating (one per order).
            if (! empty($data['seller_rating'])) {
                \App\Models\OrderReview::updateOrCreate(
                    ['order_id' => $order->id],
                    ['tenant_id' => $order->tenant_id, 'user_id' => $order->user_id, 'rating' => $data['seller_rating'], 'comment' => $data['comment'] ?? null],
                );
            }

            // Product ratings — only for products actually in this order; aggregate
            // onto the product so the market shows a real star rating.
            $orderedIds = $order->items()->pluck('club_product_id')->filter()->unique();
            foreach ($data['products'] ?? [] as $row) {
                if (! $orderedIds->contains((int) $row['id'])) {
                    continue;
                }
                $review = \App\Models\ProductReview::updateOrCreate(
                    ['order_id' => $order->id, 'club_product_id' => $row['id']],
                    ['user_id' => $order->user_id, 'rating' => $row['rating']],
                );
                // Only fold into the aggregate the first time this review is created.
                if ($review->wasRecentlyCreated && ($product = \App\Models\ClubProduct::find($row['id']))) {
                    $product->increment('rating_count');
                    $product->increment('rating_sum', $row['rating']);
                }
            }
        });

        // Tell the club the buyer confirmed receipt.
        $club = $order->tenant;
        if ($club && $club->owner_user_id) {
            UserNotification::notifyUser((int) $club->owner_user_id, 'order',
                __('market.notify_order_received', ['name' => Auth::user()->full_name, 'ref' => $order->reference]), [
                    'actor_id' => Auth::id(),
                    'tenant_id' => $club->id,
                    'icon' => 'bi-patch-check-fill',
                    'action_url' => route('admin.club.orders', $club),
                    'subject_type' => 'order',
                    'subject_id' => $order->id,
                ]);
        }

        return response()->json(['success' => true, 'message' => __('market.received_confirmed'), 'status' => 'received']);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:99'],
            'items.*.color' => ['nullable', 'string', 'max:16'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'note' => ['nullable', 'string', 'max:1000'],
            'proof' => ['required', 'string', 'starts_with:data:image'],
        ], [
            'proof.required' => __('market.proof_required'),
            'proof.starts_with' => __('market.proof_required'),
        ]);

        $user = Auth::user();

        // No gateway — store the buyer's proof of payment. Rejected if it isn't
        // a real image (the trait sniffs the bytes).
        $proofPath = $this->storeBase64Image($data['proof'], 'order-proofs/'.$user->id, 'proof_'.uniqid());
        if (! $proofPath) {
            return response()->json(['success' => false, 'message' => __('market.proof_required')], 422);
        }

        // Resolve to real, published products (price is authoritative server-side).
        $ids = collect($data['items'])->pluck('id')->unique()->all();
        $products = ClubProduct::whereIn('id', $ids)->where('status', 'published')->get()->keyBy('id');

        // Resolve chosen variants (price + stock come from the variant, not the product).
        $variantIds = collect($data['items'])->pluck('variant_id')->filter()->unique()->all();
        $variants = $variantIds
            ? \App\Models\ClubProductVariant::whereIn('id', $variantIds)->get()->keyBy('id')
            : collect();

        // Group requested lines by club.
        $byTenant = [];
        foreach ($data['items'] as $line) {
            $p = $products->get($line['id']);
            if (! $p) {
                continue;
            }

            // A variant must belong to this product and be active to be honoured.
            $variant = null;
            if (! empty($line['variant_id'])) {
                $v = $variants->get($line['variant_id']);
                if ($v && $v->club_product_id === $p->id && $v->is_active) {
                    $variant = $v;
                }
            }

            $byTenant[$p->tenant_id][] = [
                'p' => $p,
                'variant' => $variant,
                'qty' => (int) $line['qty'],
                'color' => $line['color'] ?? null,
            ];
        }

        if (empty($byTenant)) {
            return response()->json(['success' => false, 'message' => __('market.order_no_items')], 422);
        }

        $summaries = [];
        DB::transaction(function () use ($byTenant, $user, $data, $proofPath, &$summaries) {
            foreach ($byTenant as $tenantId => $lines) {
                $club = Tenant::find($tenantId);
                $order = Order::create([
                    'tenant_id' => $tenantId,
                    'user_id' => $user->id,
                    'status' => 'pending',
                    'currency' => $club?->currency ?: 'BHD',
                    'note' => $data['note'] ?? null,
                    'payment_proof_path' => $proofPath,
                ]);

                $subtotal = 0.0;
                $hasDrop = false;
                foreach ($lines as $l) {
                    $variant = $l['variant'];
                    $unitPrice = (float) ($variant ? $variant->price : $l['p']->price);
                    $lineTotal = $unitPrice * $l['qty'];
                    $subtotal += $lineTotal;
                    // A variant carries its own fulfillment (a product can mix in-stock
                    // and dropshipped variations); fall back to the product otherwise.
                    $lineFulfillment = $variant ? ($variant->fulfillment ?: 'stock') : $l['p']->fulfillment;
                    $hasDrop = $hasDrop || $lineFulfillment === 'dropship';

                    $order->items()->create([
                        'club_product_id' => $l['p']->id,
                        'club_product_variant_id' => $variant?->id,
                        'name' => $l['p']->name,
                        'brand' => $variant?->brand ?: $l['p']->brand,
                        'image_path' => $variant?->image_path ?: $l['p']->image_path,
                        'color' => $variant?->color_hex ?: $l['color'],
                        'size' => $variant?->size,
                        'variant_label' => $variant?->label,
                        'fulfillment' => $lineFulfillment,
                        'price' => $unitPrice,
                        'qty' => $l['qty'],
                        'line_total' => $lineTotal,
                    ]);

                    // Decrement stock for held inventory (never below zero). For a
                    // variant product the stock lives on the variant.
                    if ($variant) {
                        if ($variant->quantity !== null) {
                            $variant->decrement('quantity', min($l['qty'], $variant->quantity));
                        }
                    } elseif ($l['p']->fulfillment === 'stock' && $l['p']->quantity !== null) {
                        $l['p']->decrement('quantity', min($l['qty'], $l['p']->quantity));
                    }
                }

                $order->update(['subtotal' => $subtotal, 'total' => $subtotal, 'has_dropship' => $hasDrop]);

                // Tell the club owner an order came in.
                if ($club && $club->owner_user_id) {
                    UserNotification::notifyUser((int) $club->owner_user_id, 'order',
                        $user->full_name.' placed an order', [
                            'actor_id' => $user->id,
                            'tenant_id' => $club->id,
                            'icon' => 'bi-bag-check-fill',
                            'body' => $order->reference.' · '.$club->currency.' '.number_format($subtotal, 2),
                            'action_url' => route('admin.club.orders', $club),
                            'subject_type' => 'order',
                            'subject_id' => $order->id,
                        ]);
                }

                $summaries[] = [
                    'reference' => $order->reference,
                    'club' => $club?->club_name,
                    'total' => $subtotal,
                    'currency' => $order->currency,
                ];
            }
        });

        return response()->json([
            'success' => true,
            'message' => __('market.order_placed'),
            'orders' => $summaries,
            'redirect' => route('me.orders'),
        ]);
    }
}
