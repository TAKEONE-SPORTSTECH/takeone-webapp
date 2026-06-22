<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClubProduct;
use App\Models\ClubProductCategory;
use App\Models\Tenant;
use App\Services\FinancialService;
use App\Support\ClubView;
use App\Traits\HandlesClubAuthorization;
use App\Traits\StoresBase64Images;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The club Shop — products the club sells. A product is held in stock
 * (quantity tracked) or dropshipped (supplier ships to the buyer on order).
 */
class ClubShopController extends Controller
{
    use HandlesClubAuthorization, StoresBase64Images;

    /** Built-in starter categories used until the club defines its own. */
    private const DEFAULT_CATEGORIES = [
        ['key' => 'gear',      'label' => 'Gear',      'icon' => 'bi-bag'],
        ['key' => 'equipment', 'label' => 'Equipment', 'icon' => 'bi-bicycle'],
        ['key' => 'nutrition', 'label' => 'Nutrition', 'icon' => 'bi-cup-hot'],
        ['key' => 'apparel',   'label' => 'Apparel',   'icon' => 'bi-person-arms-up'],
        ['key' => 'passes',    'label' => 'Passes',    'icon' => 'bi-ticket-perforated'],
    ];

    public function shop(Tenant $club): View
    {
        $this->authorizeClub($club);

        $products = ClubProduct::where('tenant_id', $club->id)
            ->orderByDesc('sort')->latest()
            ->get()
            ->map(fn ($p) => $p->toCardArray())
            ->values()
            ->all();

        $custom = $club->productCategories()->orderBy('sort')->get(['key', 'label', 'icon']);
        $categories = $custom->isNotEmpty()
            ? $custom->map(fn ($c) => ['key' => $c->key, 'label' => $c->label, 'icon' => $c->icon])->all()
            : self::DEFAULT_CATEGORIES;

        return view(ClubView::pick('shop'), compact('club', 'products', 'categories'));
    }

    public function storeProduct(Request $request, Tenant $club): JsonResponse
    {
        $this->authorizeClub($club);

        $data = $this->validateProduct($request);

        $product = new ClubProduct(['tenant_id' => $club->id]);
        $this->fill($product, $data, $club);
        $product->save();

        // Initial stock is a purchase — record it as an expense in the ledger.
        if ($product->fulfillment === 'stock') {
            $this->recordStockCost($club, $product, (int) ($product->quantity ?? 0));
        }

        return response()->json([
            'success' => true,
            'message' => __('market.product_published'),
            'product' => $product->toCardArray(),
        ]);
    }

    public function updateProduct(Request $request, Tenant $club, ClubProduct $product): JsonResponse
    {
        $this->authorizeClub($club);
        abort_unless($product->tenant_id === $club->id, 404);

        $data = $this->validateProduct($request);

        // Remember the stock level before editing so a restock (quantity raised)
        // can be booked as an expense for the added units only.
        $wasStock    = $product->fulfillment === 'stock';
        $previousQty = (int) ($product->quantity ?? 0);

        $this->fill($product, $data, $club);
        $product->save();

        if ($product->fulfillment === 'stock') {
            $added = (int) ($product->quantity ?? 0) - ($wasStock ? $previousQty : 0);
            if ($added > 0) {
                $this->recordStockCost($club, $product, $added);
            }
        }

        return response()->json([
            'success' => true,
            'message' => __('market.product_updated'),
            'product' => $product->toCardArray(),
        ]);
    }

    public function destroyProduct(Tenant $club, ClubProduct $product): JsonResponse
    {
        $this->authorizeClub($club);
        abort_unless($product->tenant_id === $club->id, 404);

        $product->delete();

        return response()->json(['success' => true, 'message' => __('shared.deleted')]);
    }

    public function storeCategory(Request $request, Tenant $club): JsonResponse
    {
        $this->authorizeClub($club);

        $data = $request->validate([
            'label' => ['required', 'string', 'max:30'],
            'key'   => ['nullable', 'string', 'max:30'],
            'icon'  => ['nullable', 'string', 'max:40'],
        ]);

        $key = \Illuminate\Support\Str::slug($data['key'] ?: $data['label']) ?: 'cat';

        $category = ClubProductCategory::updateOrCreate(
            ['tenant_id' => $club->id, 'key' => $key],
            ['label' => $data['label'], 'icon' => $data['icon'] ?? 'bi-grid-1x2'],
        );

        return response()->json([
            'success'  => true,
            'message'  => __('market.category_added'),
            'category' => ['key' => $category->key, 'label' => $category->label, 'icon' => $category->icon],
        ]);
    }

    // ===================== helpers =====================

