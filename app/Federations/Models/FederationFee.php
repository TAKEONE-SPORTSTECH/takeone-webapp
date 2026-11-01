<?php

namespace App\Federations\Models;

use App\Traits\DeletesUploadedFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One fee owed to a federation for one season: a club's yearly fee
 * (kind=club), or — when the season has the option on — an athlete's licence
 * (kind=athlete). due → submitted (proof uploaded) → approved / rejected; a
 * federation may also waive one. The proof is a private file, served only
 * through an authorised route.
 */
class FederationFee extends Model
{
    use DeletesUploadedFiles;

    public const KIND_CLUB = 'club';
    public const KIND_ATHLETE = 'athlete';

    public const DUE = 'due';
    public const SUBMITTED = 'submitted';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const WAIVED = 'waived';

    /** States that recognise the club (or license the athlete). */
    public const RECOGNISING = [self::APPROVED, self::WAIVED];

    protected $fillable = [
        'season_id', 'kind', 'federation_club_id', 'federation_athlete_id', 'amount', 'currency', 'status',
        'proof_path', 'proof_mime', 'submitted_at', 'submitted_by', 'decided_at', 'decided_by', 'reason',
    ];

    protected $casts = [
        'amount' => 'decimal:3',
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
    ];

    protected array $fileUploads = ['proof_path' => 'local'];

    protected static function booted(): void
    {
        static::creating(fn (self $f) => $f->uuid ??= (string) Str::uuid());
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(FederationFeeSeason::class, 'season_id');
    }

    public function club(): BelongsTo
    {
        return $this->belongsTo(FederationClub::class, 'federation_club_id');
    }

    public function athlete(): BelongsTo
    {
        return $this->belongsTo(FederationAthlete::class, 'federation_athlete_id');
    }

    public function recognises(): bool
    {
        return in_array($this->status, self::RECOGNISING, true);
    }
}
