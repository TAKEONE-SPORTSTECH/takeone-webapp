<?php

namespace App\Members\Models;

use App\Traits\HasVerificationState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberWorkHistory extends Model
{
    use HasVerificationState;

    protected $table = 'member_work_history';

    protected $fillable = [
        'user_id',
        'title',
        'organization',
        'employment_type',
        'location',
        'start_date',
        'end_date',
        'description',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** A null end_date means the role is ongoing. */
    public function isCurrent(): bool
    {
        return $this->end_date === null;
    }

    /**
     * The club that may confirm this role — matched from the free-text organization
     * to an active platform club by name. Null (→ peer/colleague vouch) otherwise.
     */
    public function attestingTenant(): ?\App\Clubs\Models\Tenant
    {
        $org = trim((string) $this->organization);
        if ($org === '') {
            return null;
        }

        return \App\Clubs\Models\Tenant::whereRaw('LOWER(club_name) = ?', [mb_strtolower($org)])
            ->where('status', 'active')->first();
    }

    /** Short human label for notifications/audit. */
    public function attestationLabel(): string
    {
        return trim(($this->title ?? '').($this->organization ? ' · '.$this->organization : ''), ' ·');
    }
}