    /**
     * Record the cost of acquiring stock as an expense in the club ledger.
     * Expense = units × cost-per-unit. Skipped when no per-unit cost is set.
     */
    private function recordStockCost(Tenant $club, ClubProduct $product, int $units): void
    {
        $unitCost = (float) ($product->cost ?? 0);
        if ($units <= 0 || $unitCost <= 0) {
            return;
        }

        app(FinancialService::class)->recordTransaction($club, [
            'type'             => 'expense',
            'category'         => 'inventory',
            'amount'           => round($units * $unitCost, 2),
            'payment_method'   => 'other',
            'description'      => __('market.expense_stock_purchase', ['qty' => $units, 'name' => $product->name]),
            'transaction_date' => now(),
        ]);
    }

    private function validateProduct(Request $request): array
    {
        return $request->validate([
            'name'        => ['required', 'string', 'max:120'],
            'brand'       => ['nullable', 'string', 'max:80'],
            'cat'         => ['nullable', 'string', 'max:40'],
            'price'       => ['required', 'numeric', 'min:0', 'max:1000000'],
            'old'         => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'cost'        => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'marginType'  => ['nullable', 'in:fixed,percent'],
            'marginValue' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'badge'       => ['nullable', 'string', 'max:40'],
            'stock'       => ['nullable', 'string', 'max:40'],   // availability label
            'featured'    => ['nullable', 'boolean'],
            'color'       => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'icon'        => ['nullable', 'string', 'max:40'],
            'image'       => ['nullable', 'string'],             // data URL or existing URL
            'desc'        => ['nullable', 'string', 'max:5000'],
            'colors'      => ['nullable', 'array', 'max:10'],
            'colors.*'    => ['string', 'max:16'],
            'specs'       => ['nullable', 'array', 'max:20'],
            'fulfillment' => ['nullable', 'in:stock,dropship'],
            'quantity'    => ['nullable', 'integer', 'min:0'],
            'lowStock'    => ['nullable', 'integer', 'min:0'],
            'supplier'    => ['nullable', 'string', 'max:120'],
            'supplierUrl' => ['nullable', 'string', 'max:255'],
            'shipsIn'     => ['nullable', 'string', 'max:60'],
        ]);
    }

    private function fill(ClubProduct $product, array $data, Tenant $club): void
    {
        $fulfillment = $data['fulfillment'] ?? 'stock';

        // Margin-based pricing: when a cost and margin are given, the selling
        // price is derived server-side (cost + fixed amount, or cost × (1+%)),
        // so it always matches the recorded margin. Without a cost we keep the
        // price the client sent (manual pricing / legacy products).
        $cost        = isset($data['cost']) && $data['cost'] !== null ? (float) $data['cost'] : null;
        $marginType  = $data['marginType'] ?? 'fixed';
        $marginValue = isset($data['marginValue']) && $data['marginValue'] !== null ? (float) $data['marginValue'] : null;

        $price = (float) $data['price'];
        if ($cost !== null && $marginValue !== null) {
            $price = $marginType === 'percent'
                ? round($cost * (1 + $marginValue / 100), 2)
                : round($cost + $marginValue, 2);
        }

        $product->fill([
            'name'            => $data['name'],
            'brand'           => $data['brand'] ?? null,
            'category'        => $data['cat'] ?? 'gear',
            'price'           => $price,
            'old_price'       => $data['old'] ?? null,
            'cost'            => $cost,
            'margin_type'     => $marginType,
            'margin_value'    => $marginValue,
            'badge'           => $data['badge'] ?? null,
            'availability'    => $data['stock'] ?? 'In stock',
            'featured'        => (bool) ($data['featured'] ?? false),
            'color'           => $data['color'] ?? '#7c3aed',
            'icon'            => $data['icon'] ?? 'bi-bag',
            'description'     => $data['desc'] ?? null,
            'colors'          => $data['colors'] ?? [],
            'specs'           => array_values($data['specs'] ?? []),
            'fulfillment'     => $fulfillment,
            'quantity'        => $fulfillment === 'stock' ? ($data['quantity'] ?? 0) : null,
            'low_stock_alert' => $fulfillment === 'stock' ? ($data['lowStock'] ?? null) : null,
            'supplier'        => $fulfillment === 'dropship' ? ($data['supplier'] ?? null) : null,
            'supplier_url'    => $fulfillment === 'dropship' ? ($data['supplierUrl'] ?? null) : null,
            'ships_in'        => $fulfillment === 'dropship' ? ($data['shipsIn'] ?? null) : null,
        ]);

        // Image: a fresh data URL is stored; an existing /storage URL is left as-is.
        $image = $data['image'] ?? null;
        if ($image && str_starts_with($image, 'data:image')) {
            $path = $this->storeBase64Image($image, 'club-products/' . $club->id, 'product_' . uniqid());
            if ($path) {
                $product->image_path = $path;
            }
        } elseif ($image === null) {
            $product->image_path = null;
        }
    }
}
