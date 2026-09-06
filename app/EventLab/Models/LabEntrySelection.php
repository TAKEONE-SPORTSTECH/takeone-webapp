<?php

namespace App\EventLab\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing an entry entered: this athlete, in this variant, at this weight.
 *
 * `fee_amount` is the variant's price AS IT WAS when the athlete chose it, kept
 * here rather than read back off the variant, so an organiser correcting a price
 * cannot retroactively change a bill somebody has already been quoted.
 *
 * The weight lives here rather than on the entrant because the same athlete can
 * legitimately be in two variants at different weights.
 */
class LabEntrySelection extends Model
{
    protected $table = 'lab_entry_variants';

    protected $fillable = [
        'lab_entry_id', 'lab_variant_id', 'weight', 'division_label', 'fee_amount',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'fee_amount' => 'decimal:3',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(LabEntry::class, 'lab_entry_id');
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(LabVariant::class, 'lab_variant_id');
    }
}
