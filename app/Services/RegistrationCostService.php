<?php

namespace App\Services;

use App\Models\ClubActivityEquipment;
use App\Models\ClubMemberSubscription;
use App\Models\ClubPackage;
use App\Models\ClubTransaction;
use App\Models\MemberEquipment;
use App\Models\Membership;
use App\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * Resolves the first-time registration cost model: per-package registration fee
 * (with club-wide fallback), the activity-scoped equipment catalog, and the
 * member's existing equipment ownership which drives smart default ticks.
 *
 * Prices are SNAPSHOTTED at registration time — see snapshotEquipment() — so a
 * later catalog price change never alters a historical record.
 */
class RegistrationCostService
{
    /**
     * Effective registration (joining) fee for a package:
     * the package override, else the club-wide enrollment_fee, else 0.
     */
    public function effectiveRegistrationFee(ClubPackage $package, ?Tenant $club = null): float
    {
        if ($package->registration_fee !== null) {
            return (float) $package->registration_fee;
        }

        $club ??= $package->tenant;

        return (float) ($club?->enrollment_fee ?? 0);
    }

    /**
     * True when this user has already been a member of the club at any point —
     * i.e. the joining fee must NOT be charged again. Mirrors the existing
     * memberships-based first-time check used elsewhere.
     */
    public function isReturningMember(int $tenantId, int $userId): bool
    {
        return Membership::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * Product ids the user already owns (or is acquiring) at this club, so the
     * matching gear can be pre-unticked. Keyed on the underlying product so that
     * owning a Dobok skips it for EVERY activity that requires it.
     */
    public function ownedProductIds(int $tenantId, int $userId): array
    {
        return MemberEquipment::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereIn('status', MemberEquipment::OWNED_STATUSES)
            ->whereNotNull('club_product_id')
            ->pluck('club_product_id')
            ->all();
    }

    /**
     * Variant ids the user already owns at this club. Ownership of a variant
     * product is per exact variant (owning size M does not skip size L).
     */
    public function ownedVariantIds(int $tenantId, int $userId): array
    {
        return MemberEquipment::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->whereIn('status', MemberEquipment::OWNED_STATUSES)
            ->whereNotNull('club_product_variant_id')
            ->pluck('club_product_variant_id')
            ->all();
    }

    /**
     * The equipment a member would be offered for the given packages, grouped by
     * package id. Each item is shaped for the registration UI:
     *   { id, name, price, is_required, already_owned }
     *
     * `already_owned` flips the default tick off (still overridable by the user).
     * Equipment is de-duplicated per package (the same activity gear is offered
     * once even if shared across activities).
     *
     * Attach `equipment` + `schedule` attributes to each package model (for the
     * JSON handed to the registration UIs) and drop the heavy nested activities
     * relation. Each equipment entry pulls live name/price from the linked shop
     * product; ownership is keyed on the product.
     *   equipment: { id, product_id, name, price, image, is_required, already_owned }
     *   schedule:  { activity, day, start_time, end_time, facility }
     *
     * @param  \Illuminate\Support\Collection<ClubPackage>  $packages  (must have `activities.equipment.product` loaded or loadable)
     */
    public function attachEquipmentToPackages(Collection $packages, int $tenantId, ?int $userId = null): void
    {
        $packages->loadMissing('activities.equipment.product.variants');
        $owned        = $userId ? $this->ownedProductIds($tenantId, $userId) : [];
        $ownedVariant = $userId ? $this->ownedVariantIds($tenantId, $userId) : [];

        foreach ($packages as $package) {
            $items = $package->activities
                ->flatMap->equipment
                ->where('is_active', true)
                ->filter(fn (ClubActivityEquipment $e) => $e->product !== null)  // drop links to deleted products
                ->unique('id')
                ->map(function (ClubActivityEquipment $e) use ($owned, $ownedVariant) {
                    $product  = $e->product;
                    $variants = $product->variants->where('is_active', true)->values();
                    $hasVariants = $variants->isNotEmpty();

                    return [
                        'id'            => $e->id,
                        'product_id'    => $e->club_product_id,
                        'name'          => $product->name,
                        // With variants the price is "from" the cheapest option;
                        // the UI charges the price of whichever variant is chosen.
                        'price'         => $hasVariants ? (float) $variants->min('price') : (float) $product->price,
                        'image'         => $product->image_path ? asset('storage/' . $product->image_path) : null,
                        'is_required'   => (bool) $e->is_required,
                        'has_variants'  => $hasVariants,
                        'variants'      => $variants->map(fn ($v) => [
                            'id'        => $v->id,
                            'label'     => $v->label,
                            'size'      => $v->size,
                            'color'     => $v->color,
                            'color_hex' => $v->color_hex,
                            'brand'     => $v->brand,
                            'price'     => (float) $v->price,
                            'in_stock'  => ! $v->isOutOfStock(),
                            'owned'     => in_array($v->id, $ownedVariant, true),
                        ])->all(),
                        // Non-variant ownership stays keyed on the product.
                        'already_owned' => ! $hasVariants && in_array($e->club_product_id, $owned, true),
                    ];
                })
                ->values()
                ->all();

            // Flatten each activity's pivot schedule into a flat session list.
            $schedule = [];
            foreach ($package->activities as $activity) {
                $rows = $activity->pivot->schedule ?? null;
                if (is_string($rows)) {
                    $rows = json_decode($rows, true) ?: [];
                }
                foreach ((array) $rows as $slot) {
                    if (!is_array($slot)) continue;
                    $schedule[] = [
                        'activity'   => $activity->name,
                        'day'        => $slot['day'] ?? null,
                        'start_time' => $slot['start_time'] ?? null,
                        'end_time'   => $slot['end_time'] ?? null,
                        'facility'   => $slot['facility_name'] ?? null,
                    ];
                }
            }

            $package->setAttribute('equipment', $items);
            $package->setAttribute('schedule', $schedule);
            $package->unsetRelation('activities');
        }
    }

    /**
     * Persist the chosen equipment as frozen accounting lines + ownership memory,
     * and record matching club income transactions. Returns total charged.
     *
     * @param  array<int>  $equipmentIds  catalog ids the registrant kept ticked
     */
    public function snapshotEquipment(
        Tenant $club,
        int $userId,
        ?ClubMemberSubscription $subscription,
        array $equipmentIds,
        string $ownershipStatus = 'owned',
        bool $recordIncome = true,
        array $variantMap = []
    ): float {
        if (empty($equipmentIds)) {
            return 0.0;
        }

        $items = ClubActivityEquipment::where('tenant_id', $club->id)
            ->whereIn('id', $equipmentIds)
            ->with('product.variants')
            ->get();

        $total = 0.0;
        foreach ($items as $item) {
            $product = $item->product;
            if (!$product) continue;   // link to a deleted product — nothing to charge

            // When the product has variants, the price + label come from the
            // chosen variant. A variant product with no choice is skipped.
            $variant = null;
            if ($product->variants->isNotEmpty()) {
                $chosenId = $variantMap[$item->id] ?? null;
                $variant  = $chosenId ? $product->variants->firstWhere('id', (int) $chosenId) : null;
                if (! $variant) {
                    continue;
                }
            }

            $name  = $variant ? ($product->name . ' — ' . $variant->label) : $product->name;
            $price = (float) ($variant ? $variant->price : $product->price);

            MemberEquipment::create([
                'tenant_id'               => $club->id,
                'user_id'                 => $userId,
                'activity_id'             => $item->activity_id,
                'equipment_id'            => $item->id,
                'club_product_id'         => $product->id,
                'club_product_variant_id' => $variant?->id,
                'subscription_id'         => $subscription?->id,
                'name'                    => $name,           // snapshot (frozen for accounting)
                'variant_label'           => $variant?->label,
                'price'                   => $price,          // snapshot
                'status'                  => $ownershipStatus,
                'acquired_at'             => now(),
            ]);

            if ($recordIncome && $price > 0) {
                ClubTransaction::create([
                    'tenant_id'        => $club->id,
                    'user_id'          => $userId,
                    'subscription_id'  => $subscription?->id,
                    'type'             => 'income',
                    'category'         => 'equipment',
                    'amount'           => $price,
                    'description'      => 'Equipment: ' . $name,
                    'transaction_date' => now(),
                ]);
            }

            $total += $price;
        }

        return $total;
    }

    /**
     * Record equipment the registrant said they ALREADY OWN — ownership memory
     * only. Never billed, no income, price snapshotted as 0. A variant choice is
     * not required (we only remember they own the product). Items already in the
     * charged list should NOT be passed here.
     *
     * @param  array<int>  $equipmentIds  catalog ids ticked "I already have it"
     */
    public function recordOwnedEquipment(
        Tenant $club,
        int $userId,
        ?ClubMemberSubscription $subscription,
        array $equipmentIds
    ): void {
        $equipmentIds = array_values(array_unique(array_filter(array_map('intval', $equipmentIds))));
        if (empty($equipmentIds)) {
            return;
        }

        $items = ClubActivityEquipment::where('tenant_id', $club->id)
            ->whereIn('id', $equipmentIds)
            ->with('product')
            ->get();

        foreach ($items as $item) {
            $product = $item->product;
            if (! $product) continue;   // link to a deleted product — nothing to remember

            MemberEquipment::create([
                'tenant_id'       => $club->id,
                'user_id'         => $userId,
                'activity_id'     => $item->activity_id,
                'equipment_id'    => $item->id,
                'club_product_id' => $product->id,
                'subscription_id' => $subscription?->id,
                'name'            => $product->name,   // snapshot
                'price'           => 0,                // already owned — never charged
                'status'          => 'owned',
                'acquired_at'     => now(),
            ]);
        }
    }

    /**
     * Record the snapshotted registration (joining) fee as a club income
     * transaction. The amount is already frozen onto the subscription.
     */
    public function recordRegistrationFee(Tenant $club, int $userId, ?ClubMemberSubscription $subscription, float $fee, string $memberName): void
    {
        if ($fee <= 0) {
            return;
        }

        ClubTransaction::create([
            'tenant_id'        => $club->id,
            'user_id'          => $userId,
            'subscription_id'  => $subscription?->id,
            'type'             => 'income',
            'category'         => 'enrollment',
            'amount'           => $fee,
            'description'      => "Registration fee: {$memberName}",
            'transaction_date' => now(),
        ]);
    }
}
