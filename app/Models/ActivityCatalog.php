<?php

namespace App\Models;

use App\Traits\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A single entry in the global activity directory. Shared across every club
 * (deliberately NOT tenant-scoped). See the migration for rationale.
 */
class ActivityCatalog extends Model
{
    use HasFactory, HasTranslations;

    protected $table = 'activity_catalog';

    protected array $translatable = ['name', 'description'];

    protected $fillable = [
        'uuid', 'name', 'slug', 'description', 'translations', 'variants', 'image_prompt',
        'picture_url', 'icon', 'is_active', 'usage_count', 'source_tenant_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'usage_count' => 'integer',
        'variants' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $entry) {
            if (empty($entry->uuid)) {
                $entry->uuid = (string) Str::uuid();
            }
            if (empty($entry->slug) && filled($entry->name)) {
                $entry->slug = static::uniqueSlug($entry->name);
            }
        });
    }

    /**
     * Canonical slug for an activity name (used to dedupe across clubs).
     */
    public static function slugFor(string $name): string
    {
        return Str::slug(trim($name)) ?: 'activity';
    }

    /**
     * Map a set of club-activity names to the public directory page of the matching
     * catalog entry: ['karate (kyokushin)' => 'https://…/activity/{uuid}', …].
     *
     * Club activities carry no FK to the directory — they are matched the same way
     * `contribute()` dedupes them (canonical slug, or the normalized name), so an
     * activity typed slightly differently in one club still finds its entry.
     *
     * One query for the whole page: build the map once in the parent view and hand
     * it to the cards, never per card.
     *
     * @param  iterable<string>  $names
     * @return array<string, string>  keyed by lower-cased trimmed name
     */
    public static function linksForNames(iterable $names): array
    {
        $names = collect($names)
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->values();

        if ($names->isEmpty()) {
            return [];
        }

        $slugs = $names->map(fn ($n) => static::slugFor($n))->all();
        $lower = $names->map(fn ($n) => mb_strtolower($n))->all();

        $entries = static::query()
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereIn('slug', $slugs)
                ->orWhereIn(DB::raw('LOWER(TRIM(name))'), $lower))
            ->get(['uuid', 'name', 'slug']);

        $bySlug = $entries->keyBy('slug');
        $byName = $entries->keyBy(fn ($e) => mb_strtolower(trim($e->name)));

        return $names->mapWithKeys(function ($name) use ($bySlug, $byName) {
            $entry = $byName->get(mb_strtolower($name)) ?? $bySlug->get(static::slugFor($name));

            return [mb_strtolower($name) => $entry ? route('activity.show', $entry->uuid) : null];
        })->filter()->all();
    }

    /**
     * Add a suggested style/federation (deduped case-insensitively by name).
     * Does not persist — caller saves. Shape stored: [{name, name_ar}].
     */
    public function addVariant(string $name, ?string $nameAr = null): static
    {
        $name = trim($name);
        if ($name === '') {
            return $this;
        }

        $variants = $this->variants ?? [];
        foreach ($variants as $v) {
            if (mb_strtolower(trim((string) ($v['name'] ?? ''))) === mb_strtolower($name)) {
                return $this; // already present
            }
        }

        $variants[] = array_filter([
            'name' => $name,
            'name_ar' => $nameAr ?: null,
        ], fn ($x) => $x !== null);

        $this->variants = $variants;

        return $this;
    }

    protected static function uniqueSlug(string $name): string
    {
        $base = static::slugFor($name);
        $slug = $base;
        $i = 2;
        while (static::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /**
     * Add an activity to the directory (or return the existing one) keyed by
     * canonical slug — so the same activity from different clubs collapses to
     * one shared entry. Best-effort enrichment: fills a missing description /
     * picture / Arabic name from the contributing club.
     */
    public static function contribute(array $data, ?int $tenantId = null): self
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            // Should never happen (callers validate name) — fail safe.
            $name = 'Activity';
        }

        // Dedupe by canonical slug OR by normalized name — a curated entry may
        // use a hand-picked slug (e.g. "taekwondo-wt") that differs from the
        // auto slug of the same name ("taekwondo-wt-olympic"); matching on name
        // too prevents a second entry for the same activity.
        $slug = static::slugFor($name);
        $entry = static::query()
            ->where('slug', $slug)
            ->orWhereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($name)])
            ->first();

        if (! $entry) {
            $entry = new static;
            $entry->slug = $slug;
            $entry->name = $name;
            $entry->source_tenant_id = $tenantId;
            $entry->is_active = true;
        }

        // Fold the club's chosen style/federation into the discipline's suggested
        // list so the directory learns real-world styles over time.
        $style = trim((string) ($data['style'] ?? ''));
        if ($style !== '') {
            $entry->addVariant($style, trim((string) data_get($data, 'style_ar', '')) ?: null);
        }

        // Enrich only empty fields so we never clobber a curated entry.
        if (blank($entry->description) && filled($data['description'] ?? null)) {
            $entry->description = $data['description'];
        }
        if (blank($entry->picture_url) && filled($data['picture_url'] ?? null)) {
            $entry->picture_url = $data['picture_url'];
        }

        // Merge any Arabic name/description the club supplied, but only into a
        // slot the directory doesn't already have — never overwrite curated AR.
        $tr = $data['translations'] ?? null;
        if (is_array($tr)) {
            foreach (['name', 'description'] as $field) {
                $ar = data_get($tr, "{$field}.ar");
                $existingAr = data_get($entry->translations, "{$field}.ar");
                if (filled($ar) && blank($existingAr)) {
                    $entry->setTranslation($field, 'ar', $ar);
                }
            }
        }

        $entry->save();

        return $entry;
    }
}
