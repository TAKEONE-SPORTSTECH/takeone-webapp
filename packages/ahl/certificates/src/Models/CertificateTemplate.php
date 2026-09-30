<?php

namespace Ahl\Certificates\Models;

use Ahl\Certificates\Certificates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * One editable template per certificate type and OWNER. Title and body are per-locale maps
 * ({"en": "…", "ar": "…"}). Signature images and uploaded background artwork live in the
 * FileStore (slots signature_1 / signature_2 / background).
 *
 * The owner is a plain (type, id) pair decided by the host — a school, a club, one event.
 * ('', 0) is the application-wide template: every 1.0 template, and the fallback when a
 * host names no owner at all.
 */
class CertificateTemplate extends Model
{
    public const SIGNATURE_SLOTS = [1, 2];

    public const BACKGROUND_SLOT = 'background';

    /** Built-in backgrounds, drawn by Support\Backgrounds, plus uploaded artwork and blank paper. */
    public const BACKGROUNDS = ['plain', 'guilloche', 'watermark', 'motif', 'artwork', 'blank'];

    public const ORNAMENTS = ['full', 'minimal', 'off'];

    protected $table = 'certificate_templates';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'title' => 'array',
            'body' => 'array',
            'show_photo' => 'boolean',
            'owner_id' => 'integer',
            'print_offset_x' => 'integer',
            'print_offset_y' => 'integer',
            'layout' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CertificateTemplate $t) {
            $t->uuid ??= (string) Str::uuid();
            $t->owner_type ??= '';
            $t->owner_id ??= 0;
            $t->background ??= 'plain';
            $t->ornament_level ??= 'full';
            $t->show_photo ??= false;
            $t->print_offset_x ??= 0;
            $t->print_offset_y ??= 0;
        });
    }

    /**
     * The template for a type and owner, created from the default texts on first use.
     * Without an owner this is the application-wide template, exactly as in 1.0.
     *
     * @param  array{0:string,1:int|string}|null  $owner
     */
    public static function forType(string $type, ?array $owner = null): static
    {
        [$ownerType, $ownerId] = static::ownerKey($owner);

        return static::query()->firstOrCreate(['type' => $type, 'owner_type' => $ownerType, 'owner_id' => $ownerId], [
            'title' => static::defaults($type, 'title'),
            'body' => static::defaults($type, 'body'),
            'ornament_level' => 'full',
            'show_photo' => false,
            'background' => 'plain',
        ]);
    }

    /**
     * The first template that exists along an owner chain (most specific first). When
     * none does, the template is created for the LAST owner in the chain — the broadest
     * one — so a default is set once and every narrower owner inherits it.
     *
     * @param  list<array{0:string,1:int|string}>  $owners
     */
    public static function resolve(string $type, array $owners): static
    {
        $owners = $owners === [] ? [null] : $owners;

        foreach ($owners as $owner) {
            [$ownerType, $ownerId] = static::ownerKey($owner);
            $found = static::query()->where('type', $type)->where('owner_type', $ownerType)->where('owner_id', $ownerId)->first();
            if ($found) {
                return $found;
            }
        }

        return static::forType($type, $owners[array_key_last($owners)]);
    }

    /** @return array{0:string,1:int} */
    public static function ownerKey(?array $owner): array
    {
        if ($owner === null) {
            return ['', 0];
        }

        return [(string) ($owner[0] ?? ''), (int) ($owner[1] ?? 0)];
    }

    public function scopeOwnedBy(Builder $q, ?array $owner): Builder
    {
        [$ownerType, $ownerId] = static::ownerKey($owner);

        return $q->where('owner_type', $ownerType)->where('owner_id', $ownerId);
    }

    public function ownedBy(?array $owner): bool
    {
        return static::ownerKey($owner) === [(string) $this->owner_type, (int) $this->owner_id];
    }

    /** @return array<string, string> default text per locale */
    public static function defaults(string $type, string $field): array
    {
        $prefix = config('certificates.defaults_translation', 'certificates::certificates.defaults');

        return collect(Certificates::locales())->mapWithKeys(function ($locale) use ($prefix, $type, $field) {
            $key = "{$prefix}.{$type}.{$field}";
            $text = __($key, [], $locale);
            if ($text === $key) {
                $text = __("{$prefix}.default.{$field}", [], $locale);
            }

            return [$locale => is_string($text) && ! str_starts_with($text, $prefix) ? $text : ''];
        })->all();
    }

    public function title(string $locale): string
    {
        return $this->text('title', $locale);
    }

    public function body(string $locale): string
    {
        return $this->text('body', $locale);
    }

    /** The background actually drawn for a purpose: blank paper is for printing only. */
    public function backgroundFor(string $purpose): string
    {
        $bg = in_array($this->background, self::BACKGROUNDS, true) ? $this->background : 'plain';

        return $bg === 'blank' && $purpose !== 'print' ? 'plain' : $bg;
    }

    private function text(string $field, string $locale): string
    {
        $map = (array) ($this->{$field} ?? []);

        return (string) ($map[$locale] ?? $map[config('certificates.fallback_locale', 'en')] ?? collect($map)->first(fn ($v) => $v !== null && $v !== '') ?? '');
    }
}
