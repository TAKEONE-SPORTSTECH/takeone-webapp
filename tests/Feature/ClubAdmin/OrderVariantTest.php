<?php

namespace Tests\Feature\ClubAdmin;

use App\Models\ClubProduct;
use App\Models\ClubProductVariant;
use Tests\TestCase;

class OrderVariantTest extends TestCase
{
    /** A tiny but real 1×1 PNG, so StoresBase64Images accepts the proof. */
    private function pngProof(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';
    }

    public function test_order_for_a_variant_snapshots_label_price_and_decrements_stock(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $buyer = $this->createUser();

        $product = ClubProduct::create([
            'tenant_id' => $club->id,
            'name' => 'TKD Uniform',
            'price' => 20,
            'fulfillment' => 'stock',
            'status' => 'published',
            'attributes' => [
                ['name' => 'Brand', 'values' => ['Adidas', 'Kwon']],
                ['name' => 'Size', 'values' => ['M', 'L']],
            ],
        ]);

        $variant = ClubProductVariant::create([
            'tenant_id' => $club->id,
            'club_product_id' => $product->id,
            'options' => ['Brand' => 'Kwon', 'Size' => 'L'],
            'brand' => 'Kwon',
            'size' => 'L',
            'price' => 27,
            'quantity' => 4,
            'is_active' => true,
        ]);

        $this->actingAs($buyer)
            ->postJson(route('me.orders.store'), [
                'items' => [
                    ['id' => $product->id, 'qty' => 2, 'variant_id' => $variant->id],
                ],
                'proof' => $this->pngProof(),
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        // Order item snapshots the resolved variant (label from options) + variant price.
        $this->assertDatabaseHas('order_items', [
            'club_product_id' => $product->id,
            'club_product_variant_id' => $variant->id,
            'variant_label' => 'Kwon · L',
            'price' => 27,
            'qty' => 2,
        ]);

        // Variant stock decremented by the ordered quantity.
        $this->assertSame(2, (int) $variant->fresh()->quantity);
    }

    public function test_order_ignores_variant_from_another_product(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);
        $buyer = $this->createUser();

        $product = ClubProduct::create([
            'tenant_id' => $club->id, 'name' => 'Belt', 'price' => 5,
            'fulfillment' => 'stock', 'status' => 'published', 'quantity' => 10,
        ]);
        $otherProduct = ClubProduct::create([
            'tenant_id' => $club->id, 'name' => 'Other', 'price' => 9,
            'fulfillment' => 'stock', 'status' => 'published',
        ]);
        $foreignVariant = ClubProductVariant::create([
            'tenant_id' => $club->id, 'club_product_id' => $otherProduct->id,
            'options' => ['Size' => 'M'], 'size' => 'M', 'price' => 99, 'quantity' => 3, 'is_active' => true,
        ]);

        $this->actingAs($buyer)
            ->postJson(route('me.orders.store'), [
                'items' => [
                    ['id' => $product->id, 'qty' => 1, 'variant_id' => $foreignVariant->id],
                ],
                'proof' => $this->pngProof(),
            ])
            ->assertOk();

        // The mismatched variant is ignored → line falls back to the product's own price.
        $this->assertDatabaseHas('order_items', [
            'club_product_id' => $product->id,
            'club_product_variant_id' => null,
            'price' => 5,
        ]);
        $this->assertSame(3, (int) $foreignVariant->fresh()->quantity); // untouched
    }
}
