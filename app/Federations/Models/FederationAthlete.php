<?php

namespace App\Federations\Models;

use App\Members\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One person's federation identity: the permanent Takeone ID and a national
 * ID unique across the platform. The PERSON is the platform user; this row
 * only adds what a federation needs to know about them.
 *
 * The national ID is encrypted at rest; `national_id_hash` (a keyed hash of
 * the normalised value) is what uniqueness and lookups compare.
 */
class FederationAthlete extends Model
{
    protected $fillable = ['user_id', 'takeone_id', 'national_id', 'national_id_hash', 'nationality'];

    protected $casts = ['national_id' => 'encrypted'];

    protected $hidden = ['national_id', 'national_id_hash'];

    public static function hashNationalId(?string $value): ?string
    {
        $norm = strtolower(preg_replace('/[^0-9a-z]/i', '', (string) $value));

        return $norm === '' ? null : hash_hmac('sha256', $norm, (string) config('app.key'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clubs(): HasMany
    {
        return $this->hasMany(FederationAthleteClub::class);
    }
}
