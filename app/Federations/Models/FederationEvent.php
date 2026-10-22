<?php

namespace App\Federations\Models;

use App\Traits\DeletesUploadedFiles;
use App\Translation\Contracts\LimitsOfferedLocales;
use App\Translation\Contracts\TranslatableContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/** A competition a federation runs. Public key: uuid. */
class FederationEvent extends Model implements LimitsOfferedLocales, TranslatableContent
{
    use DeletesUploadedFiles, SoftDeletes;

    public const STATUSES = ['Planning', 'Registration open', 'Live', 'Completed'];

    protected array $fileUploads = ['logo' => 'public', 'art_portrait' => 'public', 'art_landscape' => 'public'];

    protected $fillable = [
        'federation_id', 'created_by', 'name', 'sport', 'status', 'capacity', 'starts_on', 'ends_on',
        'details', 'logo', 'logo_shape', 'art_portrait', 'art_landscape',
    ];

    protected $casts = [
        'details' => 'array',
        // Test mode (App\Federations\Support\FederationTestMode): NULL = off. Never fillable.
        'test_mode_at' => 'datetime',
        // The languages the organiser offers the invitation in (NULL = English + Arabic).
        'offered_locales' => 'array',
    ];

    /** What the club invitation has always spoken, until the organiser chooses. */
    public const DEFAULT_LOCALES = ['en', 'ar'];

    protected static function booted(): void
    {
        static::creating(fn (FederationEvent $e) => $e->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function federation(): BelongsTo
    {
        return $this->belongsTo(Federation::class);
    }

    /**
     * Over: marked Completed, or past its end (its start, when it has no end).
     * A bare date runs to the end of that day. No dates at all is never over.
     */
    /** The competition's clock: its federation's timezone, else the platform's home. */
    public function zone(): string
    {
        return \App\Events\Models\ClubEvent::firstValidZone($this->federation?->timezone);
    }

    public function hasEnded(?\Illuminate\Support\Carbon $now = null): bool
    {
        if ($this->status === 'Completed') {
            return true;
        }
        $now ??= now();
        foreach ([$this->ends_on, $this->starts_on] as $v) {
            if (! $v) {
                continue;
            }
            // starts_on / ends_on are the hall's wall clock: read them in the federation's zone.
            $at = rescue(fn () => \Illuminate\Support\Carbon::parse($v, $this->zone()), null, report: false);
            if (! $at) {
                continue;
            }
            // Only the END may carry a time that ends the day early.
            $end = ($v === $this->ends_on && strlen((string) $v) > 10) ? $at : $at->endOfDay();

            return $end->lt($now);
        }

        return false;
    }

    /**
     * The ONE status every screen shows (UX brief 2026-10-22, §3.1): the typed
     * status, unless what has happened says otherwise. An event still typed
     * "Planning" or "Registration open" whose dates have passed is Completed;
     * one whose mats have recorded a real bout is Live. The list, the event
     * header and Statistics all read this — before, the same event said
     * Planning on one screen and Completed on another.
     *
     * Read-only: nothing is written back, so the organiser's own status is
     * kept and wins again the moment it is set to anything else.
     */
    public function lifecycleStatus(): string
    {
        $typed = (string) $this->status;
        if (! in_array($typed, ['Planning', 'Registration open'], true)) {
            return $typed;
        }
        if ($this->hasEnded()) {
            return 'Completed';
        }
        $scored = $this->club_event_id && \App\Events\Models\EventMatch::where('event_id', $this->club_event_id)
            ->whereNotNull('winner')->whereNotNull('a_competitor_id')->whereNotNull('b_competitor_id')
            ->where(fn ($q) => $q->whereNull('won_by_bye')->orWhere('won_by_bye', false))->exists();

        return $scored ? 'Live' : $typed;
    }

    // ─── Translation (App\Translation) ───────────────────────────────────

    /**
     * The organiser's words, flat and keyed. `about` is the rich-text body as
     * PLAIN prose (paragraphs and "- " bullets) — the invitation renders it
     * through <x-prose-text>, which escapes it, so no organiser HTML is ever
     * sent to a translator or printed back unescaped.
     */
    public function translatableDocument(): array
    {
        $d = (array) $this->details;
        $doc = [
            'title' => (string) $this->name,
            'about' => self::aboutText($d['about'] ?? ''),
            'venue' => ($d['venue'] ?? '') !== 'TBC' ? (string) ($d['venue'] ?? '') : '',
            'city' => (string) ($d['city'] ?? ''),
            'location' => (string) ($d['location'] ?? ''),
        ];
        foreach (array_values((array) ($d['reqs'] ?? [])) as $i => $line) {
            if (is_string($line) && trim($line) !== '') {
                $doc['requirements.'.$i] = $line;
            }
        }
        foreach (array_values((array) ($d['types'] ?? [])) as $i => $type) {
            if (is_string($type) && trim($type) !== '') {
                $doc['types.'.$i] = $type;
            }
        }

        return array_filter($doc, fn ($v) => trim((string) $v) !== '');
    }

    public function translationContext(): array
    {
        $d = (array) $this->details;

        return [
            'summary' => trim('The public invitation to a '.($this->sport ?: 'sports').' competition run by '
                .($this->federation?->name ?: 'a national federation')
                .' — read by club coaches deciding whether to enter their athletes.'),
            'keep' => array_values(array_filter([
                $this->federation?->name,
                $d['venue'] ?? null,
                $this->federation?->currency,
            ], fn ($v) => is_string($v) && $v !== '' && $v !== 'TBC')),
        ];
    }

    public function sourceLocale(): string
    {
        return $this->source_locale ?: config('app.fallback_locale', 'en');
    }

    public function offeredLocales(): ?array
    {
        $stored = is_array($this->offered_locales) ? array_values(array_filter($this->offered_locales, 'is_string')) : [];

        return $stored !== [] ? $stored : self::DEFAULT_LOCALES;
    }

    /** Rich text (already cleaned on save) as plain prose, keeping paragraphs and bullets. */
    public static function aboutText(mixed $html): string
    {
        if (! is_string($html) || trim($html) === '') {
            return '';
        }
        // A raw line break inside the markup is whitespace to a browser (text
        // pasted from a word processor is full of them) — so it is to us.
        $t = preg_replace('/\s+/u', ' ', $html);
        $t = preg_replace('#<\s*li[^>]*>#i', "\n- ", $t);
        $t = preg_replace('#<\s*(br)\s*/?>#i', "\n", $t);
        $t = preg_replace('#</\s*(p|div|h[1-6]|ul|ol|li|blockquote)\s*>#i', "\n\n", $t);
        $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace("/[ \t]*\n[ \t]*/", "\n", $t);
        $t = preg_replace("/\n{3,}/", "\n\n", $t);

        return trim(mb_substr($t, 0, 20000));
    }

    public function documents(): HasMany
    {
        return $this->hasMany(FederationEventDocument::class)->orderBy('position');
    }
}
