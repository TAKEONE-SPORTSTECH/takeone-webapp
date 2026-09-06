<?php

namespace App\EventLab\Services;

use App\EventLab\Models\LabEvent;
use App\EventLab\Models\LabVariant;
use Illuminate\Support\Collection;

/**
 * What an entry costs — the one place in the sandbox that answers it.
 *
 * The sum is deliberately boring: every variant chosen, plus one late penalty if
 * the entry is being committed inside the late window. What matters is that it
 * is computed ONCE, here, on the server, from prices read out of the database —
 * never from anything the browser sent — and that the result is then written
 * down rather than recalculated later.
 *
 * The breakdown is returned alongside the total because "BHD 35" on an invoice
 * that nobody can take apart is how billing arguments start.
 */
class LabFee
{
    /**
     * Quote an entry.
     *
     * @param  array<int>  $variantIds  the variants the athlete chose
     * @return array{total: float, currency: string, late: bool, late_amount: float, lines: array<int, array{variant_id: int, name: string, amount: float}>}
     */
    public function quote(LabEvent $event, array $variantIds, ?bool $late = null): array
    {
        $late = $late ?? $event->isLateNow();

        /** @var Collection<int, LabVariant> $variants */
        $variants = $event->variants()
            ->whereIn('id', array_map('intval', $variantIds))
            ->get();

        $lines = $variants->map(fn (LabVariant $v) => [
            'variant_id' => (int) $v->id,
            'name' => $v->name,
            'amount' => $v->fee(),
        ])->values()->all();

        $lateAmount = $late ? $event->lateFee() : 0.0;
        $total = array_sum(array_column($lines, 'amount')) + $lateAmount;

        return [
            'total' => round($total, 3),
            'currency' => $event->fee_currency ?: 'BHD',
            'late' => (bool) $late,
            'late_amount' => round($lateAmount, 3),
            'lines' => $lines,
        ];
    }

    /**
     * The variant ids that actually belong to this event.
     *
     * A quote must never price a variant from somebody else's event just because
     * an id was posted, so the caller filters through here first rather than
     * trusting the request.
     *
     * @param  array<int>  $variantIds
     * @return array<int>
     */
    public function ownedBy(LabEvent $event, array $variantIds): array
    {
        return $event->variants()
            ->whereIn('id', array_map('intval', $variantIds))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}
