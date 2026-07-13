<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A user-built form. Its structure lives in the `schema` JSON:
 *   schema = { pages: [ { id, title, fields: [ Field ] } ] }
 *   Field  = { id, type, key, label, placeholder, help, required,
 *              options:[{value,label}], validation:{...}, visibleIf:{field,op,value} }
 */
class Form extends Model
{
    /** Field types the builder + renderer support. */
    public const FIELD_TYPES = [
        'heading', 'paragraph',
        'text', 'textarea', 'number', 'email', 'phone', 'date',
        'select', 'radio', 'checkboxes', 'checkbox', 'file', 'terms',
    ];

    public const TYPES = ['registration', 'intake', 'survey', 'generic'];

    protected $fillable = [
        'uuid', 'tenant_id', 'title', 'type', 'description', 'schema', 'settings', 'is_active', 'created_by',
    ];

    protected $casts = [
        'schema' => 'array',
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $form) {
            if (empty($form->uuid)) {
                $form->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(FormSubmission::class);
    }

    /** Flatten all fields across pages (excluding presentational ones). */
    public function fields(): array
    {
        $out = [];
        foreach (($this->schema['pages'] ?? []) as $page) {
            foreach (($page['fields'] ?? []) as $field) {
                if (! in_array($field['type'] ?? '', ['heading', 'paragraph'], true)) {
                    $out[] = $field;
                }
            }
        }

        return $out;
    }
}
