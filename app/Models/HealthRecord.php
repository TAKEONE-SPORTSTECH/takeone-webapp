<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HealthRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'height',
        'recorded_at',
        'weight',
        'body_fat_percentage',
        'bmi',
        'body_water_percentage',
        'muscle_mass',
        'bone_mass',
        'visceral_fat',
        'bmr',
        'protein_percentage',
        'body_age',
    ];

    protected $casts = [
        'recorded_at' => 'date',
        'height' => 'decimal:2',
        'weight' => 'decimal:2',
        'body_fat_percentage' => 'decimal:2',
        'bmi' => 'decimal:2',
        'body_water_percentage' => 'decimal:2',
        'muscle_mass' => 'decimal:2',
        'bone_mass' => 'decimal:2',
        'visceral_fat' => 'integer',
        'bmr' => 'integer',
        'protein_percentage' => 'decimal:2',
        'body_age' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
