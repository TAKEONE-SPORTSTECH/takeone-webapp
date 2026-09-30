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
    protected $fillable = ['user_id', 'takeone_id', 'national_id', 'national_id_hash', 'nationality', 'anonymised_at'];

    protected $casts = ['national_id' => 'encrypted', 'anonymised_at' => 'datetime'];

    protected $hidden = ['national_id', 'national_id_hash'];

    /**
     * A new TakeOne ID: nationality, the name's first and last initials, six
     * random digits — BHR-AE541028. The ONE generator: the invitation and the
     * dashboard's "Generate TakeOne ID" both call it.
     */
    public static function issueTakeoneId(string $iso3, string $name = ''): string
    {
        $words = preg_split('/\s+/', trim(\Illuminate\Support\Str::ascii($name))) ?: [];
        $letters = array_values(array_filter(array_map(fn ($w) => strtoupper(preg_replace('/[^A-Za-z]/', '', $w))[0] ?? '', $words)));
        $initials = count($letters) >= 2 ? $letters[0].end($letters) : (($letters[0] ?? 'X').'X');
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $iso3), 0, 3) ?: 'XXX').'-'.$initials;

        for ($try = 0; $try < 20; $try++) {
            $id = $prefix.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            if (! static::where('takeone_id', $id)->exists()) {
                return $id;
            }
        }

        throw new \RuntimeException('Could not issue a unique TakeOne ID. Try again.');
    }

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
