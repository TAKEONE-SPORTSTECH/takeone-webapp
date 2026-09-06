<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing an entry was charged, as it stood when the entry was taken.
 *
 * A RECORD, not a calculation. The label and the amount are copied onto the row
 * rather than read back through `fee_option_id`, so renaming or repricing an
 * option next season cannot restate what somebody was charged this one. The
 * option id is provenance only, and is null for the two lines that never came
 * from an option: the event's base fee and the late-entry penalty.
 */
class EventRegistrationFeeLine extends Model
{
    use HasFactory;

    protected $table = 'event_registration_fee_lines';

    protected $fillable = ['registration_id', 'fee_option_id', 'kind', 'label', 'amount', 'currency'];

    protected $casts = ['amount' => 'decimal:3'];

    /** What a line IS, so a total can be explained without joining anything. */
    public const KIND_BASE = 'base';
    public const KIND_OPTION = 'option';
    public const KIND_LATE = 'late';

    public function registration(): BelongsTo
    {
        return $this->belongsTo(ClubEventRegistration::class, 'registration_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(EventFeeOption::class, 'fee_option_id');
    }
}
