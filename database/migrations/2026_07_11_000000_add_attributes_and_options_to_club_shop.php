<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * WooCommerce-style variants: a product declares free-form ATTRIBUTES
     * (name + list of values, e.g. Brand/Model/Size) and each sellable variant
     * records the ONE value it takes per attribute in an OPTIONS map. This
     * replaces the rigid brand/color/size columns as the source of truth while
     * keeping those legacy columns populated for backward compatibility.
     */
    public function up(): void
    {
        Schema::table('club_products', function (Blueprint $table) {
            // [{ "name": "Brand", "values": ["Adidas","Kwon"] }, ...]
            $table->json('attributes')->nullable()->after('specs');
        });

        Schema::table('club_product_variants', function (Blueprint $table) {
            // { "Brand": "Adidas", "Size": "M" }
            $table->json('options')->nullable()->after('sort');
        });

        $this->backfill();
    }

    /**
     * Derive attributes/options for every existing variant product from its
     * legacy brand/color/size columns, using query builder so we bypass model
     * booting + tenant global scopes.
     */
    private function backfill(): void
    {
        // Fixed legacy dimensions, in display order. Column => attribute name.
        $dimensions = ['brand' => 'Brand', 'color' => 'Color', 'size' => 'Size'];

        DB::table('club_products')
            ->select('id')
            ->orderBy('id')
            ->chunk(200, function ($products) use ($dimensions) {
                foreach ($products as $product) {
                    $variants = DB::table('club_product_variants')
                        ->where('club_product_id', $product->id)
                        ->whereNull('deleted_at')
                        ->orderBy('sort')
                        ->get();

                    if ($variants->isEmpty()) {
                        continue; // simple product — no attributes
                    }

                    // Distinct non-empty values per dimension (preserve first-seen order).
                    $values = ['brand' => [], 'color' => [], 'size' => []];
                    foreach ($variants as $v) {
                        foreach ($dimensions as $col => $_name) {
                            $raw = trim((string) ($v->{$col} ?? ''));
                            if ($raw !== '' && ! in_array($raw, $values[$col], true)) {
                                $values[$col][] = $raw;
                            }
                        }
                    }

                    // Build the product attribute list, dropping empty dimensions.
                    $attributes = [];
                    foreach ($dimensions as $col => $name) {
                        if (! empty($values[$col])) {
                            $attributes[] = ['name' => $name, 'values' => $values[$col]];
                        }
                    }

                    DB::table('club_products')
                        ->where('id', $product->id)
                        ->update(['attributes' => json_encode($attributes)]);

                    // Each variant's own options map, using the same dimension names.
                    foreach ($variants as $v) {
                        $options = [];
                        foreach ($dimensions as $col => $name) {
                            if (empty($values[$col])) {
                                continue; // dimension not used by this product
                            }
                            $raw = trim((string) ($v->{$col} ?? ''));
                            if ($raw !== '') {
                                $options[$name] = $raw;
                            }
                        }

                        DB::table('club_product_variants')
                            ->where('id', $v->id)
                            ->update(['options' => json_encode($options)]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Legacy brand/color/size columns are never dropped, so every reader
        // falls back to them — rollback is lossless.
        Schema::table('club_product_variants', function (Blueprint $table) {
            $table->dropColumn('options');
        });

        Schema::table('club_products', function (Blueprint $table) {
            $table->dropColumn('attributes');
        });
    }
};
