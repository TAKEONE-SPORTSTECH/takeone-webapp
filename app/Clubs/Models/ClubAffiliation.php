<?php

namespace App\Clubs\Models;

use App\Traits\HasVerificationState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Members\Models\AffiliationMedia;
use App\Models\ClubMemberSubscription;
use App\Members\Models\SkillAcquisition;
use App\Members\Models\User;

class ClubAffiliation extends Model
{
    use HasVerificationState;

    protected $fillable = [
        'member_id',
        'tenant_id',
        'club_name',
        'logo',
        'start_date',
        'end_date',
        'location',
        'coaches',
        'description',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'coaches' => 'array',
    ];

    /**
     * Normalize the `coaches` JSON into structured instructor entries.
     *
     * `coaches` historically held a flat array of name strings; instructors added
     * through the UI store `{name, user_id}` objects (user_id links a real member).
     * Both shapes are accepted on read so old rows keep working.
     *
     * @return array<int, array{name:string, user_id:int|null}>
     */
    public function instructorList(): array
    {
        return collect($this->coaches ?? [])
            ->map(function ($c) {
                if (is_array($c)) {
                    $name = trim((string) ($c['name'] ?? ''));

                    return $name === '' ? null : ['name' => $name, 'user_id' => $c['user_id'] ?? null];
                }
                $name = trim((string) $c);

                return $name === '' ? null : ['name' => $name, 'user_id' => null];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Get the member that owns the affiliation.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_id');
    }

    /**
     * Get the club (tenant) this affiliation is linked to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    /** The club that may confirm this affiliation (the platform club itself), or null. */
    public function attestingTenant(): ?Tenant
    {
        return $this->tenant;
    }

    /** Affiliations are owned via member_id (not user_id). */
    public function attestationOwnerId(): ?int
    {
        return $this->member_id ? (int) $this->member_id : null;
    }

    /** Short human label for notifications/audit — club + period. */
    public function attestationLabel(): string
    {
        $span = trim((optional($this->start_date)->format('M Y') ?: '').
            ($this->end_date ? ' – '.$this->end_date->format('M Y') : ''));

        return trim(($this->club_name ?? '').($span ? ' · '.$span : ''), ' ·');
    }

    /**
     * Get the skills acquired during this affiliation.
     */
    public function skillAcquisitions(): HasMany
    {
        return $this->hasMany(SkillAcquisition::class);
    }

    /**
     * Get the media associated with this affiliation.
     */
    public function affiliationMedia(): HasMany
    {
        return $this->hasMany(AffiliationMedia::class);
    }

    /**
     * Get the subscriptions associated with this affiliation.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(ClubMemberSubscription::class, 'club_affiliation_id');
    }

    /**
     * Get the packages through subscriptions.
     */
    public function packages()
    {
        return $this->hasManyThrough(
            ClubPackage::class,
            ClubMemberSubscription::class,
            'club_affiliation_id', // Foreign key on subscriptions table
            'id', // Foreign key on packages table
            'id', // Local key on affiliations table
            'package_id' // Local key on subscriptions table
        );
    }

    /**
     * Get the duration of the affiliation in months.
     */
    public function getDurationInMonthsAttribute(): int
    {
        $endDate = $this->end_date ?? now();

        // Carbon 3 returns a FLOAT here, so returning it straight from an `int`
        // accessor is an implicit narrowing PHP now warns about. Only surfaced
        // once affiliations existed with a start date inside the current month
        // (a fraction of a month); floor keeps the previous whole-month meaning.
        return (int) floor($this->start_date->diffInMonths($endDate));
    }

    /**
     * Get formatted date range.
     */
    public function getDateRangeAttribute(): string
    {
        $start = $this->start_date->format('M Y');
        $end = $this->end_date ? $this->end_date->format('M Y') : 'Present';

        return $start.' – '.$end;
    }

    /**
     * Get detailed formatted duration (years, months, days).
     */
    public function getFormattedDurationAttribute(): string
    {
        $endDate = $this->end_date ?? now();
        $diff = $this->start_date->diff($endDate);

        $parts = [];

        if ($diff->y > 0) {
            $parts[] = $diff->y.' year'.($diff->y > 1 ? 's' : '');
        }
        if ($diff->m > 0) {
            $parts[] = $diff->m.' month'.($diff->m > 1 ? 's' : '');
        }
        if ($diff->d > 0) {
            $parts[] = $diff->d.' day'.($diff->d > 1 ? 's' : '');
        }

        return implode(' ', $parts) ?: 'Same day';
    }
}
