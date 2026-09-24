<?php

namespace App\Federations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Everything about a federation beyond what its dashboard draws — see the step-8 migration. */
class FederationProfile extends Model
{
    public const OFFICER_ROLES = ['president', 'secretary_general', 'technical_director', 'head_referees', 'anti_doping', 'safeguarding'];

    protected $fillable = [
        'federation_id', 'email', 'phone', 'office_hours', 'website', 'socials', 'founded_year', 'languages',
        'address', 'lat', 'lng', 'registration_number', 'registration_authority', 'tax_number', 'invoice_name',
        'invoice_address', 'bank_account_name', 'bank_name', 'iban', 'swift', 'officers', 'board',
        'international_federation', 'olympic_recognised', 'updated_by',
    ];

    protected $casts = [
        'socials' => 'array', 'languages' => 'array', 'officers' => 'array', 'board' => 'array',
        'olympic_recognised' => 'boolean', 'lat' => 'float', 'lng' => 'float', 'founded_year' => 'integer',
    ];

    public function federation(): BelongsTo
    {
        return $this->belongsTo(Federation::class);
    }
}
