<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A configured AI provider (one per modality). The API key is stored encrypted
 * and must never be serialised to the client — see $hidden.
 */
class AiProvider extends Model
{
    protected $fillable = [
        'name', 'modality', 'driver', 'base_url', 'api_key', 'model', 'options', 'is_default', 'enabled',
    ];

    protected $casts = [
        'api_key' => 'encrypted',
        'options' => 'array',
        'is_default' => 'boolean',
        'enabled' => 'boolean',
    ];

    // Never leak the (decrypted) key or its presence detail to JSON responses.
    protected $hidden = ['api_key'];

    public const MODALITIES = ['text', 'tts', 'stt', 'image'];

    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    public function scopeModality($query, string $modality)
    {
        return $query->where('modality', $modality);
    }

    /** True when a usable key/endpoint is present (for UI status, without exposing the key). */
    public function getConfiguredAttribute(): bool
    {
        return filled($this->base_url) || filled($this->api_key);
    }
}
