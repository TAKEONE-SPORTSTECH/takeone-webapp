<?php

namespace App\Models;

use App\Traits\BelongsToTenant;
use App\Traits\TranslatesAttributes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use App\Clubs\Models\Tenant;
use App\Translation\Contracts\LimitsOfferedLocales;
use App\Translation\Contracts\TranslatableContent;

class ClubEvent extends Model implements LimitsOfferedLocales, TranslatableContent
{
    use TranslatesAttributes;
    use BelongsToTenant, HasFactory, LogsActivity;

    protected static function booted(): void
    {
        static::creating(function (self $event) {
            if (empty($event->uuid)) {
                $event->uuid = (string) \Illuminate\Support\Str::uuid();
            }

            /*
             * The language the organiser was writing in.
             *
             * Recorded HERE rather than in each of the four forms that create
             * an event, because a translator that guesses the source language
             * does not fail loudly — it produces fluent nonsense, confidently.
             * The interface language they were using is the best available
             * guess and the organiser can correct it.
             *
             * Only ever a CONTENT locale (config/content_locales.php); an app
             * locale we do not translate from is left null, which falls back to
             * the app default exactly as before. See App\Translation.
             */
            if (empty($event->source_locale)) {
                $event->source_locale = \App\Translation\Translations::locales()
                    ->normalise(app()->getLocale());
            }
        });

        // Entries cannot close AFTER the event they are for. The create form
        // already refuses it, but a seeder, an import or an admin path could
        // still write it — and an event advertising a closing date past its own
        // start reads as open when it is not.
        static::saving(function (self $event) {
            if ($event->enrollment_ends_at && $event->date && $event->enrollment_ends_at->gt($event->date)) {
                $event->enrollment_ends_at = $event->date;
            }
        });

        // Keep the money in step with the display line, whichever a caller set.
        //
        // Several forms still post only the sentence ("BHD 20"), assembled in
        // the browser from an amount and the club's currency. Rather than trust
        // that string later — which is how "10-15 BHD" came to bill 10 — the
        // number is derived here, once, at the edge. A caller that sets the
        // amount itself is authoritative and is never overwritten.
        static::saving(function (self $event) {
            if (! $event->fee_currency) {
                $event->fee_currency = $event->tenant?->currency ?: 'BHD';
            }

            foreach (['participant_fee', 'spectator_fee'] as $column) {
                $amountColumn = $column.'_amount';

                if (! $event->isDirty($column) || $event->isDirty($amountColumn)) {
                    continue;
                }

                /*
                 * ⚠️ Never scrape an event that is priced by its FEE LIST.
                 *
                 * Since 2026-09-06 the price of such an event lives in
                 * `event_fee_options`, and its display line is composed from
                 * them — "From BHD 5". Scraping that sentence put a 5 back into
                 * the base column, and every entrant was then charged a phantom
                 * five on top of whatever they ticked. Marking the amount dirty
                 * at the call site does not help either: writing 0 over a 0 is
                 * not a change, so the guard has to be here.
                 *
                 * The scrape survives only for the rows it was written for —
                 * an event with no options whose form still posts a sentence.
                 */
                $role = $column === 'spectator_fee' ? 'spectator' : 'participant';

                if ($event->exists && \App\Events\Support\EventFee::hasOptions($event, $role)) {
                    continue;
                }

                $event->{$amountColumn} = \App\Events\Support\EventFee::parse($event->{$column});
            }
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('club')
            ->logOnly(['title', 'date', 'end_date', 'status', 'scope', 'is_archived', 'max_capacity'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $table = 'club_events';

    public const DRAW_ALWAYS = 'always';

    public const DRAW_START_DAY = 'start_day';

    public const DRAW_HIDDEN = 'hidden';

    /** Every legal value of `draw_reveal`, for validation and for the console. */
    public const DRAW_REVEALS = [self::DRAW_ALWAYS, self::DRAW_START_DAY, self::DRAW_HIDDEN];

    protected $fillable = [
        'tenant_id',
        'title',
        'description',
        'date',
        'end_date',
        'start_time',
        'end_time',
        'weigh_in_at',
        'enrollment_starts_at',
        'enrollment_ends_at',
        'minutes_per_match',
        'courts',
        'break_minutes',
        'day_courts',
        'scoreboard_settings',
        'location',
        'gps_lat',
        'gps_long',
        'location_url',
        'break_start',
        'break_end',
        'level',
        'max_capacity',
        'cancel_within_days',
        'spots_taken',
        'ribbon_label',
        'ribbon_type',
        'tags',
        'images',
        'color',
        'cta_text',
        'status',
        'scope',
        // Whether this event has a page anybody may open — see the migration.
        'entry_mode',
        // ...and whether an entry made through it needs the organiser to say
        // yes. Off by default; the two are separate decisions.
        'public_entry_auto_accept',
        // When the draw becomes readable: always | start_day | hidden.
        // See the migration — the organiser's call, never a per-viewer one.
        'draw_reveal',
        'notify_countries',
        'uuid',
        // The language the organiser wrote this event IN — see App\Translation.
        'source_locale',
        'is_archived',
        // mobile Events extensions
        'event_type',
        'sport',
        'league',
        'icon',
        // `*_fee` is the DISPLAY line; `*_fee_amount` + `fee_currency` are the
        // money. See App\Events\Support\EventFee — nothing computes a price
        // from the string any more.
        'participant_fee',
        'participant_fee_amount',
        'spectator_enabled',
        'spectator_fee',
        'spectator_fee_amount',
        'fee_currency',
        // The late-entry penalty. Not fillable until 2026-09-06, which meant the
        // organiser's own create/edit form set them, got a success toast, and
        // silently stored nothing — mass assignment discards a key that is not
        // listed here, without a word. The club-admin form assigned the
        // properties directly and DID save, which made the bug look intermittent.
        'late_fee_amount',
        'late_fee_from',
        'prize',
        'results',
        'requirements',
        'phases',
        'agenda',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'end_date' => 'date',
        'weigh_in_at' => 'datetime',
        'enrollment_starts_at' => 'date',
        'enrollment_ends_at' => 'date',
        'tags' => 'array',
        'notify_countries' => 'array',
        'images' => 'array',
        'max_capacity' => 'integer',
        'cancel_within_days' => 'integer',
        'spots_taken' => 'integer',
        'minutes_per_match' => 'integer',
        'courts' => 'integer',
        'break_minutes' => 'integer',
        'day_courts' => 'array',
        'offered_locales' => 'array',
        'scoreboard_settings' => 'array',
        'is_archived' => 'boolean',
        'spectator_enabled' => 'boolean',
        'participant_fee_amount' => 'decimal:3',
        'spectator_fee_amount' => 'decimal:3',
        'late_fee_amount' => 'decimal:3',
        // Without this the attribute comes back a bare string and every reader
        // that treats it as a date — the show payload's ->toIso8601String(),
        // the edit form's ->format() — is a fatal, not a wrong answer.
        'late_fee_from' => 'datetime',
        'requirements' => 'array',
        'phases' => 'array',
        'agenda' => 'array',
        'results' => 'array',
        'league' => 'array',
        'started_at' => 'datetime',
        'start_overridden' => 'boolean',
        'public_entry_auto_accept' => 'boolean',
    ];

    /**
     * True once the event's end has passed.
     * - Uses end_date (end of that day) if set.
     * - Falls back to date + end_time, or end of start day.
     */
    public function hasEnded(): bool
    {
        if ($this->end_date) {
            return $this->end_date->copy()->endOfDay()->isPast();
        }

        if ($this->end_time) {
            return $this->date->copy()->setTimeFromTimeString($this->end_time)->isPast();
        }

        // No explicit end info — never auto-archive
        return false;
    }

    /**
     * True while the event is running (started but not yet ended).
     */
    public function isOngoing(): bool
    {
        return $this->hasStarted() && ! $this->hasEnded();
    }

    /**
     * True once someone has STARTED this event.
     *
     * Deliberately not the clock. The start time is a plan; starting is a
     * decision, made by the organiser once the hall is ready — and the
     * readiness checklist can hold that decision back. An event whose start
     * time has passed but which nobody started is late, not running, and its
     * draw is still arrangeable.
     *
     * Everything that asks "may this still be changed?" (draw locking, the
     * arrange screens, the package actions) reads this one method, so there is
     * exactly one answer.
     */
    public function hasStarted(): bool
    {
        return $this->started_at !== null;
    }

    /**
     * Has the draw been let out yet?
     *
     * The CLOCK half of the rule, and only that half: it says nothing about who
     * is asking. Who may read a concealed draw anyway — the organiser and the
     * officials building it — is an authorization question and lives in
     * App\Events\Support\EventAccess::drawVisible(), which calls this.
     *
     * `start_day` opens on the morning of the event rather than at a start
     * time, because an athlete arrives at the hall before the first bout and
     * the draw is the first thing they look for. Pressing start opens it too:
     * an event that is running has no draw left to conceal.
     */
    public function drawRevealed(): bool
    {
        return match ($this->draw_reveal ?? self::DRAW_ALWAYS) {
            self::DRAW_HIDDEN => false,
            self::DRAW_START_DAY => $this->hasStarted()
                || $this->hasEnded()
                || ($this->date && ! $this->date->copy()->startOfDay()->isFuture()),
            // `always`, and anything unrecognised: the draw is readable. An
            // unknown value must fail OPEN here — every event that predates
            // this column has no value at all, and they all published a draw.
            default => true,
        };
    }

    /** The moment the clock says it was MEANT to begin. */
    public function scheduledStart(): ?\Carbon\Carbon
    {
        if (! $this->date) {
            return null;
        }

        return $this->date->copy()->setTimeFromTimeString($this->start_time ?: '00:00');
    }

    /** Its scheduled start has passed and nobody has started it. */
    public function isOverdueToStart(): bool
    {
        $due = $this->scheduledStart();

        return ! $this->hasStarted() && ! $this->hasEnded() && $due !== null && $due->isPast();
    }

    /** True when this event's sport is a registered combat sport (config/combat.php). */
    public function isCombat(): bool
    {
        return $this->sport && array_key_exists($this->sport, config('combat.sports', []));
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(EventCategory::class, 'event_id')->orderBy('sort_order')->chaperone();
    }

    public function matches(): HasMany
    {
        return $this->hasMany(EventMatch::class, 'event_id');
    }

    /** People appointed to officiate this event (jury by default). */
    public function officials(): HasMany
    {
        return $this->hasMany(EventOfficial::class, 'event_id');
    }

    /** What must be true before the day can begin, in the organiser's order. */
    public function checklistItems(): HasMany
    {
        return $this->hasMany(EventChecklistItem::class, 'event_id')
            ->orderBy('sort_order')->orderBy('id')->chaperone();
    }

    /**
     * Nothing on the checklist is still outstanding.
     *
     * An event with no checklist is ready by definition — the organiser who
     * never wrote a list is not thereby blocked from starting their event.
     */
    public function isReadyToStart(): bool
    {
        return $this->outstandingChecks() === 0;
    }

    /** How many checklist items are still unchecked. */
    public function outstandingChecks(): int
    {
        return $this->checklistItems()->whereNull('checked_at')->count();
    }

    /** Participant registrations (not spectators). */
    public function participantRegistrations(): HasMany
    {
        return $this->hasMany(ClubEventRegistration::class, 'event_id')->where('role', 'participant');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(ClubEventRegistration::class, 'event_id');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(EventExpense::class, 'event_id');
    }

    /** Files attached for people to download (rulebook, entry form, schedule). */
    public function documents(): HasMany
    {
        return $this->hasMany(EventDocument::class, 'event_id')->orderBy('sort_order');
    }

    /*
    |--------------------------------------------------------------------------
    | Readable in any language — App\Translation
    |--------------------------------------------------------------------------
    |
    | The organiser writes the event once, in their own language. Everything
    | below is what that means concretely: which of this row's strings are
    | WORDS (as opposed to a colour, a uuid, a status or a price), what a
    | translator needs to know to render them well, and which language they
    | arrived in.
    |
    | Nothing here changes how the event is stored, validated or displayed
    | today. A record with no translations answers every read with its own
    | text, exactly as before.
    */

    /**
     * This event's words, keyed for storage.
     *
     * ⚠️ The keys are an API. A stored translation is filed under its key, so
     * renaming one orphans every translation of that field in every language.
     *
     * List keys are dotted with the child's own DATABASE ID, never its position
     * — `divisions.418`, not `divisions.0`. Divisions and fee lines are
     * reorderable, and position-keyed translations would silently swap places
     * with each other the first time an organiser dragged a row: the Portuguese
     * for "Adult Black Belt" appearing under "Juvenile Blue". `requirements` is
     * the one list keyed by position, because it is a plain JSON array with no
     * ids to key by — reordering it re-translates it, which is the cheap,
     * correct failure.
     *
     * @return array<string, string>
     */
    public function translatableDocument(): array
    {
        /*
         * ⚠️ `translationSource()`, never `$this->title`.
         *
         * These columns translate on read now, and PHP will not re-enter
         * `__get()` for a key whose `__get()` is already on the stack — so
         * `{{ $event->title }}` reaching this method and finding `$this->title`
         * raises "Undefined property", the trait catches it, and the page
         * silently renders the source language having done all the work. The
         * full note is on App\Traits\TranslatesAttributes::translationSource().
         */
        $document = [
            'title' => $this->translationSource('title'),
            'about' => $this->translationSource('description'),
            // A venue is often a NAME ("Isa Sports City, Hall 2") and the agent
            // is told to leave names alone — but it is just as often a
            // described place ("the small hall behind the pool"), so it is
            // offered and the agent decides.
            'location' => $this->translationSource('location'),
            'level' => $this->translationSource('level'),
            'prize' => $this->translationSource('prize'),
            'cta_text' => $this->translationSource('cta_text'),
            'ribbon_label' => $this->translationSource('ribbon_label'),
        ];

        foreach (array_values((array) ($this->requirements ?: [])) as $i => $line) {
            if (is_string($line)) {
                $document['requirements.'.$i] = $line;
            }
        }

        /*
         * Divisions and fee lines live in their own tables but are read as part
         * of THIS page, so they travel in this document: one translation job
         * per event rather than one per row, and — the reason that matters —
         * the agent sees the divisions and the description together, so the
         * words it picks for a belt rank in one match the words it picks in the
         * other.
         *
         * `relationLoaded` guards keep this cheap on a page that already
         * eager-loaded them, and correct on one that did not.
         */
        foreach ($this->categories as $category) {
            if (! $category->is_heading && filled($category->translationSource('name'))) {
                $document['divisions.'.$category->id] = $category->translationSource('name');
            }
        }

        foreach ($this->feeOptions as $option) {
            if (filled($option->translationSource('label'))) {
                $document['fees.'.$option->id] = $option->translationSource('label');
            }
        }

        /*
         * The readiness list. Not a poster fact — an official reads it at the
         * venue on the morning — but it is the organiser's own words all the
         * same, and it was the last piece of event text with no way to be read
         * in anything but the language it was typed in.
         */
        foreach ($this->checklistItems as $item) {
            if (filled($item->translationSource('label'))) {
                $document['checklist.'.$item->id] = $item->translationSource('label');
            }
        }

        return $document;
    }

    /**
     * What the translator needs to know that the text alone does not say.
     *
     * The `keep` list is the important half. Everything in it is a NAME, and a
     * name that gets translated is not a cosmetic error: a competitor who
     * cannot find the venue because it was rendered into their language has
     * been sent to the wrong building by this feature.
     *
     * @return array{summary: string, keep: array<int, string>}
     */
    public function translationContext(): array
    {
        $sport = $this->sport
            ? (config('event_schema.sports.'.$this->sport.'.label') ?: $this->sport)
            : null;

        $summary = trim(implode(' ', array_filter([
            'The public page of a'.($sport ? ' '.$sport : '').' competition',
            $this->tenant?->club_name ? 'hosted by '.$this->tenant->club_name : null,
            $this->tenant?->country ? 'in '.$this->tenant->country : null,
            '— read by athletes, coaches and their families deciding whether to enter.',
        ])));

        $keep = array_filter([
            $this->tenant?->club_name,
            // Source, for the same __get re-entrancy reason as above.
            $this->translationSource('location'),
            $this->fee_currency,
        ]);

        // A division's name carries the sport's own vocabulary and its rank
        // ladder; showing them to the agent as names-in-context stops "Brown
        // Belt" being rendered as a description of a belt's colour.
        foreach ($this->categories as $category) {
            if (filled($category->weight_class)) {
                $keep[] = (string) $category->weight_class;
            }
        }

        return [
            'summary' => $summary,
            'keep' => array_values(array_unique(array_map('strval', $keep))),
        ];
    }

    /** The language this event was written in. */
    public function sourceLocale(): string
    {
        return $this->source_locale ?: config('app.fallback_locale', 'en');
    }

    /**
     * The languages this event's poster offers, or NULL for all of them.
     *
     * Deliberately NOT mass-assignable: it is written by one endpoint — the
     * translation module's own `me.events.translations.offered` — which checks
     * who is asking and validates every code against the served list. A
     * language allow-list arriving through an event's own edit form would be a
     * list of arbitrary strings landing in a column that decides what a public
     * page will serve.
     *
     * (Named by ROUTE rather than by class on purpose: a module's controllers
     * are private, and ModuleBoundaryTest reads comments too — correctly, since
     * a comment naming an internal is the first step to code doing it.)
     *
     * The stored value is normalised on the way in, so this only has to hand
     * back what is there — and hands back null rather than an empty array when
     * the column is empty, because the contract makes those mean the same thing
     * and one shape is easier to reason about than two.
     *
     * @return array<int, string>|null
     */
    public function offeredLocales(): ?array
    {
        $stored = $this->offered_locales;

        if (! is_array($stored)) {
            return null;
        }

        $clean = array_values(array_filter($stored, 'is_string'));

        return $clean === [] ? null : $clean;
    }

    /** The priced entry lines — "Gi entry", "Late entry" — whose labels are words. */
    public function feeOptions(): HasMany
    {
        return $this->hasMany(EventFeeOption::class, 'event_id')->orderBy('sort')->chaperone();
    }

    /**
     * The columns that hold WORDS, and where each one's translation lives in
     * this event's document.
     *
     * Reading any of them now returns the reader's language (App\Traits\
     * TranslatesAttributes). To get the organiser's own text — an edit form,
     * a comparison, the translator itself — say so:
     * `Translations::source(fn () => $event->title)`.
     *
     * `requirements` is a JSON array and is not listed here: an attribute
     * accessor returns one value, and the list is resolved as a list by
     * `TranslatedDocument::list()`, which keeps the ORIGINAL's length and order.
     *
     * @return array<string, string>
     */
    protected function translatedAttributes(): array
    {
        return [
            'title' => 'title',
            // ⚠️ The column is `description`; the document calls it `about`.
            // They have always differed, and conflating them would silently
            // translate nothing.
            'description' => 'about',
            'location' => 'location',
            'level' => 'level',
            'prize' => 'prize',
            'cta_text' => 'cta_text',
            'ribbon_label' => 'ribbon_label',
        ];
    }
}
