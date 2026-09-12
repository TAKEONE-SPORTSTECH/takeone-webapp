@props([
    /* The divisions to seed the list with.
       · server mode — the payloads PersonalEventController::divisionPayload()
         returns: {id,name,weight_class,status,entrants,matches,range,capacity,format,schedule}
       · local mode  — plain rows the host form owns: {name,capacity,schedule,…} */
    'divisions' => [],

    'event' => null,            // event uuid — REQUIRED when server mode
    'server' => false,          // true → save over AJAX; false → edit a host Alpine array
    'model' => null,            // local mode: Alpine path in the PARENT scope holding the array
    'phases' => [],             // [['key' => 'finals', 'label' => 'Finals'], …]
    'days' => 1,                // how many days the event runs over
    'color' => '#7c6bf5',       // the event's colour, for the sheet's band
    'canManage' => true,        // false → read-only list, no add / edit / delete

    /* The sport's own reference tables, straight from the event type's
       formCatalog(): {weight_divisions: {Band: {male: [...], female: [...]}},
       age_bands: {Band: {min, max}}}. Given them, the list offers the
       federation's weight classes as a preset; given nothing, it offers no
       preset and behaves exactly as before. */
    'presets' => [],
])

{{--
    Divisions — the short version.

    A division is what a bracket is cut from, and the block that used to ask for
    one was a wall: a card per division carrying a name, a capacity, three day
    selectors and (once ranges arrived) six more fields. Ten divisions made a
    page nobody could read.

    So the resting state is a LIST — name, one summary line, a chevron — and one
    division at a time is edited in a bottom sheet. Everything the old block
    asked for is still asked for; it is just asked for one division at a time.

    ── Two modes, one editor ─────────────────────────────────────────────────
    The same screen is used before the event exists and after, because an
    organiser does not think of those as two jobs.

      · server = true   the event has a row, so every change is saved the moment
                        it is made and the list patches itself (No-Reload).
      · server = false  the event is still a form, so the component edits the
                        plain array the host owns and the host posts it with
                        everything else. Nothing here touches the network.

    ── Standalone ────────────────────────────────────────────────────────────
    It owns its markup, its Alpine state, its requests and its sheet. The only
    thing it needs from a host is either an event uuid (server) or the name of
    an array in the surrounding Alpine scope (local). It announces every change
    on the window as `event-divisions-changed`
    ({action: 'created'|'updated'|'deleted', division}) so a bracket or a console
    beside it can react without this component knowing they exist.
--}}

@php
    // The colour is organiser-supplied and lands in a `style` attribute, so it
    // is whitelisted before it gets there. `b0` is the alpha suffix Design
    // Rule #8 requires — hex only, which is why the value must be a hex.
    $c = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $color) ? $color : '#7c6bf5';

    $isServer = (bool) $server && $event;
    $dayCount = max(1, (int) $days);

    // Phases, normalised so the sheet can loop them without guessing a shape.
    $phaseList = [];
    foreach ((array) $phases as $p) {
        $key = is_array($p) ? ($p['key'] ?? null) : (is_string($p) ? $p : null);
        if (! $key) {
            continue;
        }
        $phaseList[] = ['key' => (string) $key, 'label' => (string) (is_array($p) ? ($p['label'] ?? $key) : $key)];
    }

    $seed = array_values(array_map(fn ($d) => (array) $d, (array) $divisions));

    $urls = $isServer ? [
        // update/destroy are this same path plus the id — one base, so a route
        // rename cannot leave two of the three behind.
        'store' => route('me.events.divisions.store', $event),
        'base' => route('me.events.divisions.store', $event),
        // The order of the list, which is the only thing that gives a heading
        // meaning. Its own endpoint because it writes one column and must never
        // be confused with saving a division.
        'order' => route('me.events.divisions.order', $event),
        // Who a proposed range would hold, asked while it is still being typed.
        'preview' => route('me.events.divisions.preview', $event),
    ] : null;

    // The drawn 3:4 portrait a person falls back to (App\Support\Avatars).
    $avatars = \App\Support\Avatars::fallbacks();

    /* The sport's own belt ladder, so a division can say which grades it is for
       instead of that being typed into its name. One list (BeltRank::ladder),
       lowercase values — the same vocabulary the weigh-in desk writes. */
    /* The preset table, reduced to what the picker needs and nothing else: a
       band, a gender, and the classes under them. Built here rather than in the
       script so an event type with no table sends no table. */
    $presetTable = [];

    /* One entry per RULESET the sport weighs in under. Gi and no-gi are two
       tables, not one table with a couple of kilos taken off: an athlete
       weighs in without a gi, so both the limits and the ladder differ. A
       sport that publishes only one simply offers one. */
    $presetRulesets = array_filter([
        'gi' => $presets['weight_divisions'] ?? null,
        'nogi' => $presets['weight_divisions_nogi'] ?? null,
    ]);

    foreach ($presetRulesets as $ruleset => $table) {
    foreach ((array) $table as $band => $byGender) {
        if (! is_array($byGender)) {
            continue;
        }

        $ages = $presets['age_bands'][$band] ?? null;
        $entry = ['ruleset' => $ruleset, 'band' => (string) $band,
            'min_age' => $ages['min'] ?? null, 'max_age' => $ages['max'] ?? null, 'genders' => []];

        foreach ($byGender as $gender => $classes) {
            if (! is_array($classes) || ! in_array(strtolower((string) $gender), ['male', 'female'], true)) {
                continue;
            }

            $entry['genders'][strtolower((string) $gender)] = array_values(array_map(fn ($c) => [
                'label' => (string) ($c['label'] ?? ''),
                'name' => $c['name'] ?? null,
                // 0 and 200 are the table's way of writing an open end; the
                // division stores NULL for "no bound", so they are translated
                // here rather than every reader having to know the convention.
                'min' => ($c['min'] ?? 0) > 0 ? (float) $c['min'] : null,
                'max' => ($c['max'] ?? 0) >= 200 ? null : (float) ($c['max'] ?? 0),
            ], $classes));
        }

        if ($entry['genders']) {
            $presetTable[] = $entry;
        }
    }
    }

    $beltLadder = array_map(fn ($b) => [
        'value' => $b['value'], 'label' => $b['label'], 'bg' => $b['bg'], 'fg' => $b['fg'],
    ], \App\Sports\Combat\BeltRank::ladder());

    $words = [
        'entrants' => __('events.divisions_entrants'),
        'cap' => __('events.divisions_cap'),
        'no_cap' => __('events.divisions_no_cap'),
        'day' => __('events.divisions_day'),
        'male' => __('events.divisions_gender_male'),
        'female' => __('events.divisions_gender_female'),
        'untitled' => __('events.divisions_untitled'),
        'needs_name' => __('events.divisions_needs_name'),
        'heading' => __('events.divisions_heading'),
        'heading_hint' => __('events.divisions_heading_hint'),
        'heading_name' => __('events.divisions_heading_name'),
        'delete_ask' => __('events.divisions_delete_ask'),
        'delete' => __('events.divisions_delete'),
        'failed' => __('events.divisions_failed'),
        'created' => __('events.division_created'),
        'saved' => __('events.division_saved'),
        'deleted' => __('events.division_deleted'),
        // The second ask, when the group is not empty. The server sends the
        // sentence WITH the count in it, so this is only the fallback.
        // The delete question, composed on the CLIENT from the row it is
        // asking about — the counts are already in the list, so one dialog can
        // say exactly what will happen instead of two appearing in a row.
        'ask_people' => __('events.division_delete_ask_people', ['count' => ':count']),
        'ask_draw' => __('events.division_delete_ask_draw', ['bouts' => ':bouts']),
        'ask_both' => __('events.division_delete_ask_both', ['count' => ':count', 'bouts' => ':bouts']),
        'has_results' => __('events.division_has_results'),
        'bouts_one' => trans_choice('events.division_bouts_count', 1, ['count' => 1]),
        'bouts_many' => trans_choice('events.division_bouts_count', 2, ['count' => ':count']),
        'yrs' => __('events.divisions_yrs'),
        'kg' => __('events.divisions_kg'),
        'move_up' => __('events.divisions_move_up'),
        'move_down' => __('events.divisions_move_down'),
        'drag' => __('events.divisions_drag'),
        'order_saved' => __('events.divisions_order_saved'),
        'duplicate' => __('events.divisions_duplicate'),
        'copied' => __('events.division_copied'),
        'belts_any' => __('events.divisions_belts_any'),
        'name_auto' => __('events.divisions_name_auto'),
        // The words a generated name is built from. Here rather than in the
        // script so the name an organiser reads is in their own language.
        'group' => __('events.divisions_group'),
        'belt_word' => __('events.divisions_belt_word'),
        'age_kids' => __('events.divisions_age_kids'),
        'age_youth' => __('events.divisions_age_youth'),
        'age_adult' => __('events.divisions_age_adult'),
        'age_master' => __('events.divisions_age_master'),
        'belt_labels' => collect($beltLadder)->pluck('label', 'value')->all(),
        'presets' => __('events.divisions_add_preset'),
        'preset_title' => __('events.divisions_preset_title'),
        'preset_hint' => __('events.divisions_preset_hint'),
        'preset_add' => __('events.divisions_preset_add'),
        'select_all' => __('events.divisions_select_all'),
        'clear_all' => __('events.divisions_clear_all'),
        'belts_all' => __('events.divisions_belts_all'),
        'men' => __('events.divisions_men'),
        'women' => __('events.divisions_women'),
        'adding' => __('events.divisions_adding'),
        'preset_added' => __('events.divisions_preset_added'),
        'tab_settings' => __('events.divisions_tab_settings'),
        'tab_people' => __('events.divisions_tab_people'),
        'people_after_save' => __('events.divisions_people_after_save'),
        'people_fit' => __('events.divisions_people_fit'),
        'people_all' => __('events.divisions_people_all'),
        'people_selected' => __('events.divisions_people_selected'),
        'people_none_selected' => __('events.divisions_people_none_selected'),
        'people_none' => __('events.divisions_people_none'),
        'people_outside' => __('events.divisions_people_outside'),
        'people_unknown' => __('events.divisions_people_unknown'),
        'people_save' => __('events.divisions_people_save'),
        'people_saved' => __('events.divisions_people_saved'),
        'any_age' => __('events.divisions_any_age'),
        'any_gender' => __('events.divisions_gender_any'),
        'format_ko' => __('events.divisions_format_knockout'),
        'format_rr' => __('events.divisions_format_round_robin'),
        'ruleset_gi' => __('events.divisions_ruleset_gi'),
        'ruleset_nogi' => __('events.divisions_ruleset_nogi'),
        'ruleset_hint' => __('events.divisions_ruleset_hint'),
        'fits_none' => __('events.divisions_fits_none'),
        'fits_one' => trans_choice('events.divisions_fits', 1, ['count' => 1]),
        'fits_many' => trans_choice('events.divisions_fits', 2, ['count' => ':count']),
        'fits_of' => __('events.divisions_fits_of', ['total' => ':total']),
        'fits_here' => __('events.divisions_fits_here', ['count' => ':count']),
        'fits_unknown' => __('events.divisions_fits_unknown', ['count' => ':count']),
        'fits_counting' => __('events.divisions_fits_counting'),
    ];
@endphp

