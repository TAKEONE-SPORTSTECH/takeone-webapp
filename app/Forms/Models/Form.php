<?php

namespace App\Forms\Models;

use App\Forms\Models\FormSubmission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use App\Clubs\Models\Tenant;
use App\Members\Models\User;

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
        // Survey types — drawn only by the paged (bilingual) layout.
        'scale', 'grid', 'rank', 'numbers', 'note',
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

    /**
     * The one-question-at-a-time, English + Arabic layout. Opt-in per form
     * (`settings.layout = paged`), so every form made before it keeps the
     * single-card page it always had.
     */
    public function isPaged(): bool
    {
        return ($this->settings['layout'] ?? null) === 'paged';
    }

    /**
     * Does this request carry the visitor's link? The visitor sees the
     * private pages (cover, observations, visit summary); the club does not.
     * The token lives only in settings and is compared in constant time.
     */
    public function isVisit(?string $token): bool
    {
        $real = (string) ($this->settings['visitToken'] ?? '');

        return $real !== '' && is_string($token) && hash_equals($real, $token);
    }

    /**
     * The cover artwork's address, or null. Served through forms.cover (never
     * a storage path); the query string changes whenever the file does, so a
     * replaced picture is never shown from a stale cache.
     */
    public function coverUrl(): ?string
    {
        $path = $this->settings['cover'] ?? null;

        return $path ? route('forms.cover', $this->uuid).'?v='.substr(md5($path), 0, 10) : null;
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
