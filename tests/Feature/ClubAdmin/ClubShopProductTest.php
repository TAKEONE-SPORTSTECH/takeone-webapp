<?php

namespace Tests\Feature\ClubAdmin;

use App\Models\ClubProduct;
use Tests\TestCase;

class ClubShopProductTest extends TestCase
{
    private function attributePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'TKD Uniform',
            'cat' => 'gear',
            'price' => 20,           // base/"from" price
            'fulfillment' => 'stock',
            'useVariants' => true,
            'attributes' => [
                ['name' => 'Brand', 'values' => ['Adidas', 'Kwon']],
                ['name' => 'Size', 'values' => ['S', 'M']],
            ],
            'variants' => [
                ['options' => ['Brand' => 'Adidas', 'Size' => 'S'], 'price' => 20, 'quantity' => 5, 'is_active' => true, 'description' => 'Lightweight Adidas small'],
                ['options' => ['Brand' => 'Adidas', 'Size' => 'M'], 'price' => 22, 'quantity' => 3, 'is_active' => true],
                ['options' => ['Brand' => 'Kwon', 'Size' => 'S'], 'price' => 25, 'quantity' => 0, 'is_active' => true],
                ['options' => ['Brand' => 'Kwon', 'Size' => 'M'], 'price' => 27, 'quantity' => 2, 'is_active' => false],
            ],
        ], $overrides);
    }

    public function test_store_product_persists_attributes_and_variant_options(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);

        $this->actingAs($owner)
            ->postJson("/admin/club/{$club->slug}/shop/products", $this->attributePayload())
            ->assertOk()
            ->assertJson(['success' => true]);

        $product = ClubProduct::where('tenant_id', $club->id)->firstOrFail();

        // Attributes stored on the product.
        $this->assertSame([
            ['name' => 'Brand', 'values' => ['Adidas', 'Kwon']],
            ['name' => 'Size', 'values' => ['S', 'M']],
        ], $product->attributeList());

        // Base price = lowest variant price.
        $this->assertSame('20.00', (string) $product->price);

        // Four variants, each carrying its options map + derived legacy columns + label.
        $variants = $product->variants()->orderBy('sort')->get();
        $this->assertCount(4, $variants);

        $first = $variants->first();
        $this->assertSame(['Brand' => 'Adidas', 'Size' => 'S'], $first->options);
        $this->assertSame('Adidas', $first->brand);   // legacy column derived
        $this->assertSame('S', $first->size);
        $this->assertSame('Adidas · S', $first->label);
        $this->assertSame('Lightweight Adidas small', $first->description);   // per-variation description
        $this->assertNull($variants->get(1)->description);                    // empty ⇒ null

        // is_active flows through.
        $this->assertFalse((bool) $variants->last()->is_active);
    }

    public function test_variation_can_be_dropship_with_supplier(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);

        $payload = $this->attributePayload([
            'attributes' => [['name' => 'Brand', 'values' => ['Adidas', 'Kwon']]],
            'variants' => [
                ['options' => ['Brand' => 'Adidas'], 'price' => 20, 'quantity' => 8, 'fulfillment' => 'stock', 'is_active' => true],
                ['options' => ['Brand' => 'Kwon'], 'price' => 27, 'quantity' => 5, 'fulfillment' => 'dropship',
                 'supplier' => 'Kwon Intl', 'ships_in' => '5 days', 'is_active' => true],
            ],
        ]);

        $this->actingAs($owner)
            ->postJson("/admin/club/{$club->slug}/shop/products", $payload)
            ->assertOk();

        $product = ClubProduct::where('tenant_id', $club->id)->firstOrFail();
        $variants = $product->variants()->orderBy('sort')->get();

        $stock = $variants->first();
        $this->assertSame('stock', $stock->fulfillment);
        $this->assertSame(8, (int) $stock->quantity);

        $drop = $variants->last();
        $this->assertSame('dropship', $drop->fulfillment);
        $this->assertNull($drop->quantity);            // dropship tracks no stock
        $this->assertSame('Kwon Intl', $drop->supplier);
        $this->assertSame('5 days', $drop->ships_in);
    }

    public function test_editing_upserts_by_id_and_removes_pruned_variants(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);

        $this->actingAs($owner)
            ->postJson("/admin/club/{$club->slug}/shop/products", $this->attributePayload())
            ->assertOk();

        $product = ClubProduct::where('tenant_id', $club->id)->firstOrFail();
        $keep = $product->variants()->orderBy('sort')->first();

        // Resubmit with only 2 of the 4 variants (one existing kept, one new).
        $payload = $this->attributePayload([
            'attributes' => [['name' => 'Brand', 'values' => ['Adidas']], ['name' => 'Size', 'values' => ['S', 'L']]],
            'variants' => [
                ['id' => $keep->id, 'options' => ['Brand' => 'Adidas', 'Size' => 'S'], 'price' => 30, 'quantity' => 9, 'is_active' => true],
                ['options' => ['Brand' => 'Adidas', 'Size' => 'L'], 'price' => 33, 'quantity' => 1, 'is_active' => true],
            ],
        ]);

        $this->actingAs($owner)
            ->putJson("/admin/club/{$club->slug}/shop/products/{$product->id}", $payload)
            ->assertOk();

        $variants = $product->fresh()->variants()->orderBy('sort')->get();
        $this->assertCount(2, $variants);
        $this->assertSame($keep->id, $variants->first()->id);       // same row updated
        $this->assertSame('30.00', (string) $variants->first()->price);
    }

    public function test_duplicate_variant_combination_is_rejected(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);

        $payload = $this->attributePayload([
            'variants' => [
                ['options' => ['Brand' => 'Adidas', 'Size' => 'S'], 'price' => 20, 'quantity' => 1, 'is_active' => true],
                ['options' => ['Brand' => 'Adidas', 'Size' => 'S'], 'price' => 21, 'quantity' => 1, 'is_active' => true],
            ],
        ]);

        $this->actingAs($owner)
            ->postJson("/admin/club/{$club->slug}/shop/products", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['variants.1.options']);
    }

    public function test_variant_option_outside_declared_values_is_rejected(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);

        $payload = $this->attributePayload([
            'variants' => [
                ['options' => ['Brand' => 'Adidas', 'Size' => 'XXL'], 'price' => 20, 'quantity' => 1, 'is_active' => true],
            ],
        ]);

        $this->actingAs($owner)
            ->postJson("/admin/club/{$club->slug}/shop/products", $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['variants.0.options']);
    }

    public function test_simple_product_without_attributes_still_saves(): void
    {
        $owner = $this->createUser();
        $club = $this->createClub($owner);

        $this->actingAs($owner)
            ->postJson("/admin/club/{$club->slug}/shop/products", [
                'name' => 'Water Bottle',
                'cat' => 'gear',
                'price' => 5,
                'fulfillment' => 'stock',
                'quantity' => 100,
            ])
            ->assertOk();

        $product = ClubProduct::where('tenant_id', $club->id)->firstOrFail();
        $this->assertNull($product->attributes);
        $this->assertCount(0, $product->variants);
        $this->assertSame('5.00', (string) $product->price);
    }
}
