<?php

namespace Database\Seeders;

use App\Models\ClubProduct;
use App\Models\ClubProductCategory;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Seeds a sample storefront for a club so the Shop has something to show.
 * Idempotent — keyed on (tenant, name). Target a club with CLUB_SHOP_SLUG
 * (default "eta").
 */
class ClubShopSeeder extends Seeder
{
    public function run(): void
    {
        $slug = env('CLUB_SHOP_SLUG', 'eta');
        $club = Tenant::where('slug', $slug)->first() ?? Tenant::orderBy('id')->first();

        if (! $club) {
            $this->command?->warn('No club found to seed a shop for.');
            return;
        }

        $categories = [
            ['key' => 'gear',      'label' => 'Gear',      'icon' => 'bi-bag'],
            ['key' => 'equipment', 'label' => 'Equipment', 'icon' => 'bi-bicycle'],
            ['key' => 'nutrition', 'label' => 'Nutrition', 'icon' => 'bi-cup-hot'],
            ['key' => 'apparel',   'label' => 'Apparel',   'icon' => 'bi-person-arms-up'],
            ['key' => 'passes',    'label' => 'Passes',    'icon' => 'bi-ticket-perforated'],
        ];
        foreach ($categories as $i => $c) {
            ClubProductCategory::updateOrCreate(
                ['tenant_id' => $club->id, 'key' => $c['key']],
                ['label' => $c['label'], 'icon' => $c['icon'], 'sort' => $i],
            );
        }

        $products = [
            ['name' => 'Pro Boxing Gloves', 'brand' => 'TAKEONE Sport', 'category' => 'gear', 'price' => 28, 'old_price' => 38, 'badge' => 'Sale', 'featured' => true, 'color' => '#7c3aed', 'icon' => 'bi-trophy', 'fulfillment' => 'stock', 'quantity' => 24, 'description' => 'Premium 12oz sparring gloves with multi-layer foam and a secure wrist strap.', 'specs' => [['Weight', '12 oz'], ['Material', 'Vegan leather'], ['Closure', 'Velcro strap']], 'colors' => ['#7c3aed', '#ef4444', '#111827']],
            ['name' => 'Adjustable Dumbbell 24kg', 'brand' => 'IronCore', 'category' => 'equipment', 'price' => 95, 'badge' => null, 'featured' => true, 'color' => '#0ea5e9', 'icon' => 'bi-bicycle', 'fulfillment' => 'stock', 'quantity' => 8, 'low_stock_alert' => 5, 'description' => 'One dumbbell, fifteen weights. Dial from 2.5kg to 24kg in seconds.', 'specs' => [['Range', '2.5–24 kg'], ['Handle', 'Knurled steel']]],
            ['name' => 'Whey Protein · 1kg', 'brand' => 'FuelLab', 'category' => 'nutrition', 'price' => 22, 'old_price' => 26, 'badge' => 'Sale', 'color' => '#f59e0b', 'icon' => 'bi-cup-hot', 'fulfillment' => 'dropship', 'supplier' => 'FuelLab', 'ships_in' => '5–7 days', 'supplier_url' => 'https://fuellab.example/whey', 'description' => '24g of protein per scoop, low sugar, mixes smooth.', 'specs' => [['Protein', '24 g / scoop'], ['Servings', '33']], 'colors' => ['#7c3aed', '#f59e0b', '#ec4899']],
            ['name' => '10-Session Class Pass', 'brand' => $club->club_name, 'category' => 'passes', 'price' => 45, 'old_price' => 60, 'badge' => 'Best value', 'featured' => true, 'availability' => 'Digital', 'color' => '#10b981', 'icon' => 'bi-ticket-perforated', 'fulfillment' => 'stock', 'quantity' => 999, 'description' => 'Ten drop-in sessions across any group class. Valid 3 months.', 'specs' => [['Sessions', '10'], ['Valid', '3 months']]],
            ['name' => 'Performance Training Tee', 'brand' => 'TAKEONE Sport', 'category' => 'apparel', 'price' => 16, 'badge' => 'New', 'color' => '#ec4899', 'icon' => 'bi-person-arms-up', 'fulfillment' => 'stock', 'quantity' => 60, 'description' => 'Breathable quick-dry tee with a relaxed athletic cut.', 'specs' => [['Fabric', 'Quick-dry poly'], ['Sizes', 'XS–XXL']], 'colors' => ['#111827', '#ec4899', '#0ea5e9', '#10b981']],
            ['name' => 'Speed Jump Rope', 'brand' => 'IronCore', 'category' => 'gear', 'price' => 9, 'old_price' => 14, 'badge' => 'Sale', 'color' => '#ef4444', 'icon' => 'bi-lightning-charge-fill', 'fulfillment' => 'dropship', 'supplier' => 'IronCore', 'ships_in' => '3–5 days', 'description' => 'Ball-bearing speed rope with adjustable length and anti-slip handles.', 'specs' => [['Bearing', 'Dual ball'], ['Cable', 'Coated steel']]],
            ['name' => 'Yoga & Recovery Mat', 'brand' => 'FlowState', 'category' => 'equipment', 'price' => 19, 'color' => '#10b981', 'icon' => 'bi-grid', 'fulfillment' => 'stock', 'quantity' => 30, 'description' => 'Extra-thick 6mm non-slip mat with alignment lines.', 'specs' => [['Thickness', '6 mm'], ['Size', '183 × 61 cm']], 'colors' => ['#10b981', '#7c3aed', '#111827']],
            ['name' => 'Electrolyte Hydration Mix', 'brand' => 'FuelLab', 'category' => 'nutrition', 'price' => 12, 'old_price' => 15, 'badge' => 'Sale', 'color' => '#0ea5e9', 'icon' => 'bi-droplet-half', 'fulfillment' => 'dropship', 'supplier' => 'FuelLab', 'ships_in' => '5–7 days', 'description' => 'Sugar-free electrolyte sachets — 20 sticks per box.', 'specs' => [['Sticks', '20'], ['Sugar', '0 g']]],
        ];

        foreach ($products as $i => $p) {
            ClubProduct::updateOrCreate(
                ['tenant_id' => $club->id, 'name' => $p['name']],
                array_merge([
                    'brand' => null, 'old_price' => null, 'badge' => null, 'availability' => 'In stock',
                    'featured' => false, 'color' => '#7c3aed', 'icon' => 'bi-bag', 'description' => null,
                    'colors' => [], 'specs' => [], 'fulfillment' => 'stock', 'quantity' => null,
                    'low_stock_alert' => null, 'supplier' => null, 'supplier_url' => null, 'ships_in' => null,
                    'status' => 'published', 'sort' => count($products) - $i,
                ], $p),
            );
        }

        $this->command?->info("Seeded {$club->club_name} shop: " . count($products) . ' products, ' . count($categories) . ' categories.');
    }
}