<div x-data="eventDivisions({
        server: {{ Illuminate\Support\Js::from($isServer) }},
        eventKey: {{ Illuminate\Support\Js::from($event) }},
        urls: {{ Illuminate\Support\Js::from($urls) }},
        items: {{ Illuminate\Support\Js::from($seed) }},
        phases: {{ Illuminate\Support\Js::from($phaseList) }},
        days: {{ Illuminate\Support\Js::from($dayCount) }},
        canManage: {{ Illuminate\Support\Js::from((bool) $canManage) }},
        words: {{ Illuminate\Support\Js::from($words) }},
        belts: {{ Illuminate\Support\Js::from($beltLadder) }},
        presets: {{ Illuminate\Support\Js::from($presetTable) }},
        avatars: {{ Illuminate\Support\Js::from($avatars) }},
        @if(! $isServer && $model)
        {{-- Local mode reads the HOST's array rather than copying it: the host
             posts that same array with its form, so a copy here would be a
             second source of truth and the form would submit the stale one.
             The x-data expression is evaluated in the parent Alpine scope, so
             this reference stays live and reactive. --}}
        read: () => {{ $model }},
        @endif
     })"
     class="space-y-2.5">

    {{-- ===== The list =====
         Name, one summary line, a chevron. Everything else is one tap away. --}}
    <template x-for="(d, i) in rows()" :key="rowKey(d, i)">
        {{-- The row, plus the controls that place it.

             ORDER IS THE WHOLE POINT OF A HEADING: it captions the divisions
             BELOW it, until the next heading. Until these controls existed a
             heading could be created but never placed — new rows append, so
             every heading landed underneath the divisions it was written to
             label, captioning nothing. --}}
        <div :data-row-index="i" data-division-row
             @pointerdown="cardDown(i, $event)"
             @touchstart="touchDown(i, $event)"
             :class="dragging === i ? (dragEl ? 'select-none' : 'opacity-40 select-none') : ''"
             class="flex items-stretch gap-1.5 transition-opacity">

            <div class="min-w-0 flex-1">
                {{-- A HEADING: the organiser's own title for the divisions under it.
                     Drawn as a band rather than a card on purpose — it has to read
                     as a label ABOVE things, not as another thing in the list. Still
                     tappable, because renaming and deleting it happen in the same
                     sheet everything else uses. --}}
                <button type="button" x-show="d.is_heading" x-cloak
                        @click="openEdit(d, i)" :disabled="! canManage"
                        class="m-press w-full text-start rounded-xl px-3.5 py-2.5 flex items-center gap-2.5 text-white"
                        style="background: linear-gradient(135deg, {{ $c }}, {{ $c }}b0);">
                    <i class="bi bi-bookmark-fill text-[13px] opacity-90"></i>
                    <span class="flex-1 min-w-0 text-[12px] font-black uppercase tracking-[0.14em] truncate"
                          x-text="d.name || words.untitled"></span>
                    <i class="bi bi-chevron-right text-white/70 text-xs flex-shrink-0 rtl:rotate-180" x-show="canManage"></i>
                </button>

                <button type="button" x-show="! d.is_heading" @click="openEdit(d, i)" :disabled="! canManage"
                        class="m-card m-press w-full text-start bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3">
                    <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 text-white"
                          style="background: {{ $c }}">
                        <i class="bi bi-diagram-3-fill bracket-icon text-lg"></i>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-foreground truncate" x-text="d.name || words.untitled"></span>
                        <span class="block text-[11px] text-muted-foreground mt-0.5 truncate" x-text="summary(d)"></span>

        {{-- Who is IN it. A division's entrant count is a number in the
                             summary line above; these are the people, and a face is
                             read faster than a number — an empty division is visible
                             at a glance across the list rather than by reading twelve
                             summary lines.

                             ⚠️ CIRCLES, and deliberately so — asked for explicitly on
                             2026-09-12, twice. This is the ONE place that departs from
                             "Profile Pictures Are Portrait 3:4" (CLAUDE.md), which
                             exists so the crop a member chose is the crop a reader
                             sees. A circle cannot honour that: it takes a square out
                             of a 3:4 portrait. `object-top` is the mitigation — the
                             square is taken from the TOP of the picture, so what is
                             lost is the bottom of the frame rather than the top of
                             somebody's head.

                             It applies to these stacks only. Anywhere a face is the
                             subject rather than a token — a bout page, a bracket, a
                             profile — the 3:4 rule still stands. Do not read this as
                             licence to round off avatars elsewhere. --}}
                        <span x-show="(d.people || []).length" x-cloak
                              class="flex items-center mt-1.5">
                            <template x-for="(p, k) in (d.people || [])" :key="p.name + k">
                                {{-- ⚠️ The overlap is an INLINE margin, not a
                                     utility class: the Tailwind bundle is
                                     prebuilt, and `-ms-1.5` / `first:ms-0` are
                                     not in it — they would render as nothing
                                     and the stack would sit in a flat row.
                                     `margin-inline-start` also mirrors itself
                                     in Arabic, which a left margin would not. --}}
                                <span class="w-7 h-7 rounded-full overflow-hidden flex-shrink-0 bg-muted ring-2 ring-white"
                                      :style="k ? 'margin-inline-start:-8px' : ''"
                                      :title="p.name">
                                    <img :src="p.photo || avatars[p.gender === 'Female' ? 'Female' : 'Male']"
                                         alt="" class="w-full h-full object-cover object-top">
                                </span>
                            </template>

                            <span x-show="(d.entrants || 0) > (d.people || []).length" x-cloak
                                  class="ms-1.5 text-[10px] font-bold text-muted-foreground"
                                  x-text="'+' + ((d.entrants || 0) - (d.people || []).length)"></span>
                        </span>
                    </span>
                    <i class="bi bi-chevron-right text-muted-foreground/50 text-xs flex-shrink-0 rtl:rotate-180" x-show="canManage"></i>
                </button>
            </div>

            {{-- No placement column: the CARD is the control.

                 It had up/down buttons and a grip, and both are gone at the
                 organiser's request (2026-09-12) — the card drags, and a
                 column of controls beside every row was repeating in three
                 places what one gesture already does.

                 ⚠️ What went with them is the KEYBOARD path: this list can now
                 only be reordered by pointer or finger. Worth knowing before
                 anyone calls that a bug. --}}
        </div>
    </template>

    <p x-show="! rows().length" x-cloak class="text-[11px] text-muted-foreground text-center py-3">
        {{ __('events.divisions_empty') }}
    </p>

    @if($canManage)
        {{-- Add, and whatever else the host offers alongside it, on ONE row.

             The create form puts its bulk weight-class picker here. Stacking the
             two full-width buttons read as a sequence -- do this, then that --
             when they are alternatives: one adds a division by hand, the other
             adds a batch from the sport's own table. Side by side says that.

             With no `actions` slot the Add button is full width exactly as
             before, so every other caller is unchanged. --}}
        <div class="flex items-stretch gap-2">
            <button type="button" @click="openNew()"
                    class="m-press flex-1 py-2.5 rounded-xl border-2 border-dashed border-gray-200 text-sm font-bold text-muted-foreground flex items-center justify-center gap-2 transition-colors hover:bg-muted/60">
                <i class="bi bi-plus-lg"></i> {{ __('events.divisions_add') }}
            </button>
            {{-- A title for the divisions that follow it. Beside Add rather than
                 under it, because the two are alternatives — one adds a thing
                 people compete in, the other adds a label over several. --}}
            <button type="button" @click="openNew(true)"
                    class="m-press py-2.5 px-3.5 rounded-xl border-2 border-dashed border-gray-200 text-sm font-bold text-muted-foreground flex items-center justify-center gap-2 transition-colors hover:bg-muted/60">
                <i class="bi bi-bookmark-plus"></i>
                <span class="hidden sm:inline">{{ __('events.divisions_add_heading') }}</span>
            </button>
            {{-- The federation's own weight classes, in one press.

                 Rendered only when this component was GIVEN a table and the
                 host supplied no actions of its own — the create form passes
                 its own preset button through the slot, and two buttons doing
                 the same job side by side is how a screen stops being read. --}}
            @if (empty(trim($actions ?? '')))
                <button type="button" x-show="presetTable.length" x-cloak @click="openPresets()"
                        class="m-press py-2.5 px-3.5 rounded-xl border-2 border-dashed border-gray-200 text-sm font-bold text-muted-foreground flex items-center justify-center gap-2 transition-colors hover:bg-muted/60">
                    <i class="bi bi-lightning-charge"></i>
                    <span class="hidden sm:inline" x-text="words.presets"></span>
                </button>
            @endif

            {{ $actions ?? '' }}
        </div>
    @endif

    {{-- ===== The sheet =====
         Teleported: the mobile shell leaves a transform on its children, which
         would make `fixed` resolve against a wrapper instead of the viewport
         and clip the form. --}}
    <template x-teleport="body" data-teleport-template="true">
        <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center"
             @keydown.escape.window="close()" style="display:none;">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/40" @click="close()"></div>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="relative w-full sm:max-w-lg max-h-[92vh] flex flex-col bg-white rounded-t-3xl shadow-2xl">

                {{-- ===== The band =====
                     Design Rule #8's sheet header, carrying the DIVISION rather
                     than the word "Edit": its name is the title, and the chips
                     under it are what it is FOR — age, weight, belts, gender,
                     format. They are live, so the band is a running answer to
                     "what am I building" while the fields below are edited, and
                     a reader never has to scroll back up to check.

                     The chips replaced an accordion whose closed rows carried
                     the same summaries. The summaries were worth keeping; the
                     folding was not — an organiser setting six divisions in a
                     row does not want four taps between them and a number.
                     (2026-09-12, at the organiser's request.) --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $c }}, {{ $c }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3 sm:hidden"></div>

                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi text-xl" x-show="! form.is_heading"
                               :class="'bi-diagram-3-fill bracket-icon'"></i>
                            <i class="bi bi-bookmark-fill text-xl" x-show="form.is_heading" x-cloak></i>
                        </span>

                        <div class="min-w-0 flex-1">
                            {{-- The name IS the title. It writes itself from the
                                 settings until the organiser types over it, so
                                 this line moves as the division is built. --}}
                            <h3 class="text-lg font-black leading-tight truncate"
                                x-text="form.name || (form.is_heading
                                    ? words.heading
                                    : (editing === null ? @js(__('events.divisions_new')) : @js(__('events.divisions_edit'))))"></h3>
                            <p class="text-[12px] text-white/85 mt-0.5"
                               x-text="form.is_heading
                                    ? words.heading
                                    : (editing === null ? @js(__('events.divisions_new')) : @js(__('events.divisions_edit')))"></p>
                        </div>

                        <button type="button" @click="close()" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    {{-- What it is for, at a glance. Only the chips that say
                         something: a division with no cap does not need a chip
                         announcing that it has no cap. --}}
                    <div x-show="! form.is_heading" x-cloak class="relative mt-3 flex flex-wrap gap-1.5">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/20 text-[11px] font-bold">
                            <i class="bi bi-person-bounding-box"></i><span x-text="forSummary()"></span>
                        </span>
                        <span x-show="(form.belts || []).length" x-cloak
                              class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/20 text-[11px] font-bold">
                            <i class="bi bi-award"></i><span x-text="beltsSummary()"></span>
                        </span>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/20 text-[11px] font-bold">
                            <i class="bi bi-flag"></i><span x-text="runsSummary()"></span>
                        </span>
                        <span x-show="preview.loaded" x-cloak
                              class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/25 text-[11px] font-bold">
                            <i class="bi bi-people-fill"></i><span x-text="preview.matched"></span>
                        </span>
                    </div>

                    {{-- The sheet's two jobs. A segmented control on the band
                         rather than an underlined strip on the white below it:
                         the choice belongs to the subject in the header, and it
                         keeps the body a single uninterrupted form. --}}
                    <div x-show="! form.is_heading" x-cloak
                         class="relative mt-3.5 p-1 rounded-full bg-black/15 backdrop-blur flex items-center gap-1">
                        <button type="button" @click="tab = 'settings'"
                                class="flex-1 py-1.5 rounded-full text-[12px] font-bold transition-colors"
                                :class="tab === 'settings' ? 'bg-white shadow-sm' : 'text-white/85'"
                                :style="tab === 'settings' ? 'color: {{ $c }}' : ''"
                                x-text="words.tab_settings"></button>

                        <button type="button" @click="openPeopleTab()" :disabled="editing === null || ! server"
                                class="flex-1 py-1.5 rounded-full text-[12px] font-bold transition-colors flex items-center justify-center gap-1.5 disabled:opacity-50"
                                :class="tab === 'people' ? 'bg-white shadow-sm' : 'text-white/85'"
                                :style="tab === 'people' ? 'color: {{ $c }}' : ''">
                            <span x-text="words.tab_people"></span>
                            <span x-show="inCount()" x-cloak class="text-[10px] opacity-80" x-text="inCount()"></span>
                        </button>
                    </div>

                    <p x-show="editing === null && server && ! form.is_heading" x-cloak
                       class="relative mt-1.5 text-[10.5px] text-white/75" x-text="words.people_after_save"></p>
                </div>

                {{-- Body scrolls; the actions below never do. --}}
                <div x-show="tab === 'settings'" class="flex-1 overflow-y-auto px-5 py-4 space-y-5">

                    <div>
                        <label class="block text-[11px] font-semibold text-gray-500 mb-1">{{ __('events.divisions_name') }}</label>
                        <input x-model="form.name" @input="nameTyped()" type="text" maxlength="80"
                               placeholder="{{ __('events.divisions_name_ph') }}"
                               class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none"
                               :class="errors.name ? 'border-red-400' : 'border-gray-200'">
                        <p x-show="errors.name" x-cloak class="mt-1 text-xs text-red-500" x-text="errors.name"></p>

                        <p x-show="autoName && ! form.is_heading && ! errors.name" x-cloak
                           class="mt-1 text-[11px] text-muted-foreground flex items-center gap-1.5">
                            <i class="bi bi-magic"></i><span x-text="words.name_auto"></span>
                        </p>
                    </div>

                    {{-- A HEADING stops here: a name is all it is. The endpoint
                         drops these fields for a heading too (headingSafe), so
                         the form and the contract agree rather than one
                         trusting the other. --}}
                    <div x-show="form.is_heading" x-cloak
                         class="rounded-xl bg-muted/60 border border-gray-100 p-3 flex items-start gap-2.5">
                        <i class="bi bi-bookmark-fill text-primary text-sm mt-0.5"></i>
                        <p class="text-[11px] text-muted-foreground leading-relaxed" x-text="words.heading_hint"></p>
                    </div>

                    {{-- ===== One page, four groups =====
                         Everything visible, in the order a division is thought
                         about: who it is for, which belts, how it runs, which
                         day. The groups are named by a hairline header rather
                         than boxed in cards — the fields are already the
                         structure, and a card around each would be three
                         borders deep inside a sheet that is itself a card.

                         Nothing folds. An organiser building six weight classes
                         wants the whole form under the thumb, not four taps
                         between them and a number. --}}
                    <div x-show="! form.is_heading" x-cloak class="space-y-5">
                        <section>
                            <div class="flex items-center gap-2 mb-2.5">
                                <i class="bi bi-person-bounding-box text-[13px] text-muted-foreground"></i>
                                <h4 class="text-[12.5px] font-bold text-foreground">{{ __('events.divisions_section_for') }}</h4>
                                <span class="h-px flex-1 bg-gray-100"></span>
                            </div>
                            <label class="block text-[11px] font-semibold text-gray-500 mb-1">{{ __('events.divisions_gender') }}</label>
                                                    <div class="grid grid-cols-3 gap-2 mb-3">
                                                        @foreach([['', 'divisions_gender_any', 'bi-people'], ['Male', 'divisions_gender_male', 'bi-gender-male'], ['Female', 'divisions_gender_female', 'bi-gender-female']] as [$g, $k, $icon])
                                                            <button type="button" @click="form.gender = @js($g); settingsChanged()"
                                                                    class="m-press rounded-xl border-2 p-2.5 text-center transition-colors"
                                                                    :class="(form.gender || '') === @js($g) ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-foreground'"
                                                                    :aria-pressed="(form.gender || '') === @js($g)">
                                                                <i class="bi {{ $icon }} text-base"></i>
                                                                <span class="block text-[11px] font-bold mt-0.5">{{ __('events.'.$k) }}</span>
                                                            </button>
                                                        @endforeach
                                                    </div>

                            {{-- Age --}}
                                                    <label class="block text-[11px] font-semibold text-gray-500 mb-1">{{ __('events.divisions_age') }}</label>
                                                    <div class="grid grid-cols-2 gap-2 mb-1">
                                                        <input x-model="form.min_age" @input="settingsChanged()" type="number" min="2" max="100" inputmode="numeric"
                                                               placeholder="{{ __('events.divisions_from') }}"
                                                               class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                                                        <input x-model="form.max_age" @input="settingsChanged()" type="number" min="2" max="100" inputmode="numeric"
                                                               placeholder="{{ __('events.divisions_to') }}"
                                                               class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                                                    </div>
                                                    <p x-show="errors.max_age" x-cloak class="mb-2 text-xs text-red-500" x-text="errors.max_age"></p>

                                                    {{-- Weight --}}
                                                    <label class="block text-[11px] font-semibold text-gray-500 mb-1 mt-2">{{ __('events.divisions_weight') }}</label>
                                                    <div class="grid grid-cols-2 gap-2 mb-1">
                                                        <input x-model="form.min_weight" @input="settingsChanged()" type="number" min="10" max="300" step="0.1" inputmode="decimal"
                                                               placeholder="{{ __('events.divisions_from') }}"
                                                               class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                                                        <input x-model="form.max_weight" @input="settingsChanged()" type="number" min="10" max="300" step="0.1" inputmode="decimal"
                                                               placeholder="{{ __('events.divisions_to') }}"
                                                               class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                                                    </div>
                                                    <p x-show="errors.max_weight" x-cloak class="text-xs text-red-500" x-text="errors.max_weight"></p>
                        </section>
                        <section>
                            <div class="flex items-center gap-2 mb-2.5">
                                <i class="bi bi-award text-[13px] text-muted-foreground"></i>
                                <h4 class="text-[12.5px] font-bold text-foreground">{{ __('events.divisions_section_belts') }}</h4>
                                <span class="h-px flex-1 bg-gray-100"></span>
                            </div>
                            {{-- ===== Belts =====
                                                         Chips rather than a dropdown: a division is
                                                         regularly opened to two or three grades at once
                                                         ("Blue, Purple"), and a multi-select pop-over
                                                         inside this scrolling body would be clipped by it
                                                         (Mobile Pattern Language → selection cards).

                                                         None selected means ANY belt, which is both the
                                                         default and the honest reading of an empty column —
                                                         said in words underneath so it is never mistaken
                                                         for "nothing chosen yet". --}}
                                                    <label class="block text-[11px] font-semibold text-gray-500 mb-1 mt-3">{{ __('events.divisions_belts') }}</label>
                                                    {{-- FIVE TO A ROW, equal cells. Content-sized chips put
                                                         "Red" and "Light Feather" side by side at wildly
                                                         different widths, which reads as importance rather
                                                         than as ten equal options — and the row rewrapped
                                                         every time a label changed language. Ten chips (All
                                                         + nine belts) land as two tidy rows of five. --}}
                                                    <div class="grid grid-cols-5 gap-1.5">
                                                        {{-- "All" is not a tenth belt: it is the ABSENCE of
                                                             a restriction, which is what an empty list
                                                             already means everywhere else (DivisionRange).
                                                             So it lights up when nothing is chosen, and
                                                             pressing it clears the rest. --}}
                                                        <button type="button" @click="form.belts = []; settingsChanged()"
                                                                class="m-press px-1 py-1.5 rounded-full text-[11px] font-bold border transition-all truncate"
                                                                :class="! (form.belts || []).length ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-muted-foreground bg-white'"
                                                                x-text="words.belts_all"></button>

                                                        <template x-for="b in beltLadder" :key="b.value">
                                                            <button type="button" @click="toggleBelt(b.value)" :title="b.label"
                                                                    class="m-press px-1 py-1.5 rounded-full text-[11px] font-bold border transition-all flex items-center justify-center gap-1 min-w-0"
                                                                    :class="hasBelt(b.value)
                                                                        ? 'border-transparent shadow-sm'
                                                                        : 'border-gray-200 text-muted-foreground bg-white'"
                                                                    :style="hasBelt(b.value) ? ('background:' + b.bg + ';color:' + b.fg) : ''">
                                                                <span class="w-2.5 h-2.5 rounded-full border border-black/15 flex-shrink-0"
                                                                      :style="'background:' + b.bg"></span>
                                                                <span class="truncate" x-text="b.label"></span>
                                                            </button>
                                                        </template>
                                                    </div>
                                                    <p x-show="! (form.belts || []).length" x-cloak
                                                       class="mt-1 text-[11px] text-muted-foreground" x-text="words.belts_any"></p>
                        </section>
                        <section>
                            <div class="flex items-center gap-2 mb-2.5">
                                <i class="bi bi-flag text-[13px] text-muted-foreground"></i>
                                <h4 class="text-[12.5px] font-bold text-foreground">{{ __('events.divisions_section_runs') }}</h4>
                                <span class="h-px flex-1 bg-gray-100"></span>
                            </div>
                            <label class="block text-[11px] font-semibold text-gray-500 mb-1">{{ __('events.divisions_format') }}</label>
                                                    <div class="space-y-2">
                                                        <button type="button" @click="form.format = 'knockout'"
                                                                class="m-press w-full text-start rounded-2xl border-2 p-3.5 transition-colors"
                                                                :class="form.format === 'knockout' ? 'border-primary bg-primary/5' : 'border-gray-200 bg-white'"
                                                                :aria-pressed="form.format === 'knockout'">
                                                            <div class="flex items-start gap-3">
                                                                <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0"
                                                                      :class="form.format === 'knockout' ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground'">
                                                                    <i class="bi bi-diagram-3-fill bracket-icon text-lg"></i>
                                                                </span>
                                                                <div class="min-w-0 flex-1">
                                                                    <p class="text-sm font-black text-foreground">{{ __('events.divisions_format_knockout') }}</p>
                                                                    <p class="text-[11.5px] text-muted-foreground leading-snug mt-0.5">{{ __('events.divisions_format_knockout_hint') }}</p>
                                                                </div>
                                                                <span class="w-5 h-5 rounded-full border-2 grid place-items-center flex-shrink-0 mt-0.5 transition-colors"
                                                                      :class="form.format === 'knockout' ? 'border-transparent text-white' : 'border-gray-300'"
                                                                      :style="form.format === 'knockout' ? 'background: {{ $c }}' : ''">
                                                                    <i class="bi bi-check-lg text-[11px]" x-show="form.format === 'knockout'" x-cloak></i>
                                                                </span>
                                                            </div>
                                                        </button>

                                                        <button type="button" @click="form.format = 'round_robin'"
                                                                class="m-press w-full text-start rounded-2xl border-2 p-3.5 transition-colors"
                                                                :class="form.format === 'round_robin' ? 'border-primary bg-primary/5' : 'border-gray-200 bg-white'"
                                                                :aria-pressed="form.format === 'round_robin'">
                                                            <div class="flex items-start gap-3">
                                                                <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0"
                                                                      :class="form.format === 'round_robin' ? 'bg-primary/10 text-primary' : 'bg-muted text-muted-foreground'">
                                                                    <i class="bi bi-arrow-repeat text-lg"></i>
                                                                </span>
                                                                <div class="min-w-0 flex-1">
                                                                    <p class="text-sm font-black text-foreground">{{ __('events.divisions_format_round_robin') }}</p>
                                                                    <p class="text-[11.5px] text-muted-foreground leading-snug mt-0.5">{{ __('events.divisions_format_round_robin_hint') }}</p>
                                                                </div>
                                                                <span class="w-5 h-5 rounded-full border-2 grid place-items-center flex-shrink-0 mt-0.5 transition-colors"
                                                                      :class="form.format === 'round_robin' ? 'border-transparent text-white' : 'border-gray-300'"
                                                                      :style="form.format === 'round_robin' ? 'background: {{ $c }}' : ''">
                                                                    <i class="bi bi-check-lg text-[11px]" x-show="form.format === 'round_robin'" x-cloak></i>
                                                                </span>
                                                            </div>
                                                        </button>
                                                    </div>

                            <label class="block text-[11px] font-semibold text-gray-500 mb-1">{{ __('events.divisions_capacity') }}</label>
                                                    <input x-model="form.capacity" type="number" min="2" inputmode="numeric"
                                                           placeholder="{{ __('events.divisions_no_cap') }}"
                                                           class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none"
                                                           :class="errors.capacity ? 'border-red-400' : 'border-gray-200'">
                                                    <p class="mt-1 text-[10px] text-muted-foreground">{{ __('events.divisions_capacity_hint') }}</p>
                                                    <p x-show="errors.capacity" x-cloak class="mt-1 text-xs text-red-500" x-text="errors.capacity"></p>
                        </section>
                        <section x-show="days > 1 && phases.length" x-cloak>
                            <div class="flex items-center gap-2 mb-2.5">
                                <i class="bi bi-calendar3 text-[13px] text-muted-foreground"></i>
                                <h4 class="text-[12.5px] font-bold text-foreground">{{ __('events.divisions_section_days') }}</h4>
                                <span class="h-px flex-1 bg-gray-100"></span>
                            </div>
                            {{-- ===== Which day each phase lands on =====
                                                     Only asked when the event runs over more than one day —
                                                     on a one-day event every answer is "day 1" and the
                                                     question is noise. Day pills rather than a dropdown: an
                                                     absolutely-positioned panel inside this scrolling body
                                                     would be clipped by it. --}}
                                                <div x-show="days > 1 && phases.length" x-cloak>
                                                    <label class="block text-[11px] font-semibold text-gray-500 mb-1">{{ __('events.divisions_days') }}</label>
                                                    <div class="space-y-2">
                                                        <template x-for="ph in phases" :key="ph.key">
                                                            <div class="rounded-xl border border-gray-100 p-2.5">
                                                                <p class="text-[11px] font-semibold text-foreground mb-1.5" x-text="ph.label"></p>
                                                                <div class="flex flex-wrap gap-1.5">
                                                                    <template x-for="day in dayList()" :key="day">
                                                                        {{-- ⚠️ `form.schedule` is read through a guard, and written through
                                                                             one. This row lives inside the sheet's markup, which Alpine
                                                                             evaluates whether or not the sheet is OPEN — and `form` starts
                                                                             as `{}`, so the bare `form.schedule[ph.key]` threw
                                                                             "Cannot read properties of undefined" once per phase on every
                                                                             load of the page (caught in the browser 2026-09-06). Harmless
                                                                             to look at, but a console full of errors is where a real one
                                                                             goes unnoticed. --}}
                                                                        <button type="button" @click="(form.schedule = form.schedule || {})[ph.key] = day"
                                                                                class="m-press px-2.5 py-1 rounded-full text-[11px] font-bold border transition-colors"
                                                                                :class="Number((form.schedule || {})[ph.key] || 1) === day ? 'bg-primary text-white border-primary' : 'bg-white text-foreground border-gray-200'"
                                                                                x-text="words.day.replace(':n', day)"></button>
                                                                    </template>
                                                                </div>
                                                            </div>
                                                        </template>
                                                    </div>
                                                </div>

                                                {{-- ===== Format =====
                                                     Round robin is DRAWN, and drawn as unavailable rather
                                                     than left out: an organiser who is looking for it needs
                                                     to learn that it is coming, not conclude the platform
                                                     has never heard of it.

                                                     BOTH are live since 2026-09-07. A knockout is
                                                     DrawEngine's ladder; a group is GroupEngine's
                                                     all-play-all feeding a knockout final. The server still
                                                     validates `format` against
                                                     App\Models\EventCategory::RUNNABLE_FORMATS, which now
                                                     holds them both — a format an organiser can choose is
                                                     always one the mats can honour. --}}
                        </section>

                        {{-- ===== Who is actually in it =====
                                                     The question an organiser is really asking while
                                                     typing a range: is there ANYBODY in this? Until now
                                                     it could only be answered by saving the division,
                                                     opening the entrant picker and counting — so a
                                                     weight class that caught nobody was discovered
                                                     after the fact, when it is either the wrong numbers
                                                     or a division the event should not run.

                                                     Judged by the same rule the roster is judged by
                                                     (DivisionRange, server side), so this and the
                                                     picker cannot disagree.

                                                     Faces are CIRCLES here, as asked for on 2026-09-12
                                                     — see the note on the list's own stack for why that
                                                     departs from the 3:4 rule and how far the exception
                                                     reaches. They overlap into a row, which is what a
                                                     stack of faces is for. --}}
                                                <div x-show="server && (preview.loading || preview.loaded)" x-cloak
                                                     class="mt-3 rounded-2xl border border-gray-100 bg-muted/40 p-3">

                                                    <div class="flex items-center gap-3">
                                                        <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 text-white"
                                                              style="background: {{ $c }}">
                                                            <i class="bi bi-people-fill text-lg"></i>
                                                        </span>

                                                        <div class="min-w-0 flex-1">
                                                            <p class="text-sm font-black text-foreground leading-tight"
                                                               x-text="preview.loading ? words.fits_counting : fitsLine()"></p>
                                                            <p class="text-[11px] text-muted-foreground mt-0.5"
                                                               x-show="! preview.loading" x-text="fitsSubLine()"></p>
                                                        </div>
                                                    </div>

                                                    {{-- The faces themselves. Overlapping, in the order
                                                         the server sorted them, with the remainder as a
                                                         number rather than a scroll. --}}
                                                    <div x-show="preview.people.length" x-cloak class="flex items-center mt-2.5 ps-0.5">
                                                        <template x-for="(p, k) in preview.people" :key="p.name + k">
                                                            <span class="w-8 h-8 rounded-full overflow-hidden flex-shrink-0 bg-muted ring-2 ring-white"
                                                                  :style="k ? 'margin-inline-start:-9px' : ''"
                                                                  :title="p.name"
                                                                  :class="p.fit === 'unknown' ? 'opacity-60' : ''">
                                                                <img :src="p.photo || avatars[p.gender === 'Female' ? 'Female' : 'Male']"
                                                                     alt="" class="w-full h-full object-cover object-top">
                                                            </span>
                                                        </template>

                                                        <span x-show="preview.matched > preview.people.length" x-cloak
                                                              class="ms-2 text-[11px] font-bold text-muted-foreground"
                                                              x-text="'+' + (preview.matched - preview.people.length)"></span>
                                                    </div>
                                                </div>

                        {{-- Copy this one. Offered only on a division that exists,
                                                 for the same reason Delete is: there is nothing to copy
                                                 until it has been saved once. It copies what the
                                                 division is FOR and never what happened in it — see
                                                 duplicateDivision(). --}}
                                            <button type="button" x-show="editing !== null && server" x-cloak
                                                    @click="duplicate()" :disabled="saving"
                                                    class="m-press w-full py-2.5 mb-2 rounded-xl border border-gray-200 text-foreground font-semibold text-sm flex items-center justify-center gap-2 transition-colors hover:bg-muted/60 disabled:opacity-60">
                                                <i class="bi bi-copy"></i> <span x-text="words.duplicate"></span>
                                            </button>

                                            <button type="button" x-show="canDelete()" x-cloak @click="remove()" :disabled="saving"
                                                    class="m-press w-full py-2.5 rounded-xl border border-red-300 text-red-600 font-semibold text-sm flex items-center justify-center gap-2 transition-colors hover:bg-red-50 disabled:opacity-60">
                                                <i class="bi bi-trash"></i> {{ __('events.divisions_delete') }}
                                            </button>
                    </div>
                </div>

                {{-- ===== Who is in it =====
                     Two lists of the same people: the ones this division is FOR,
                     and everybody entered in the event. Filtered first, because
                     it is the answer nine times in ten — and All second rather
                     than not at all, because a range is a guide and never a gate
                     (DivisionRange): an organiser moving a strong junior up, or
                     filling a bracket of three, must be able to reach anybody.

                     The tick is the only control, and it is reversible until
                     Save. Nothing here confirms or warns: entering somebody in
                     two divisions is the ordinary thing this exists for. --}}
                <div x-show="tab === 'people'" x-cloak class="flex-1 overflow-y-auto px-5 py-4">

                    {{-- Three ways into the same roster: who the division is
                         FOR, everybody entered, and who is in it right now.

                         Selected is the one an organiser checks last — it
                         answers "is this division right" without reading past
                         sixty names, and it counts UNSAVED ticks too, so it is
                         what the division will be once Save is pressed rather
                         than what it was when the sheet opened. --}}
                    <div class="grid grid-cols-3 gap-2 mb-3">
                        <button type="button" @click="peopleTab = 'fit'"
                                class="m-press rounded-xl border-2 py-2 px-1 text-center transition-colors"
                                :class="peopleTab === 'fit' ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-foreground'">
                            <span class="flex items-center justify-center gap-1.5 min-w-0">
                                <span class="text-[12px] font-bold truncate" x-text="words.people_fit"></span>
                                {{-- The count in the sheet's own accent, bold: it is
                                     the thing being compared across the three, so it
                                     must not read as a footnote to the label. --}}
                                <span class="text-[12px] font-black flex-shrink-0"
                                      style="color: {{ $c }}" x-text="fitList().length"></span>
                            </span>
                        </button>
                        <button type="button" @click="peopleTab = 'all'"
                                class="m-press rounded-xl border-2 py-2 px-1 text-center transition-colors"
                                :class="peopleTab === 'all' ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-foreground'">
                            <span class="flex items-center justify-center gap-1.5 min-w-0">
                                <span class="text-[12px] font-bold truncate" x-text="words.people_all"></span>
                                {{-- The count in the sheet's own accent, bold: it is
                                     the thing being compared across the three, so it
                                     must not read as a footnote to the label. --}}
                                <span class="text-[12px] font-black flex-shrink-0"
                                      style="color: {{ $c }}" x-text="roster.length"></span>
                            </span>
                        </button>
                        <button type="button" @click="peopleTab = 'selected'"
                                class="m-press rounded-xl border-2 py-2 px-1 text-center transition-colors"
                                :class="peopleTab === 'selected' ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-foreground'">
                            <span class="flex items-center justify-center gap-1.5 min-w-0">
                                <span class="text-[12px] font-bold truncate" x-text="words.people_selected"></span>
                                {{-- The count in the sheet's own accent, bold: it is
                                     the thing being compared across the three, so it
                                     must not read as a footnote to the label. --}}
                                <span class="text-[12px] font-black flex-shrink-0"
                                      style="color: {{ $c }}" x-text="inCount()"></span>
                            </span>
                        </button>
                    </div>

                    <p x-show="rosterLoading" x-cloak class="py-8 text-center text-[12px] text-muted-foreground"
                       x-text="words.fits_counting"></p>

                    <p x-show="! rosterLoading && ! visiblePeople().length" x-cloak
                       class="py-8 text-center text-[12px] text-muted-foreground"
                       x-text="peopleTab === 'selected' ? words.people_none_selected : words.people_none"></p>

                    <div class="space-y-2" x-show="! rosterLoading">
                        <template x-for="p in visiblePeople()" :key="p.person_id">
                            <div role="button" tabindex="0" @click="togglePerson(p)"
                                 @keydown.enter.prevent="togglePerson(p)" @keydown.space.prevent="togglePerson(p)"
                                 :aria-pressed="isIn(p) ? 'true' : 'false'"
                                 :class="isIn(p) ? 'border-primary bg-primary/5' : 'border-gray-100 bg-white hover:bg-muted/40'"
                                 class="w-full rounded-2xl border p-2.5 flex items-center gap-3 text-start transition-colors shadow-sm cursor-pointer">

                                {{-- The circular tick, on the leading edge: it is
                                     the control, and the rest of the row is what
                                     it is about. --}}
                                <span class="w-6 h-6 rounded-full border-2 grid place-items-center flex-shrink-0 transition-colors"
                                      :class="isIn(p) ? 'border-primary bg-primary text-white' : 'border-gray-300'">
                                    <i class="bi bi-check-lg text-[12px]" x-show="isIn(p)"></i>
                                </span>

                                <span class="w-9 h-9 rounded-full overflow-hidden flex-shrink-0 bg-muted">
                                    <img :src="p.photo || avatars[p.gender === 'Female' ? 'Female' : 'Male']"
                                         alt="" class="w-full h-full object-cover object-top">
                                </span>

                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-bold text-foreground truncate" x-text="p.name"></span>
                                    <span class="flex items-center gap-1.5 flex-wrap mt-1">
                                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-muted text-muted-foreground"
                                              x-text="(p.age ?? '—') + ' ' + words.yrs"></span>
                                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-muted text-muted-foreground"
                                              x-text="(p.weight ?? '—') + ' ' + words.kg"></span>

                                        {{-- Why they are not in the filtered list.
                                             Said on the row rather than by leaving
                                             them out silently — an organiser
                                             reaching into All is doing it on
                                             purpose and should see what they are
                                             overriding. --}}
                                        <span x-show="p.fit === 'out'" x-cloak
                                              class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-50 text-amber-600"
                                              x-text="words.people_outside"></span>
                                        <span x-show="p.fit === 'unknown'" x-cloak
                                              class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-muted text-muted-foreground"
                                              x-text="words.people_unknown"></span>
                                    </span>
                                </span>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Sticky footer, safe-area padded, so Save is always reachable. --}}
                <div x-show="tab === 'people'" x-cloak class="flex-shrink-0 px-5 pt-3 border-t border-gray-100"
                     style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="savePeople()" :disabled="saving || ! changedCount()"
                            class="m-press w-full py-3 rounded-xl text-white font-bold text-sm flex items-center justify-center gap-2 active:scale-[.98] transition disabled:opacity-60"
                            style="background: {{ $c }}">
                        <i class="bi bi-check2"></i>
                        <span x-text="saving ? words.adding : (words.people_save + (changedCount() ? ' (' + changedCount() + ')' : ''))"></span>
                    </button>
                </div>

                <div x-show="tab === 'settings'" class="flex-shrink-0 px-5 pt-3 border-t border-gray-100"
                     style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="save()" :disabled="saving"
                            class="m-press w-full py-3 rounded-xl text-white font-bold text-sm flex items-center justify-center gap-2 active:scale-[.98] transition disabled:opacity-60"
                            style="background: {{ $c }}">
                        <i class="bi bi-check2"></i>
                        <span x-text="saving ? @js(__('events.divisions_saving')) : @js(__('events.divisions_save'))"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- ===== The preset sheet =====
         The federation's weight classes, picked in one place. Its own sheet
         rather than a tab inside the editor: the editor edits ONE division and
         this creates several, which is a different job (Mobile Pattern
         Language → one screen, one job).

         Teleported and shaped like every other sheet on the platform — gradient
         band, scrolling body, safe-area footer (Design Rule #8). --}}
    <template x-teleport="body" data-teleport-template="true">
        <div x-show="presetsOpen" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center"
             @keydown.escape.window="presetsOpen = false" style="display:none;">
            <div x-show="presetsOpen" x-transition.opacity class="absolute inset-0 bg-black/40" @click="presetsOpen = false"></div>

            <div x-show="presetsOpen"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="relative w-full sm:max-w-lg max-h-[92vh] flex flex-col bg-white rounded-t-3xl shadow-2xl">

                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $c }}, {{ $c }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3 sm:hidden"></div>

                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-lightning-charge-fill text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight" x-text="words.preset_title"></h3>
                            <p class="text-[12px] text-white/85 mt-0.5" x-text="words.preset_hint"></p>
                        </div>
                        <button type="button" @click="presetsOpen = false" :aria-label="words.close || 'Close'"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">

                    {{-- Gi or no-gi. FIRST, because it decides which table the
                         rest of this sheet is reading — the same athlete is a
                         different weight class with and without a gi. Shown
                         only where the sport publishes both. --}}
                    <div x-show="rulesets().length > 1" x-cloak>
                        <div class="grid grid-cols-2 gap-2">
                            <template x-for="r in rulesets()" :key="r">
                                <button type="button" @click="presetRuleset = r; presetPick = {}; syncBand()"
                                        class="m-press rounded-xl border-2 p-2.5 text-center transition-colors"
                                        :class="presetRuleset === r ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-foreground'">
                                    <span class="block text-[12px] font-bold"
                                          x-text="r === 'nogi' ? words.ruleset_nogi : words.ruleset_gi"></span>
                                </button>
                            </template>
                        </div>
                        <p class="mt-1 text-[11px] text-muted-foreground" x-text="words.ruleset_hint"></p>
                    </div>

                    {{-- Age band. Selection cards, because the list is short and
                         known — and an absolute panel inside this scrolling body
                         would be clipped by it. --}}
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-500 mb-1">{{ __('events.divisions_age') }}</label>

                        {{-- A GRID, not a wrapping row: the bands are one choice
                             of four, and content-sized buttons made "Kids" a
                             third the width of "Juvenile" — which reads as a
                             ranking rather than as four equal options. Grid
                             items stretch, so they also come out the same
                             HEIGHT whether or not a band has an age line.

                             Four columns because a combat sport's ladder is
                             four bands; a fifth simply wraps onto a second row
                             at the same width rather than squeezing the rest. --}}
                        <div class="grid grid-cols-4 gap-2">
                            <template x-for="b in bandsFor()" :key="b.band">
                                <button type="button" @click="presetBand = b.band; presetPick = {}"
                                        class="m-press px-2 py-2 rounded-xl border-2 text-[12px] font-bold leading-tight text-center transition-colors"
                                        :class="presetBand === b.band ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-foreground'">
                                    <span class="block truncate" x-text="b.band"></span>
                                    <span class="block text-[10px] font-medium opacity-70 mt-0.5" x-text="bandAges(b)"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    {{-- Gender. The table has one list per gender and the weights
                         differ, so this is a fork in the data, not a filter. --}}
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-500 mb-1">{{ __('events.divisions_gender') }}</label>
                        <div class="grid grid-cols-2 gap-2">
                            <template x-for="g in ['male', 'female']" :key="g">
                                <button type="button" x-show="genderInBand(g)" @click="presetGender = g; presetPick = {}"
                                        class="m-press rounded-xl border-2 p-2.5 text-center transition-colors"
                                        :class="presetGender === g ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-foreground'">
                                    <i class="bi" :class="g === 'female' ? 'bi-gender-female' : 'bi-gender-male'"></i>
                                    <span class="block text-[11px] font-bold mt-0.5" x-text="g === 'female' ? words.women : words.men"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    {{-- The classes themselves, with select-all. --}}
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block text-[11px] font-semibold text-gray-500">{{ __('events.divisions_weight') }}</label>
                            <button type="button" @click="togglePresetAll()"
                                    class="text-[11px] font-bold text-primary"
                                    x-text="allPresetPicked() ? words.clear_all : words.select_all"></button>
                        </div>

                        <div class="space-y-1.5">
                            <template x-for="c in presetClasses()" :key="c.label">
                                <button type="button" @click="togglePreset(c.label)"
                                        class="m-press w-full rounded-xl border-2 p-2.5 flex items-center gap-2.5 transition-colors text-start"
                                        :class="presetPick[c.label] ? 'border-primary bg-primary/5' : 'border-gray-200'">
                                    <span class="w-5 h-5 rounded-full border-2 grid place-items-center flex-shrink-0"
                                          :class="presetPick[c.label] ? 'border-primary bg-primary text-white' : 'border-gray-300'">
                                        <i class="bi bi-check2 text-[11px]" x-show="presetPick[c.label]"></i>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-bold text-foreground" x-text="c.label + ' ' + (words.kg || 'kg')"></span>
                                        <span class="block text-[11px] text-muted-foreground" x-show="c.name" x-text="c.name"></span>
                                    </span>
                                </button>
                            </template>
                        </div>

                        <p x-show="! presetClasses().length" x-cloak
                           class="text-[11px] text-muted-foreground text-center py-3">{{ __('events.divisions_empty') }}</p>
                    </div>

                    {{-- Belts for every division this makes. Same chips as the
                         editor, because it is the same question. --}}
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-500 mb-1">{{ __('events.divisions_belts') }}</label>
                        <div class="grid grid-cols-5 gap-1.5">
                            <button type="button" @click="presetBelts = []"
                                    class="m-press px-1 py-1.5 rounded-full text-[11px] font-bold border transition-all truncate"
                                    :class="! presetBelts.length ? 'border-primary bg-primary/5 text-primary' : 'border-gray-200 text-muted-foreground bg-white'"
                                    x-text="words.belts_all"></button>

                            <template x-for="b in beltLadder" :key="b.value">
                                <button type="button" @click="togglePresetBelt(b.value)" :title="b.label"
                                        class="m-press px-1 py-1.5 rounded-full text-[11px] font-bold border transition-all flex items-center justify-center gap-1 min-w-0"
                                        :class="presetBelts.indexOf(b.value) >= 0 ? 'border-transparent shadow-sm' : 'border-gray-200 text-muted-foreground bg-white'"
                                        :style="presetBelts.indexOf(b.value) >= 0 ? ('background:' + b.bg + ';color:' + b.fg) : ''">
                                    <span class="w-2.5 h-2.5 rounded-full border border-black/15 flex-shrink-0" :style="'background:' + b.bg"></span>
                                    <span class="truncate" x-text="b.label"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="flex-shrink-0 px-5 pt-3 border-t border-gray-100"
                     style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="addPresets()" :disabled="saving || ! pickedCount()"
                            class="m-press w-full py-3 rounded-xl text-white font-bold text-sm flex items-center justify-center gap-2 active:scale-[.98] transition disabled:opacity-60"
                            style="background: {{ $c }}">
                        <i class="bi bi-plus-lg"></i>
                        <span x-text="saving ? words.adding : (words.preset_add + (pickedCount() ? ' (' + pickedCount() + ')' : ''))"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>

@once
@push('scripts')
<script>
/**
 * The x-event-divisions editor’s behaviour.
 *
 * Registered rather than declared inline: the sheet above lives inside an
 * x-teleport <template>, and an inline script inside one of those is inert, so an
 * inline x-data would leave the whole sheet bound to an empty scope (see
 * CLAUDE.md → "Alpine state must be REGISTERED, not inline"). Registered on
 * BOTH paths because the shell may swap this markup in after Alpine has
 * already started, by which point `alpine:init` has been and gone.
 */
(function () {
    const register = (Alpine) => Alpine.data('eventDivisions', (cfg) => ({
        server: !! cfg.server,
        eventKey: cfg.eventKey || null,
        urls: cfg.urls || null,
        phases: cfg.phases || [],
        days: Math.max(1, parseInt(cfg.days, 10) || 1),
        canManage: cfg.canManage !== false,
        words: cfg.words || {},
        beltLadder: Array.isArray(cfg.belts) ? cfg.belts : [],
        presetTable: Array.isArray(cfg.presets) ? cfg.presets : [],
        avatars: cfg.avatars || {},

        /* Local mode hands us a live reference to the HOST's array. Server mode
           owns its own list, seeded from the payloads the page rendered with. */
        read: typeof cfg.read === 'function' ? cfg.read : null,
        items: Array.isArray(cfg.items) ? cfg.items : [],

        open: false,
        saving: false,
        autoName: false,     // is the name still following the settings?
        presetsOpen: false,  // the federation-table picker
        presetBand: '',
        presetGender: 'male',
        presetRuleset: 'gi',   // which table the sheet is reading
        preview: { loading: false, loaded: false, matched: 0, unknown: 0, here: 0, total: 0, people: [] },
        previewTimer: null,
        previewSeq: 0,
        tab: 'settings',       // settings | people
        peopleTab: 'fit',      // fit | all
        roster: [],            // the candidate list, as the server judged it
        rosterLoading: false,
        picked: {},            // person_id => wanted, for ticks not yet saved
        presetPick: {},      // {label: true} — which weight classes are ticked
        presetBelts: [],     // belts every division it makes will carry
        editingIndex: null,  // where the open sheet's row sits, for its letter
        editing: null,      // server: the division id · local: its index · null: a new one
        form: {},
        errors: {},

        /* ── Reading ─────────────────────────────────────────────────────── */

        rows() {
            if (this.read) {
                const v = this.read();
                return Array.isArray(v) ? v : [];
            }
            return this.items;
        },

        rowKey(d, i) {
            return this.server ? ('d' + (d && d.id ? d.id : 'n' + i)) : ('i' + i);
        },

        /* ── Placing a row ───────────────────────────────────────────────────
           A heading captions the divisions BELOW it until the next heading, so
           the list's ORDER is the only thing that gives a heading meaning.
           These are what place it.

           ── What moves ──
           EVERY row moves alone, heading or division, and lands wherever it is
           let go — a heading between two cards included.

           ⚠️ This replaced a block rule (2026-09-12, at the organiser's
           request). A heading used to travel with every division under it, so
           that dragging a caption could never change which section somebody
           else's division was in. The cost of that safety was that a heading
           could not be placed at all where an event has only one section: its
           block was the whole list, and the card simply would not move.

           So the safety is gone, deliberately, and this is what it means: a
           heading captions the rows BELOW it until the next heading, so moving
           one re-captions everything it passes. Dropping "No-Gi" one card
           higher moves that card into the Gi section. That is now the
           organiser's to see and to mean — it is visible while dragging,
           because the list redraws under the finger.

           A division's own entrants, bouts and draw are untouched by any of
           this — the endpoint writes `sort_order` and nothing else. */

        dragging: null,      // index currently under the finger, or null
        orderTimer: null,
        press: null,         // a finger down on a card, not yet a drag
        dragEl: null,        // the lifted row's element, while one is held
        listEl: null,        // the element the rows are children of — see startDrag
        orderAtGrab: null,   // the order when the card was picked up
        dragY: 0,            // how far it has been carried from its slot
        grabDy: 0,           // where inside the row the finger took hold
        lastY: 0,            // the last pointer position, for re-tracking after a reorder

        /* ── Dragging ────────────────────────────────────────────────────────
           Pointer events, no library (CLAUDE.md → don't add a dependency for a
           trivial feature; the bracket and family-tree runtimes hand-roll the
           same thing).

           Three parts, because "drag the card and the others make room" is
           three separate things to the eye:

           · The card LIFTS and follows the finger. It stays in the list's flow
             and is moved by a transform, so nothing reflows around it and the
             slot it vacates is exactly the gap it will drop into.
           · The others MOVE ASIDE to open that gap, animated with FLIP —
             measure, reorder, invert, release. The reorder is the same array
             splice the up/down buttons use; the animation is the only thing
             added, because a row that teleports into its new place reads as a
             glitch rather than as space being made.
           · The card TRACKS BY DELTA, not by absolute position. That is what
             lets it survive the list changing underneath it: the frame after a
             reorder moves the element into a new slot, the offset is corrected
             and the finger never slips off the card.

           The lift needs the DOM node to SURVIVE a reorder, which it only does
           where rows are keyed by id — server mode. The create form keys by
           index, so Alpine swaps the CONTENT of fixed nodes instead of moving
           them; measuring a node that did not move is meaningless there, so
           that list keeps the plain instant reorder it has always had. */

        /** Can the held row be lifted — i.e. will its DOM node survive a reorder? */
        canLift() { return !! this.server; },

        /** How long the others take to move aside. Zero where motion is refused. */
        flipMs() {
            try {
                if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return 0;
            } catch (e) { /* no matchMedia — assume motion is fine */ }
            return 180;
        },

        /** Take hold of row `i`, with the finger at `clientY`. */
        startDrag(i, row, clientY) {
            if (! this.canManage) return;

            this.dragging = i;
            this.lastY = clientY;

            // What the order was when the card came up, so a drag that changed
            // nothing — carried and put back, or a heading with nowhere to go —
            // does not post a write saying so.
            this.orderAtGrab = this.rows().map((d) => d && d.id).join(',');

            /* ⚠️ The list is found through the HELD ROW, never through `$el`.

               `$el` is the CURRENT element, and these gestures are bound on the
               row — so inside them `$el` is that row, and asking it for
               `[data-division-row]` returns NOTHING (a row contains no rows).
               Every comparison then ran against an empty list, no row was ever
               found to swap with, and the cards would not change places at all
               while the card itself dragged perfectly. That was this bug.

               The rows are siblings, so their parent is the list — no magic,
               and true in both the page and the create form. */
            this.listEl = row ? row.parentElement : null;

            if (this.canLift() && row) {
                this.dragEl = row;
                this.dragY = 0;
                this.grabDy = clientY - row.getBoundingClientRect().top;

                row.style.willChange = 'transform';
                row.style.transition = 'none';
                row.style.position = 'relative';
                row.style.zIndex = '30';
                row.style.transform = 'scale(1.02)';
                row.style.filter = 'drop-shadow(0 12px 24px rgba(15, 23, 42, .18))';
            }

        },

        /** Put the held card back where the finger is, however the list moved. */
        track(clientY) {
            if (! this.dragEl || clientY == null) return;

            this.lastY = clientY;
            const want = clientY - this.grabDy;
            this.dragY += (want - this.dragEl.getBoundingClientRect().top);
            this.dragEl.style.transform = 'translateY(' + this.dragY + 'px) scale(1.02)';
        },

        /** Reorder, and let every row that moved slide from where it was. */
        /** Every row of the list, found without `$el`. See startDrag. */
        rowEls() {
            const scope = this.listEl || this.$el;
            return scope ? Array.from(scope.querySelectorAll('[data-division-row]')) : [];
        },

        flip(mutate) {
            const before = new Map();
            this.rowEls().forEach((r) => {
                before.set(r, r.getBoundingClientRect().top);
            });

            mutate();

            if (! this.dragEl) return;        // no lift: the plain reorder is the whole of it

            this.$nextTick(() => {
                const ms = this.flipMs();

                this.rowEls().forEach((r) => {
                    if (r === this.dragEl) return;

                    const was = before.get(r);
                    if (was == null) return;

                    const dy = was - r.getBoundingClientRect().top;
                    if (! dy) return;

                    // Invert to where it used to be, then release it.
                    r.style.transition = 'none';
                    r.style.transform = 'translateY(' + dy + 'px)';
                    requestAnimationFrame(() => {
                        r.style.transition = 'transform ' + ms + 'ms cubic-bezier(.2, .8, .2, 1)';
                        r.style.transform = '';
                    });
                });

                // The held card changed slot with everything else; correct it in
                // the same frame, before the eye can see it jump.
                this.track(this.lastY);
            });
        },

        /** Scroll the page when the card is carried to the top or bottom edge. */
        edgeScroll(y) {
            const pad = 84;
            const above = y - pad;
            const below = window.innerHeight - pad - y;

            if (above < 0) window.scrollBy(0, Math.max(-20, above / 4));
            else if (below < 0) window.scrollBy(0, Math.min(20, -below / 4));
        },

        dragOver(ev) {
            if (this.dragging === null) return;

            this.track(ev.clientY);
            this.edgeScroll(ev.clientY);

            const rows = this.rowEls();

            /* Measured from the CARD, not the finger: the card is the thing
               being pushed into the others, and after a long press the two can
               sit a row apart. With no lift there is no card to measure, and
               the finger is the honest answer. */
            const held = this.dragEl ? this.dragEl.getBoundingClientRect() : null;
            const y = held ? (held.top + held.height / 2) : ev.clientY;

            /* A row yields when the card passes its MIDPOINT — not on first
               contact, which makes a list flicker between two orders while a
               finger hovers on a border. */
            let over = -1;
            for (let k = 0; k < rows.length; k++) {
                if (k === this.dragging) continue;                             // itself

                const b = rows[k].getBoundingClientRect();
                const mid = b.top + b.height / 2;

                if (k < this.dragging) { if (y < mid && over < 0) over = k; }
                else if (y > mid) { over = k; }
            }

            if (over < 0 || over === this.dragging) return;

            const list = this.rows();

            this.flip(() => {
                // One row, to exactly where it was let go. `over` is already an
                // index in the list as it stands, and removing the held row
                // first leaves every index at or below it unchanged.
                const moved = list.splice(this.dragging, 1);
                list.splice(over, 0, ...moved);
                this.dragging = over;
            });
        },

        /** Set the card down: it eases into the slot the list opened for it. */
        releaseDrag() {
            const el = this.dragEl;

            this.dragEl = null;
            this.listEl = null;
            this.dragging = null;

            if (el) {
                const ms = this.flipMs();
                el.style.transition = 'transform ' + ms + 'ms cubic-bezier(.2, .8, .2, 1), filter ' + ms + 'ms ease';
                el.style.transform = '';
                el.style.filter = '';

                // Only once it has landed — clearing the lift mid-flight drops it.
                setTimeout(() => {
                    ['transition', 'transform', 'filter', 'zIndex', 'position', 'willChange']
                        .forEach((k) => { el.style[k] = ''; });
                }, ms + 40);
            }

            // Only when the list actually ended up different.
            const now = this.rows().map((d) => d && d.id).join(',');
            if (now !== this.orderAtGrab) {
                this.queueOrder();
                // Letters are counted from the heading above, so moving a card
                // moves them. Auto-named rows only — see reletter().
                this.reletter();
            }
            this.orderAtGrab = null;
        },

        /* ── Taking hold of the CARD ─────────────────────────────────────
           ⚠️ MOUSE AND TOUCH ARE READ FROM DIFFERENT EVENT STREAMS, and that
           separation IS the fix for the bug this shipped with: a card picked
           up with a finger was dropped straight back where it started.

           Why it did that. `touch-action` has to be left alone on these rows —
           the page is a list of cards and it must still scroll — so the
           browser decides for itself that a moving finger is a scroll, and
           when it does, it CANCELS the pointer stream. The drag ended on
           `pointercancel` a frame after it began, and the card eased back into
           the slot it had never really left. Blocking `touchmove` afterwards
           cannot undo a gesture the browser has already claimed.

           So the finger is driven by TOUCH events, whose move is preventable
           and is never taken away mid-gesture, and pointer events are left to
           the mouse. One set of machinery underneath; only the stream differs.
           (Same split, for the same reason, as the bracket runtime.)

           The two also cannot share a rule for STARTING:

           · A MOUSE takes hold on the first few pixels of MOVEMENT, not on the
             press — otherwise every tap that wobbled becomes a reorder, and a
             press on the card is also how the editor opens.
           · A FINGER takes hold on a LONG PRESS (280ms), because the same
             gesture scrolls the page: move before it fires and it was a
             scroll; hold still and the card comes up under the finger.

           Either way a press that became a drag must not also open the editor,
           so the click that follows is swallowed once. */
        cardDown(i, ev) {
            // Touch has its own door; a browser that sends both must not start twice.
            if (ev.pointerType === 'touch') return;
            if (! this.canManage || this.dragging !== null || this.press) return;
            if (ev.button != null && ev.button > 0) return;          // right / middle

            const row = ev.currentTarget;
            const p = this.press = { i, row, x: ev.clientX, y: ev.clientY, touch: false, started: false, timer: null };

            const move = (e) => {
                if (! this.press) return;
                if (this.press.started) { this.dragOver(e); return; }

                if (Math.abs(e.clientX - p.x) > 6 || Math.abs(e.clientY - p.y) > 6) {
                    this.beginCardDrag(e.clientY);
                }
            };
            const up = () => this.endPress();

            p.move = move; p.up = up;
            window.addEventListener('pointermove', move);
            window.addEventListener('pointerup', up);
            window.addEventListener('pointercancel', up);
        },

        touchDown(i, ev) {
            if (! this.canManage || this.dragging !== null || this.press) return;

            const t = ev.changedTouches && ev.changedTouches[0];
            if (! t) return;

            const row = ev.currentTarget;
            const p = this.press = { i, row, x: t.clientX, y: t.clientY, touch: true,
                                     started: false, timer: null, id: t.identifier, native: true };

            /* The SAME finger, not whichever one the event happens to carry:
               a second thumb landing mid-drag must not take the card over. */
            const mine = (e) => Array.prototype.find.call(e.changedTouches || [],
                                                          (c) => c.identifier === p.id);

            const move = (e) => {
                if (! this.press) return;

                const c = mine(e);
                if (! c) return;

                if (this.press.started) {
                    // Held down from the moment the card came up, so the page
                    // cannot start scrolling out from under it.
                    e.preventDefault();
                    this.dragOver({ clientX: c.clientX, clientY: c.clientY });
                    return;
                }

                // Moved before the hold fired: that was a scroll, and it stays one.
                if (Math.abs(c.clientX - p.x) > 8 || Math.abs(c.clientY - p.y) > 8) this.endPress();
            };
            const up = (e) => { if (! e || mine(e)) this.endPress(); };

            p.move = move; p.up = up;
            document.addEventListener('touchmove', move, { passive: false });
            document.addEventListener('touchend', up);
            document.addEventListener('touchcancel', up);

            p.timer = setTimeout(() => this.beginCardDrag(p.y), 280);
        },

        beginCardDrag(clientY) {
            const p = this.press;
            if (! p || p.started) return;

            p.started = true;
            clearTimeout(p.timer);

            // Say it happened, on the one input kind that has no cursor to change.
            if (p.touch) { try { navigator.vibrate && navigator.vibrate(12); } catch (e) { /* not fatal */ } }

            this.startDrag(p.i, p.row, clientY == null ? p.y : clientY);
        },

        /** The pointer came up, or the gesture was taken away. */
        endPress() {
            const p = this.press;
            if (! p) return;

            clearTimeout(p.timer);

            if (p.native) {
                document.removeEventListener('touchmove', p.move, { passive: false });
                document.removeEventListener('touchend', p.up);
                document.removeEventListener('touchcancel', p.up);
            } else {
                window.removeEventListener('pointermove', p.move);
                window.removeEventListener('pointerup', p.up);
                window.removeEventListener('pointercancel', p.up);
            }

            this.press = null;

            if (! p.started) return;        // an ordinary tap: let it open the editor

            this.releaseDrag();

            /* Swallow the click this press is about to produce, so a card that
               was dragged does not also open. Removed on a timer as well as on
               use: a touch drag often produces no click at all, and a listener
               left armed would eat the NEXT genuine tap. */
            if (p.row) {
                const eat = (e) => { e.stopPropagation(); e.preventDefault(); };
                p.row.addEventListener('click', eat, { capture: true, once: true });
                setTimeout(() => p.row.removeEventListener('click', eat, { capture: true }), 350);
            }
        },

        /** Coalesce a run of taps into one write. */
        queueOrder() {
            if (! this.server) return;      // local mode: the host form posts the array itself

            clearTimeout(this.orderTimer);
            this.orderTimer = setTimeout(() => this.saveOrder(), 400);
        },

        async saveOrder() {
            const order = this.rows().map((d) => d && d.id).filter(Boolean);
            if (! order.length) return;

            const data = await this.request('PUT', this.urls.order, { order });
            if (! data) return;             // the toast has already said why

            this.announce('reordered', null);
        },

        /** A division's fields, flat, whether they arrive nested in `range` or not. */
        flat(d) {
            d = d || {};
            const r = d.range || d;
            const val = (v) => (v === null || v === undefined ? '' : v);
            return {
                name: d.name || '',
                // A title in the list rather than a division. Sticky across an
                // edit: what a row IS cannot change by opening it.
                is_heading: !! d.is_heading,
                capacity: val(d.capacity),
                format: d.format || 'knockout',
                gender: val(r.gender),
                min_age: val(r.min_age),
                max_age: val(r.max_age),
                min_weight: val(r.min_weight),
                max_weight: val(r.max_weight),
                belts: Array.isArray(r.belts) ? r.belts.slice() : [],
                schedule: Object.assign(this.blankSchedule(), d.schedule || {}),
            };
        },

        blankSchedule() {
            const s = {};
            this.phases.forEach((p) => { s[p.key] = 1; });
            return s;
        },

        dayList() {
            return Array.from({ length: this.days }, (_, i) => i + 1);
        },

        /** The one line under the name: who it is for, how many, which day. */
        summary(d) {
            const w = this.words;
            const r = (d && d.range) || d || {};
            const bits = [];

            if (r.gender === 'Male') bits.push(w.male);
            else if (r.gender === 'Female') bits.push(w.female);

            if (r.min_age || r.max_age) {
                bits.push(this.span(r.min_age, r.max_age) + ' ' + w.yrs);
            }
            if (r.min_weight || r.max_weight) {
                bits.push(this.span(r.min_weight, r.max_weight) + ' ' + w.kg);
            }

            if (this.server && typeof d.entrants === 'number') {
                bits.push(d.entrants + ' ' + w.entrants);
            }

            bits.push(d && d.capacity
                ? String(w.cap).replace(':n', d.capacity)
                : w.no_cap);

            const day = this.firstDay(d);
            if (this.days > 1 && day) bits.push(String(w.day).replace(':n', day));

            return bits.join(' · ');
        },

        span(min, max) {
            if (min && max) return min + '–' + max;
            if (min) return '≥ ' + min;
            return '≤ ' + max;
        },

        firstDay(d) {
            const s = (d && d.schedule) || {};
            const v = Object.values(s).map((n) => parseInt(n, 10)).filter((n) => n > 0);
            return v.length ? Math.min.apply(null, v) : null;
        },

        /* ── The sheet ───────────────────────────────────────────────────── */

        openNew(heading = false) {
            if (! this.canManage) return;
            this.editing = null;
            this.errors = {};
            this.form = this.flat({ is_heading: heading });
            this.editingIndex = this.rows().length;
            // A blank name is by definition still waiting to be written.
            this.autoName = ! heading;
            this.preview = { loading: false, loaded: false, matched: 0, unknown: 0, here: 0, total: 0, people: [] };
            this.tab = 'settings';
            this.peopleTab = 'fit';
            this.roster = [];
            this.picked = {};
            this.open = true;
            this.queuePreview();
        },

        openEdit(d, i) {
            if (! this.canManage) return;
            this.editing = this.server ? (d.id ?? null) : i;
            this.errors = {};
            this.form = this.flat(d);
            this.editingIndex = i;

            /* Is this name still the settings talking, or did somebody write
               it? Answered by COMPARING rather than by a stored flag: a name
               that is exactly what the settings would produce is, for every
               purpose anyone can observe, a generated one — so it goes on
               following them. A name of their own differs, and is left alone
               from the moment the sheet opens. Nothing to migrate, and nothing
               that can fall out of step with the text it describes. */
            this.autoName = ! d.is_heading
                && (! String(d.name || '').trim() || this.looksGenerated(d, i));

            this.preview = { loading: false, loaded: false, matched: 0, unknown: 0, here: 0, total: 0, people: [] };
            this.tab = 'settings';
            this.peopleTab = 'fit';
            this.roster = [];
            this.picked = {};
            this.open = true;
            this.queuePreview();
        },

        close() {
            if (this.saving) return;
            this.open = false;
        },

        canDelete() {
            return this.canManage && this.editing !== null;
        },

        /* ── Writing ─────────────────────────────────────────────────────── */

        /** What the server is sent, and what a local row is made of. */
        /* ── The name a division gives itself ────────────────────────────
           Everything an organiser used to type is already in the sheet: the
           section it sits under says gi or no-gi, the age and weight boxes say
           what it is for, the belt chips say which grades. Typing all of that
           again as prose is how a list ends up with "Group C (+70) Adult [Gi]"
           beside "‏المجموعة ج (70+)" — the same division, spelled two ways,
           and nothing able to read either. (The event page has a regex that
           tries to parse these names back into structure. That is the same
           problem seen from the other end.)

           So the name FOLLOWS the settings while it is still the settings
           talking, and stops the moment somebody types their own — see
           openEdit. It is only ever a string in the `name` column: no screen
           has to know it was generated, and the organiser can always overrule
           it with one keystroke.

           ⚠️ The GROUP LETTER is positional — first division under its heading
           is A, the next B. So it changes when cards are dragged, which is why
           the list re-letters auto-named rows after a reorder (see reletter).
           A hand-typed name never gets a letter imposed on it. */
        generatedName(index) {
            const body = this.nameBody(index);
            const letter = this.groupLetter(index);
            if (! letter) return body;

            const prefix = (this.words.group || 'Group') + ' ' + letter;
            return body ? (prefix + ' · ' + body) : prefix;
        },

        /** The generated name WITHOUT its positional letter. */
        nameBody(index) {
            const f = this.form || {};
            const parts = [];
            const w = this.words || {};

            // Belts, in the ladder's own order rather than the order they were
            // tapped: "Blue, Purple" and "Purple, Blue" are one division.
            const belts = (f.belts || []);
            if (belts.length) {
                const inOrder = this.beltLadder.filter((b) => belts.indexOf(b.value) >= 0);
                const names = inOrder.map((b) => (w.belt_labels || {})[b.value] || b.label);
                if (names.length) parts.push(names.join(', ') + ' ' + (w.belt_word || 'Belt'));
            }

            if (f.gender) parts.push(f.gender === 'Female' ? (w.female || 'Female') : (w.male || 'Male'));

            const age = this.ageBand(f.min_age, f.max_age);
            if (age) parts.push(age);

            const weight = this.weightLabel(f.min_weight, f.max_weight);
            if (weight) parts.push(weight);

            const section = this.sectionOf(index);
            if (section) parts.push(section);

            return parts.join(' · ');
        },

        /* Is this row's name one WE wrote, or one somebody typed?

           Compared with the positional letter taken off both sides, because a
           row that has been dragged carries the letter it had in its old place
           — and that is precisely the row we are here to re-letter. Without
           this it would look hand-typed the moment it moved, which is to say
           it would never be corrected at all. */
        looksGenerated(d, index) {
            const name = String((d && d.name) || '').trim();
            if (! name) return false;

            const held = this.form;
            this.form = this.flat(d);
            const body = this.nameBody(index);
            this.form = held;

            if (! body) return false;

            const word = (this.words.group || 'Group').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            const stripped = name.replace(new RegExp('^' + word + '\\s+[A-Z]\\s+·\\s+'), '');

            return name === body || stripped === body;
        },

        /** A, B, C… among the divisions under the same heading. */
        groupLetter(index) {
            if (index === null || index === undefined || index < 0) return '';

            const list = this.rows();
            let n = 0;

            for (let k = 0; k < index && k < list.length; k++) {
                if (list[k] && list[k].is_heading) n = 0;   // a new section restarts the count
                else n++;
            }

            // Past Z the letter stops being a label and starts being a puzzle.
            return n < 26 ? String.fromCharCode(65 + n) : '';
        },

        /** The heading above this row — "Gi", "No-Gi", or whatever was typed. */
        sectionOf(index) {
            const list = this.rows();

            for (let k = Math.min(index, list.length) - 1; k >= 0; k--) {
                if (list[k] && list[k].is_heading) return String(list[k].name || '').trim();
            }
            return '';
        },

        /* The words a federation actually uses, from the age box.

           Deliberately a SUGGESTION and not a vocabulary: the bands below are
           the common ones, and anything that does not match them is said as
           plain numbers rather than forced into the nearest word. An organiser
           running 14-15s does not want them called Youth because that is the
           closest label this file happens to know. */
        ageBand(min, max) {
            const lo = min === '' || min == null ? null : parseInt(min, 10);
            const hi = max === '' || max == null ? null : parseInt(max, 10);
            const w = this.words || {};

            if (lo === null && hi === null) return '';

            // Four words, not five: every extra band is another judgement
            // this file makes on an organiser's behalf, and anything that does
            // not sit cleanly in one is said as plain numbers instead.
            if (lo === null && hi !== null) {
                if (hi <= 11) return w.age_kids || 'Kids';
                if (hi <= 17) return w.age_youth || 'Youth';
                return '≤' + hi + ' ' + (w.yrs || 'yrs');
            }
            if (hi === null && lo !== null) {
                if (lo >= 30) return w.age_master || 'Masters';
                if (lo >= 18) return w.age_adult || 'Adult';
                return lo + '+ ' + (w.yrs || 'yrs');
            }

            if (hi <= 11) return w.age_kids || 'Kids';
            if (hi <= 17) return w.age_youth || 'Youth';
            if (lo >= 30) return w.age_master || 'Masters';
            if (lo >= 18) return w.age_adult || 'Adult';

            return lo + '–' + hi + ' ' + (w.yrs || 'yrs');
        },

        /** −60 kg · +80 kg · 60–70 kg, the way a weigh-in sheet writes it. */
        weightLabel(min, max) {
            const lo = min === '' || min == null ? null : Number(min);
            const hi = max === '' || max == null ? null : Number(max);
            const kg = (this.words || {}).kg || 'kg';
            const n = (v) => String(Math.round(v * 10) / 10);

            if (lo == null && hi == null) return '';
            if (lo == null) return '−' + n(hi) + ' ' + kg;
            if (hi == null) return '+' + n(lo) + ' ' + kg;
            return n(lo) + '–' + n(hi) + ' ' + kg;
        },

        /** Rewrite the name from the settings, while it is still following them. */
        refreshName() {
            if (! this.autoName || this.form.is_heading) return;
            this.form.name = this.generatedName(this.editingIndex);
        },

        /** The organiser typed: the name is theirs from here. */
        nameTyped() {
            this.autoName = false;
        },

        /* ── The federation's own weight classes ─────────────────────────
           Nine weights per band per gender, and an organiser was typing all of
           them — which is both the slowest part of setting an event up and
           where the numbers drift from the table registration actually places
           people by. The table is the sport's (config/bjj_divisions.php, via
           the event type's formCatalog), so this makes divisions that agree
           with classify() by construction.

           Every division it creates is a NORMAL one: a range, a gender, belts,
           a generated name. Nothing about it is special afterwards, and the
           organiser can edit or delete any of them like any other. */
        openPresets() {
            if (! this.canManage || ! this.presetTable.length) return;

            this.presetRuleset = this.rulesets()[0] || 'gi';
            this.presetPick = {};
            this.presetBelts = [];
            this.syncBand();
            this.presetsOpen = true;
        },

        /** Which rulesets this sport published a table for, in order. */
        rulesets() {
            const seen = [];
            this.presetTable.forEach((b) => { if (seen.indexOf(b.ruleset) < 0) seen.push(b.ruleset); });
            return seen;
        },

        /** The bands belonging to the ruleset being read. */
        bandsFor() {
            return this.presetTable.filter((b) => b.ruleset === this.presetRuleset);
        },

        /* A band from the other ruleset cannot survive the switch — the two
           tables need not name the same bands — so the band follows the table
           rather than the other way round. */
        syncBand() {
            const bands = this.bandsFor();
            if (! bands.some((b) => b.band === this.presetBand)) {
                this.presetBand = bands.length ? bands[0].band : '';
            }
            if (! this.genderInBand(this.presetGender)) {
                this.presetGender = this.genderInBand('male') ? 'male' : 'female';
            }
        },

        /** The band being read, as the table holds it. */
        presetBandRow() {
            return this.bandsFor().find((b) => b.band === this.presetBand) || null;
        },

        /** "18–29", "30+", or nothing where the table says nothing. */
        bandAges(b) {
            if (! b || b.min_age == null) return '';
            return b.max_age == null ? (b.min_age + '+') : (b.min_age + '–' + b.max_age);
        },

        genderInBand(g) {
            const row = this.presetBandRow();
            return !! (row && row.genders && row.genders[g] && row.genders[g].length);
        },

        presetClasses() {
            const row = this.presetBandRow();
            return (row && row.genders && row.genders[this.presetGender]) || [];
        },

        togglePreset(label) {
            const picked = Object.assign({}, this.presetPick);
            if (picked[label]) delete picked[label];
            else picked[label] = true;
            this.presetPick = picked;
        },

        pickedCount() {
            return Object.keys(this.presetPick || {}).length;
        },

        allPresetPicked() {
            const classes = this.presetClasses();
            return classes.length > 0 && classes.every((c) => this.presetPick[c.label]);
        },

        togglePresetAll() {
            if (this.allPresetPicked()) { this.presetPick = {}; return; }

            const picked = {};
            this.presetClasses().forEach((c) => { picked[c.label] = true; });
            this.presetPick = picked;
        },

        togglePresetBelt(value) {
            const list = this.presetBelts.slice();
            const at = list.indexOf(value);
            if (at >= 0) list.splice(at, 1);
            else list.push(value);
            this.presetBelts = list;
        },

        /**
         * Make one division per checked weight class.
         *
         * Saved one at a time rather than in a batch: there is no batch
         * endpoint, and a failure half way through then leaves the ones that
         * DID land in the list rather than losing the lot. The list patches in
         * place as each arrives, so the organiser watches them appear.
         */
        async addPresets() {
            if (this.saving || ! this.canManage) return;

            const row = this.presetBandRow();
            const classes = this.presetClasses().filter((c) => this.presetPick[c.label]);
            if (! row || ! classes.length) return;

            const held = this.form;
            this.saving = true;
            let made = 0;

            for (const c of classes) {
                const body = {
                    is_heading: false,
                    capacity: null,
                    format: 'knockout',
                    gender: this.presetGender === 'female' ? 'Female' : 'Male',
                    min_age: row.min_age == null ? null : row.min_age,
                    max_age: row.max_age == null ? null : row.max_age,
                    min_weight: c.min == null ? null : c.min,
                    max_weight: c.max == null ? null : c.max,
                    belts: this.presetBelts.slice(),
                    schedule: this.blankSchedule(),
                };

                /* Named the one way everything else is named: by its own
                   settings, at the position it is about to occupy. So a preset
                   division and a hand-made one read identically. */
                this.form = this.flat(body);
                body.name = this.generatedName(this.rows().length) || (c.label + ' ' + (this.words.kg || 'kg'));

                if (! this.server) {
                    this.rows().push(body);
                    made++;
                    continue;
                }

                const data = await this.request('POST', this.urls.store, body);
                if (! data || ! data.division) break;      // the toast has said why

                this.upsert(data.division);
                this.announce('created', data.division);
                made++;
            }

            this.form = held;
            this.saving = false;
            this.presetPick = {};
            this.presetsOpen = false;

            if (made) {
                window.showToast('success', (this.words.preset_added || '').replace(':count', made) || this.words.created);
            }
        },

        /* ── Who the division would hold ─────────────────────────────────
           Asked of the server, because the rule that decides it lives there
           (DivisionRange) and a second copy in JavaScript would be a second
           answer. Debounced: the range is typed a digit at a time, and every
           keystroke would otherwise walk the whole entry list.

           Never in local mode — a form that has not created the event yet has
           nobody entered to count — and never for a heading, which holds
           nobody by definition. */
        queuePreview() {
            if (! this.server || ! this.urls || ! this.urls.preview) return;
            if (this.form.is_heading) { this.preview.loaded = false; return; }

            clearTimeout(this.previewTimer);
            this.preview.loading = true;
            this.previewTimer = setTimeout(() => this.runPreview(), 400);
        },

        async runPreview() {
            const num = (v) => (v === '' || v === null || v === undefined ? null : Number(v));
            const seq = ++this.previewSeq;       // only the latest answer wins

            const data = await this.request('POST', this.urls.preview, {
                gender: this.form.gender || null,
                min_age: num(this.form.min_age),
                max_age: num(this.form.max_age),
                min_weight: num(this.form.min_weight),
                max_weight: num(this.form.max_weight),
                belts: (this.form.belts || []).slice(),
                division: this.server ? this.editing : null,
            });

            if (seq !== this.previewSeq) return;  // a newer request has overtaken this one

            this.preview.loading = false;

            if (! data) { this.preview.loaded = false; return; }

            this.preview.matched = data.matched || 0;
            this.preview.unknown = data.unknown || 0;
            this.preview.here = data.here || 0;
            this.preview.total = data.total || 0;
            this.preview.people = Array.isArray(data.people) ? data.people : [];
            this.preview.loaded = true;
        },

        /** "13 people fit this" — or that nobody does, which is the useful case. */
        fitsLine() {
            const n = this.preview.matched;
            if (! n) return this.words.fits_none;

            return n === 1 ? this.words.fits_one
                           : (this.words.fits_many || '').replace(':count', n);
        },

        /* The caveats under it, and only the ones that apply: how many of the
           event's entrants that is, how many are already in this division, and
           how many matched only because something about them is UNKNOWN —
           which is the number that decides whether the count can be trusted. */
        fitsSubLine() {
            const bits = [];

            if (this.preview.total) {
                bits.push((this.words.fits_of || '').replace(':total', this.preview.total));
            }
            if (this.preview.here) {
                bits.push((this.words.fits_here || '').replace(':count', this.preview.here));
            }
            if (this.preview.unknown) {
                bits.push((this.words.fits_unknown || '').replace(':count', this.preview.unknown));
            }

            return bits.join(' · ');
        },

        /** A setting changed: the name follows it, and so does the count. */
        settingsChanged() {
            this.refreshName();
            this.queuePreview();
        },

        /* ── The people tab ──────────────────────────────────────────────
           The same two endpoints the console's picker uses — the candidate
           list, and one write carrying what changed — so a division filled
           from here and one filled from there cannot end up meaning different
           things. The overlay model is theirs too: `picked` holds only what
           the organiser has CHANGED, so a tick that returns to where it
           started is not a change and is not saved. */
        async openPeopleTab() {
            if (this.editing === null || ! this.server || this.form.is_heading) return;

            this.tab = 'people';

            if (this.roster.length || this.rosterLoading) return;     // already read

            this.rosterLoading = true;
            const data = await this.request('GET', this.urls.base + '/' + this.editing + '/candidates', null);
            this.rosterLoading = false;

            if (! data) { this.tab = 'settings'; return; }            // the toast has said why

            this.roster = data.people || [];
            this.picked = {};
        },

        /** Everybody this division is FOR — the range as a guide, never a gate. */
        fitList() {
            return this.roster.filter((p) => p.fit !== 'out');
        },

        /** Who is in it right now — saved ticks and unsaved ones alike. */
        selectedList() {
            return this.roster.filter((p) => this.isIn(p));
        },

        visiblePeople() {
            if (this.peopleTab === 'all') return this.roster;
            if (this.peopleTab === 'selected') return this.selectedList();

            return this.fitList();
        },

        /** In this division, counting what has been ticked but not yet saved. */
        isIn(p) {
            return this.picked[p.person_id] ?? !! p.here;
        },

        togglePerson(p) {
            const now = this.isIn(p);

            // Back to where it started is not a change.
            if (now === !! p.here) this.picked[p.person_id] = ! now;
            else delete this.picked[p.person_id];
        },

        /** How many are in it now, ticks included — the number on the tab. */
        inCount() {
            return this.roster.filter((p) => this.isIn(p)).length;
        },

        /** How many ticks are actually unsaved, which is what Save acts on. */
        changedCount() {
            return Object.keys(this.picked).length;
        },

        async savePeople() {
            if (this.saving || ! this.changedCount()) return;

            const add = [];
            const remove = [];

            for (const [pid, wanted] of Object.entries(this.picked)) {
                const p = this.roster.find((x) => String(x.person_id) === String(pid));
                if (! p) continue;

                if (wanted) { if (p.enter_id) add.push(p.enter_id); }
                else if (p.entry_here_id) remove.push(p.entry_here_id);
            }

            if (! add.length && ! remove.length) { this.picked = {}; return; }

            this.saving = true;
            const data = await this.request('PUT', this.urls.base + '/' + this.editing + '/members', { add, remove });
            this.saving = false;

            if (! data) return;

            // The endpoint answers with the refreshed roster, so the sheet
            // patches in place rather than closing (No Page Reload Rule).
            this.roster = data.people || [];
            this.picked = {};

            /* The list behind the sheet has to move too: the card shows the
               count and the faces, and both just changed. The division payload
               comes back with the answer, so nothing is re-read. */
            if (data.division) this.upsert(data.division);

            window.showToast('success', this.words.people_saved);
            this.announce('updated', data.division || null);

            // The preview counts "already in it", which is now a different number.
            this.queuePreview();
        },

        /* ── The four facts, said in one line each ───────────────────────
           What a closed section row reads. Not a field list — the ANSWER, in
           the same words the division is named by, so a row says "Youth · −60
           kg · Male" rather than "Gender, age, weight".

           "Any", "All belts", "No cap": an unset bound is a real answer here
           and is said out loud, because blank means ANY everywhere in this
           editor (DivisionRange) and a row that simply went quiet would read
           as unfinished. */
        forSummary() {
            const f = this.form || {};
            const bits = [];

            const age = this.ageBand(f.min_age, f.max_age);
            bits.push(age || this.words.any_age);

            const weight = this.weightLabel(f.min_weight, f.max_weight);
            if (weight) bits.push(weight);

            bits.push(f.gender
                ? (f.gender === 'Female' ? this.words.female : this.words.male)
                : this.words.any_gender);

            return bits.join(' · ');
        },

        beltsSummary() {
            const chosen = this.form.belts || [];
            if (! chosen.length) return this.words.belts_any;

            // Ladder order, never tap order: "Blue, Purple" is one division
            // however the organiser reached it.
            return this.beltLadder
                .filter((b) => chosen.indexOf(b.value) >= 0)
                .map((b) => (this.words.belt_labels || {})[b.value] || b.label)
                .join(', ');
        },

        runsSummary() {
            const bits = [];
            bits.push(this.form.format === 'round_robin' ? this.words.format_rr : this.words.format_ko);

            const cap = this.form.capacity;
            bits.push(cap ? (this.words.cap || '').replace(':n', cap) : this.words.no_cap);

            return bits.join(' · ');
        },

        /** Which day each phase runs, collapsed: "Day 1" when they all agree. */
        daysSummary() {
            const sched = this.form.schedule || {};
            const days = this.phases.map((ph) => Number(sched[ph.key] || 1));

            if (! days.length) return '';

            const same = days.every((d) => d === days[0]);
            if (same) return (this.words.day || '').replace(':n', days[0]);

            return (this.words.day || '').replace(':n', Math.min(...days))
                + ' – ' + (this.words.day || '').replace(':n', Math.max(...days));
        },

        hasBelt(value) {
            return (this.form.belts || []).indexOf(value) >= 0;
        },

        toggleBelt(value) {
            const list = (this.form.belts || []).slice();
            const at = list.indexOf(value);

            if (at >= 0) list.splice(at, 1);
            else list.push(value);

            this.form.belts = list;
            this.settingsChanged();
        },

        payload() {
            const num = (v) => (v === '' || v === null || v === undefined ? null : Number(v));
            const int = (v) => (v === '' || v === null || v === undefined ? null : parseInt(v, 10));
            // A heading carries a name and nothing else — the same shape the
            // endpoint enforces (PersonalEventController::headingSafe), so the
            // two cannot disagree about what a heading is.
            if (this.form.is_heading) {
                return { name: String(this.form.name || '').trim(), is_heading: true };
            }

            return {
                name: String(this.form.name || '').trim(),
                is_heading: false,
                capacity: int(this.form.capacity),
                // Always knockout: the only format RUNNABLE_FORMATS allows and
                // the only one DrawEngine can cut. See the sheet's comment.
                format: 'knockout',
                gender: this.form.gender || null,
                min_age: int(this.form.min_age),
                max_age: int(this.form.max_age),
                min_weight: num(this.form.min_weight),
                max_weight: num(this.form.max_weight),
                belts: (this.form.belts || []).slice(),
                schedule: Object.assign({}, this.form.schedule || {}),
            };
        },

        async save() {
            if (this.saving || ! this.canManage) return;

            const body = this.payload();
            this.errors = {};

            if (! body.name) {
                this.errors.name = this.words.needs_name;
                return;
            }

            if (! this.server) {
                const list = this.rows();
                if (this.editing === null) {
                    list.push(body);
                    this.announce('created', body);
                } else {
                    Object.assign(list[this.editing], body);
                    this.announce('updated', body);
                }
                this.open = false;
                return;
            }

            const creating = this.editing === null;
            const url = creating ? this.urls.store : (this.urls.base + '/' + this.editing);

            this.saving = true;
            const data = await this.request(creating ? 'POST' : 'PATCH', url, body);
            this.saving = false;

            if (! data) return;

            this.upsert(data.division);
            window.showToast('success', data.message || (creating ? this.words.created : this.words.saved));
            this.announce(creating ? 'created' : 'updated', data.division);
            this.open = false;
        },

        /**
         * The question a delete asks.
         *
         * Composed here, from the row on screen, because the list already knows
         * how many are in the group and how many bouts it has — so ONE dialog
         * can say what will actually happen. The server asks the same question
         * again (409) if a caller ever skips this, but a browser that asked
         * twice put two dialogs on screen back to back and the second one
         * flashed and vanished, which is how this ended up being fixed.
         */
        deleteAsk(d) {
            const people = Number((d && d.entrants) || 0);
            const bouts = Number((d && d.matches) || 0);

            if (! people && ! bouts) return this.words.delete_ask;

            const drawn = bouts === 1
                ? this.words.bouts_one
                : (this.words.bouts_many || '').replace(':count', bouts);

            if (people && bouts) {
                return (this.words.ask_both || '').replace(':count', people).replace(':bouts', drawn);
            }
            if (bouts) {
                return (this.words.ask_draw || '').replace(':bouts', drawn);
            }
            return (this.words.ask_people || '').replace(':count', people);
        },

        async remove() {
            if (this.saving || ! this.canDelete()) return;

            const row = this.server
                ? this.items.find((d) => d.id === this.editing)
                : this.rows()[this.editing];

            /* A group whose bouts have been fought cannot go, and the server
               says so with a 422. Saying it HERE means the organiser is not
               asked to confirm something that was never going to happen. */
            if (row && Number(row.fought || 0) > 0) {
                window.showToast('error', this.words.has_results);
                return;
            }

            const ok = await window.confirmAction({
                title: this.words.delete,
                message: this.deleteAsk(row),
                type: 'danger',
                confirmText: this.words.delete,
            });
            if (! ok) return;

            if (! this.server) {
                const removed = this.rows().splice(this.editing, 1)[0] || null;
                this.announce('deleted', removed);
                this.open = false;
                return;
            }

            const id = this.editing;
            this.saving = true;

            /* `release` says the organiser has been told what goes with the
               group: its unfought bouts, and the entrants' place in it (they
               stay entered — see destroyDivision). A group whose bouts have
               been FOUGHT is still refused outright, and the toast says so. */
            const data = await this.request('DELETE', this.urls.base + '/' + id, { release: true });
            this.saving = false;

            if (! data || data.success === false) return;

            this.items = this.items.filter((d) => d.id !== id);
            window.showToast('success', data.message || this.words.deleted);
            this.announce('deleted', { id: id });
            this.open = false;
        },

        /* ── Copying a division ──────────────────────────────────────────
           An event is built out of near-identical divisions: the same age band
           and belt at six weights, then the same six again without the gi.
           This is that, in one press.

           The server owns what a copy IS (duplicateDivision) — the settings and
           nothing that happened in the original. The client only has to put the
           new row in the right place, which it is told: `after` names the
           division it was inserted below, so the list does not have to re-read
           itself to find out. */
        async duplicate() {
            if (this.saving || ! this.canManage || this.editing === null || ! this.server) return;

            this.saving = true;
            const data = await this.request('POST', this.urls.base + '/' + this.editing + '/duplicate', null);
            this.saving = false;

            if (! data || ! data.division) return;

            const list = this.rows();
            const at = list.findIndex((d) => d && d.id === data.after);

            if (at === -1) list.push(data.division);
            else list.splice(at + 1, 0, data.division);

            window.showToast('success', data.message || this.words.copied);
            this.announce('created', data.division);
            this.open = false;

            // Letters are positional, and a new row in the middle moves them.
            this.reletter();
        },

        /* ── Keeping the letters honest ──────────────────────────────────────
           A generated name carries "Group A", "Group B"… counted from the
           heading above it. Move a card and those numbers are wrong, so every
           AUTO-NAMED row is rebuilt after the list changes.

           Only auto-named rows: a name somebody typed is theirs, and having the
           list quietly rewrite it because a neighbour moved would be the worst
           kind of surprise. "Auto-named" is decided the same way the sheet
           decides it — the stored name is exactly what these settings would
           produce at the row's OLD position — so a hand-typed name is never
           mistaken for one of ours.

           Each changed row is saved on its own; a reorder usually shifts one or
           two letters, and the endpoint is the ordinary division update. */
        async reletter() {
            if (! this.server || ! this.canManage) return;

            const list = this.rows();
            const held = this.form;
            const changed = [];

            list.forEach((d, i) => {
                if (! d || ! d.id || d.is_heading) return;
                if (! this.looksGenerated(d, i)) return;

                this.form = this.flat(d);
                const next = this.generatedName(i);
                this.form = held;

                if (next && next !== String(d.name || '').trim()) {
                    changed.push({ id: d.id, name: next, row: d });
                }
            });

            for (const c of changed) {
                c.row.name = c.name;                       // in place, at once
                await this.request('PATCH', this.urls.base + '/' + c.id, { name: c.name });
            }
        },

        upsert(division) {
            if (! division) return;
            const at = this.items.findIndex((d) => d.id === division.id);
            if (at === -1) this.items.push(division);
            else this.items.splice(at, 1, division);
        },

        announce(action, division) {
            window.dispatchEvent(new CustomEvent('event-divisions-changed', {
                detail: { action: action, event: this.eventKey, division: division },
            }));
        },

        /** One door to the network, so every failure is reported the same way. */
        async request(method, url, body) {
            try {
                const res = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    credentials: 'same-origin',
                    body: body === null ? undefined : JSON.stringify(body),
                });
                const data = await res.json().catch(() => ({}));

                if (res.status === 422 && data.errors) {
                    this.errors = Object.fromEntries(
                        Object.entries(data.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])
                    );
                    if (data.message) window.showToast('error', data.message);
                    return null;
                }
                /* 409 is not a failure — it is the server asking a question
                   (see remove()). It carries the count and the sentence, and
                   the caller decides; toasting it here would answer it. */
                if (res.status === 409 && data.needs_release) {
                    return data;
                }
                if (! res.ok || data.success === false) {
                    window.showToast('error', data.message || this.words.failed);
                    return null;
                }
                return data;
            } catch (e) {
                window.showToast('error', this.words.failed);
                return null;
            }
        },
    }));

    if (window.Alpine) register(window.Alpine);
    else document.addEventListener('alpine:init', () => register(window.Alpine));
})();
</script>
@endpush
@endonce
