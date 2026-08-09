<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person appointed to officiate one event — by default, the jury.
 *
 * Scoped to a single event on purpose: officiating a championship says nothing
 * about the next one. See App\Events\Support\EventAccess for what it grants
 * (arranging the draw before the event starts, and nothing else).
 */
class EventOfficial extends Model
{
    /** Arrange the draw before the event starts. */
    public const ROLE_JURY = 'jury';

    /** Record and verify official weights at the weigh-in. */
    public const ROLE_WEIGH_IN = 'weigh_in';

    /** Check proof of payment against the club account and approve it. */
    public const ROLE_PAYMENTS = 'payments';

    /**
     * Ran the event. Grants NO extra permission — EventAccess derives management
     * rights from ClubEvent::created_by, and every isOfficial() check names the
     * role it wants (jury / weigh_in / payments). This role exists so the person
     * organising can be recorded, and paid, like anyone else on the staff.
     */
    public const ROLE_ORGANISER = 'organiser';

    /** Gave their time. */
    public const COMP_VOLUNTEER = 'volunteer';

    /** Being paid a stated fee, which becomes an event expense. */
    public const COMP_PAID = 'paid';

    /** @return array<int, string> */
    public static function roles(): array
    {
        return [self::ROLE_JURY, self::ROLE_WEIGH_IN, self::ROLE_PAYMENTS, self::ROLE_ORGANISER];
    }

    /** @return array<int, string> */
    public static function compensations(): array
    {
        return [self::COMP_VOLUNTEER, self::COMP_PAID];
    }

    protected $fillable = [
        'event_id',
        'user_id',
        'role',
        'assigned_by',
        'compensation',
        'fee',
    ];

    protected $casts = [
        'fee' => 'decimal:3',
    ];

    /**
     * Keep the event's ledger in step with who is being paid to run it.
     *
     * Hooked on the model rather than done in the controller so it holds for
     * EVERY path that touches an appointment — web, MCP, console, seeder — and
     * cannot be forgotten at a new call site.
     */
    protected static function booted(): void
    {
        static::saved(fn (self $official) => $official->syncExpense());

        // The expense row cascades on delete via its FK, so nothing to undo here
        // beyond letting the database do it.
    }

    /** True when this appointment costs the event money. */
    public function isPaid(): bool
    {
        return $this->compensation === self::COMP_PAID && (float) $this->fee > 0;
    }

    /**
     * Create, update or remove the expense that mirrors this appointment.
     *
     * One expense per official, found by event_official_id — so editing a fee
     * moves the existing line rather than stacking a second one.
     */
    public function syncExpense(): void
    {
        $expense = EventExpense::where('event_official_id', $this->id)->first();

        if (! $this->isPaid()) {
            $expense?->delete();       // switched to volunteer, or fee cleared

            return;
        }

        $label = trim(sprintf(
            '%s — %s',
            __('personal.event_officials_role_'.$this->role),
            $this->user?->full_name ?? $this->user?->name ?? __('personal.event_officials_someone')
        ));

        if ($expense) {
            $expense->update(['label' => $label, 'amount' => $this->fee]);

            return;
        }

        EventExpense::create([
            'event_id' => $this->event_id,
            'event_official_id' => $this->id,
            'label' => $label,
            'amount' => $this->fee,
            'created_by' => $this->assigned_by,
        ]);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(ClubEvent::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
