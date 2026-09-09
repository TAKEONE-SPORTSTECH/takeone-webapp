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
    ] : null;

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
        <div>
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
                </span>
                <i class="bi bi-chevron-right text-muted-foreground/50 text-xs flex-shrink-0 rtl:rotate-180" x-show="canManage"></i>
            </button>
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
            {{ $actions ?? '' }}
        </div>
    @endif

    {{-- ===== The sheet =====
         Teleported: the mobile shell leaves a transform on its children, which
         would make `fixed` resolve against a wrapper instead of the viewport
         and clip the form. --}}
    <template x-teleport="body" data-teleport-template="true">
        <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-end sm:items-center sm:justify-center sm:p-4"
             @keydown.escape.window="close()" style="display:none;">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/40" @click="close()"></div>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full sm:translate-y-4 sm:opacity-0" x-transition:enter-end="translate-y-0 sm:opacity-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0 sm:opacity-100" x-transition:leave-end="translate-y-full sm:translate-y-4 sm:opacity-0"
                 class="relative w-full sm:max-w-lg max-h-[92vh] flex flex-col bg-white rounded-t-3xl sm:rounded-2xl shadow-2xl">

                {{-- Gradient header band (Design Rule #8). --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl sm:rounded-t-2xl text-white relative overflow-hidden"
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
                            <h3 class="text-lg font-black leading-tight"
                                x-text="form.is_heading
                                    ? words.heading
                                    : (editing === null
                                        ? @js(__('events.divisions_new'))
                                        : @js(__('events.divisions_edit')))"></h3>
                            <p class="text-[12px] text-white/85 mt-0.5 truncate"
                               x-text="form.name || @js(__('events.divisions_hint'))"></p>
                        </div>
                        <button type="button" @click="close()" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                {{-- Body scrolls; the actions below never do. --}}
                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">

                    {{-- Name --}}
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-500 mb-1">{{ __('events.divisions_name') }}</label>
                        <input x-model="form.name" type="text" maxlength="80"
                               placeholder="{{ __('events.divisions_name_ph') }}"
                               class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none"
                               :class="errors.name ? 'border-red-400' : 'border-gray-200'">
                        <p x-show="errors.name" x-cloak class="mt-1 text-xs text-red-500" x-text="errors.name"></p>
                    </div>

                    {{-- A HEADING stops here: a name is all it is.
                         Everything below belongs to a division — a capacity, a
                         range, a schedule — and none of it would ever be read
                         on a title. The endpoint drops these fields for a
                         heading too (headingSafe), so the form and the contract
                         agree rather than one trusting the other. --}}
                    <div x-show="form.is_heading" x-cloak
                         class="rounded-xl bg-muted/60 border border-gray-100 p-3 flex items-start gap-2.5">
                        <i class="bi bi-bookmark-fill text-primary text-sm mt-0.5"></i>
                        <p class="text-[11px] text-muted-foreground leading-relaxed" x-text="words.heading_hint"></p>
                    </div>

                    <div x-show="! form.is_heading" x-cloak class="space-y-4">

                    {{-- Capacity. Blank is the normal answer: a division is
                         capped to keep a mat's day finite, not because a
                         bracket needs a number. --}}
                    <div>
                        <label class="block text-[11px] font-semibold text-gray-500 mb-1">{{ __('events.divisions_capacity') }}</label>
                        <input x-model="form.capacity" type="number" min="2" inputmode="numeric"
                               placeholder="{{ __('events.divisions_no_cap') }}"
                               class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none"
                               :class="errors.capacity ? 'border-red-400' : 'border-gray-200'">
                        <p class="mt-1 text-[10px] text-muted-foreground">{{ __('events.divisions_capacity_hint') }}</p>
                        <p x-show="errors.capacity" x-cloak class="mt-1 text-xs text-red-500" x-text="errors.capacity"></p>
                    </div>

                    {{-- ===== What the division is FOR =====
                         Every bound is optional and blank means ANY. They
                         narrow the roster; they never forbid an organiser from
                         putting somebody in (App\Events\Support\DivisionRange). --}}
                    <div>
                        <p class="text-sm font-bold text-foreground">{{ __('events.divisions_whatfor') }}</p>
                        <p class="text-[11px] text-muted-foreground leading-snug mt-0.5 mb-2">{{ __('events.divisions_whatfor_hint') }}</p>

                        {{-- Gender: three known answers, so cards rather than a
                             dropdown (Mobile Pattern Language §3). --}}
                        <label class="block text-[11px] font-semibold text-gray-500 mb-1">{{ __('events.divisions_gender') }}</label>
                        <div class="grid grid-cols-3 gap-2 mb-3">
                            @foreach([['', 'divisions_gender_any', 'bi-people'], ['Male', 'divisions_gender_male', 'bi-gender-male'], ['Female', 'divisions_gender_female', 'bi-gender-female']] as [$g, $k, $icon])
                                <button type="button" @click="form.gender = @js($g)"
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
                            <input x-model="form.min_age" type="number" min="2" max="100" inputmode="numeric"
                                   placeholder="{{ __('events.divisions_from') }}"
                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                            <input x-model="form.max_age" type="number" min="2" max="100" inputmode="numeric"
                                   placeholder="{{ __('events.divisions_to') }}"
                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                        </div>
                        <p x-show="errors.max_age" x-cloak class="mb-2 text-xs text-red-500" x-text="errors.max_age"></p>

                        {{-- Weight --}}
                        <label class="block text-[11px] font-semibold text-gray-500 mb-1 mt-2">{{ __('events.divisions_weight') }}</label>
                        <div class="grid grid-cols-2 gap-2 mb-1">
                            <input x-model="form.min_weight" type="number" min="10" max="300" step="0.1" inputmode="decimal"
                                   placeholder="{{ __('events.divisions_from') }}"
                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                            <input x-model="form.max_weight" type="number" min="10" max="300" step="0.1" inputmode="decimal"
                                   placeholder="{{ __('events.divisions_to') }}"
                                   class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                        </div>
                        <p x-show="errors.max_weight" x-cloak class="text-xs text-red-500" x-text="errors.max_weight"></p>
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
                    <div>
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
                    </div>

                    {{-- Deleting is offered only for a division that exists.
                         The server refuses one with people or bouts in it —
                         that is somebody's work — and the refusal is shown
                         rather than the row disappearing. --}}
                    <button type="button" x-show="canDelete()" x-cloak @click="remove()" :disabled="saving"
                            class="m-press w-full py-2.5 rounded-xl border border-red-300 text-red-600 font-semibold text-sm flex items-center justify-center gap-2 transition-colors hover:bg-red-50 disabled:opacity-60">
                        <i class="bi bi-trash"></i> {{ __('events.divisions_delete') }}
                    </button>
                </div>


                    </div>{{-- /division-only fields --}}

                {{-- Sticky footer, safe-area padded, so Save is always reachable. --}}
                <div class="flex-shrink-0 px-5 pt-3 border-t border-gray-100"
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

        /* Local mode hands us a live reference to the HOST's array. Server mode
           owns its own list, seeded from the payloads the page rendered with. */
        read: typeof cfg.read === 'function' ? cfg.read : null,
        items: Array.isArray(cfg.items) ? cfg.items : [],

        open: false,
        saving: false,
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
            this.open = true;
        },

        openEdit(d, i) {
            if (! this.canManage) return;
            this.editing = this.server ? (d.id ?? null) : i;
            this.errors = {};
            this.form = this.flat(d);
            this.open = true;
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
