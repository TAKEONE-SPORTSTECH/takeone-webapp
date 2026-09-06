@props([
    'event',                        // event uuid
    'canManage' => false,           // may create / rename / delete a group
    'color' => '#7c6bf5',           // the event's colour, for the sheet bands

    /* Render the triggers as ONE three-dot menu instead of a row of labelled
       buttons. On the manage-draw bar the row had grown to three text buttons
       beside a back pill, a title and the page's own actions menu — too many
       words in 56px of height (reported 2026-09-04). The `actions` slot takes
       whatever else the host page wants in the same menu (its draw verbs), so
       the bar ends up with a single control rather than two. */
    'asMenu' => false,
])

@php
    // Whitelisted before it reaches a style attribute — the colour is
    // organiser-supplied, and the gradient below interpolates it directly.
    $c = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $color) ? $color : '#7c6bf5';

    // The 3:4 portrait fallbacks, resolved server-side so an Alpine row can
    // simply point at one (CLAUDE.md → Profile Pictures Are Portrait 3:4:
    // fall back to the drawn avatar, never an icon or initials).
    $avatars = [];
    foreach (['Male', 'Female'] as $g) {
        $name = $g === 'Female' ? 'female' : 'male';
        $file = file_exists(public_path("images/avatars/{$name}.jpg"))
            ? "images/avatars/{$name}.jpg" : "images/avatars/{$name}.svg";
        $avatars[$g] = asset($file).'?v='.@filemtime(public_path($file));
    }
@endphp

{{--
    Groups — building a bracket by hand.

    A "group" is a division: the set of entrants a bracket is cut from. The
    packages cut divisions automatically from their own weight and belt tables,
    which is right until the morning of the event, when reality arrives: four
    people do not show and two brackets have to merge, a category nobody planned
    turns up, a strong junior is safer moved up than left with no fight at all.

    So this is the manual path. Two sheets:

      · GROUP    — what the group is called and what it is FOR (gender, age
                   range, weight range). Every bound is optional and means ANY
                   when left blank.
      · PEOPLE   — every entrant in the event, with what is known about each,
                   filtered down by the group's own range as a starting point.

    ── The filters narrow; they never forbid ──────────────────────────────────
    This is the whole point of the screen. The range sorts the roster and marks
    who sits outside it, and then the organiser decides. Putting a fourteen-
    year-old in the adult group is one tap, and the fact that somebody chose to
    is visible afterwards instead of being lost. See App\Events\Support\DivisionRange.

    ── Missing data is shown as missing ───────────────────────────────────────
    Birthdate is never required on this platform, weight is only known after a
    weigh-in, and an athlete entered off a paper sheet often has neither. Those
    people are ALWAYS offered and read "age —", never quietly filtered out.

    Standalone: it needs the board's public API (window.BracketBoard.reload) and
    its `bracket:loaded` / `bracket:state` events, both documented, and nothing
    else. Drop it beside any bracket.
--}}
<div x-data="divisionGroups({
        eventKey: @js($event),
        canManage: @js((bool) $canManage),
        avatars: @js($avatars),
        urls: {
            store: @js(route('me.events.divisions.store', $event)),
            base:  @js(route('me.events.divisions.store', $event)),
            // Registering somebody who is not on the platform at all.
            enter: @js(route('me.events.entries.unnamed', $event)),
        },
        words: @js([
            'new'        => __('personal.division_new'),
            'edit'       => __('personal.division_edit'),
            'people'     => __('personal.division_people'),
            'name'       => __('personal.division_name'),
            'name_hint'  => __('personal.division_name_hint'),
            'whatfor'    => __('personal.division_whatfor'),
            'whatfor_hint' => __('personal.division_whatfor_hint'),
            'any'        => __('personal.division_any'),
            'male'       => __('personal.division_male'),
            'female'     => __('personal.division_female'),
            'age'        => __('personal.division_age'),
            'weight'     => __('personal.division_weight'),
            'from'       => __('personal.division_from'),
            'to'         => __('personal.division_to'),
            'save'       => __('personal.division_save'),
            'cancel'     => __('shared.close'),
            'delete'     => __('personal.division_delete'),
            'delete_ask' => __('personal.division_delete_ask'),
            'search'     => __('personal.division_search'),
            'onlyfit'    => __('personal.division_only_fitting'),
            'in_group'   => __('personal.division_in_group'),
            'elsewhere'  => __('personal.division_elsewhere'),
            'free'       => __('personal.division_unassigned'),
            'outside'    => __('personal.division_outside'),
            'unknown'    => __('personal.division_unknown'),
            'add'        => __('personal.division_add'),
            'remove'     => __('personal.division_remove'),
            'apply'      => __('personal.division_apply'),
            'nobody'     => __('personal.division_nobody'),
            'clear'      => __('personal.division_clear_filters'),
            'miss_gender' => __('personal.division_miss_gender'),
            'miss_age'    => __('personal.division_miss_age'),
            'miss_weight' => __('personal.division_miss_weight'),
            'kg'          => __('personal.division_kg'),
            'yrs'         => __('personal.division_yrs'),
            'register'    => __('personal.division_register'),
            'register_sub' => __('personal.division_register_sub'),
            'reg_name'    => __('personal.division_reg_name'),
            'reg_only_name' => __('personal.division_reg_only_name'),
            'reg_save'    => __('personal.division_reg_save'),
            'reg_link'    => __('personal.division_reg_link'),
            'reg_copy'    => __('personal.division_reg_copy'),
            'reg_copied'  => __('personal.division_reg_copied'),
            'birthdate'   => __('personal.division_birthdate'),
        ]),
     })"
     @bracket:loaded.window="sync($event.detail)"
     @bracket:state.window="sync($event.detail)"
     class="flex items-center gap-2">

    {{-- ===== Triggers =====

         Two shapes, one set of verbs. `asMenu` folds them into a single
         three-dot control (see the prop); the default is the labelled row the
         other callers already use. Both drive the SAME methods and the same
         sheets below, so neither shape owns any behaviour of its own. --}}
    @if($asMenu)
        <div class="relative" x-data="{ menu: false }"
             @click.outside="menu = false" @keydown.escape="menu = false">
            <button type="button" @click="menu = ! menu" :aria-expanded="menu"
                    aria-haspopup="menu" title="{{ __('personal.event_show_actions') }}"
                    class="ev-ico w-9 h-9 shrink-0 rounded-xl border border-gray-200 flex items-center
                           justify-center text-muted-foreground hover:bg-muted/60 transition-colors">
                <i class="bi bi-three-dots-vertical"></i>
            </button>

            <div x-show="menu" x-cloak role="menu"
                 x-transition:enter="transition ease-out duration-100"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="absolute end-0 mt-1 w-64 bg-white border border-gray-100 rounded-xl shadow-lg
                        overflow-hidden z-50">

                {{-- The group the board is showing, named, so the two rows
                     under it are obviously about THAT group and not the event. --}}
                <div x-show="division" x-cloak
                     class="px-3 pt-2.5 pb-1.5 border-b border-gray-100">
                    <p class="text-[10px] font-extrabold uppercase tracking-wider text-muted-foreground leading-none"
                       x-text="words.whatfor"></p>
                    <p class="text-[13px] font-bold text-foreground truncate mt-1" x-text="groupName"></p>
                </div>

                <button type="button" x-show="division" x-cloak role="menuitem"
                        @click="menu = false; openPeople()"
                        class="w-full text-start px-3 py-2.5 flex items-center gap-2.5 text-sm hover:bg-muted/60 transition-colors">
                    <span class="w-8 h-8 rounded-lg grid place-items-center flex-shrink-0 text-white"
                          style="background: {{ $c }};">
                        <i class="bi bi-person-plus-fill text-sm"></i>
                    </span>
                    <span class="font-bold text-foreground" x-text="words.people"></span>
                </button>

                @if($canManage)
                    <button type="button" x-show="division" x-cloak role="menuitem"
                            @click="menu = false; openGroup(division)"
                            class="w-full text-start px-3 py-2.5 flex items-center gap-2.5 text-sm hover:bg-muted/60 transition-colors">
                        <span class="w-8 h-8 rounded-lg grid place-items-center flex-shrink-0 bg-primary/10 text-primary">
                            <i class="bi bi-pencil text-sm"></i>
                        </span>
                        <span class="font-bold text-foreground" x-text="words.edit"></span>
                    </button>

                    <button type="button" role="menuitem" @click="menu = false; openGroup(null)"
                            class="w-full text-start px-3 py-2.5 flex items-center gap-2.5 text-sm hover:bg-muted/60 transition-colors">
                        <span class="w-8 h-8 rounded-lg grid place-items-center flex-shrink-0 bg-primary/10 text-primary">
                            <i class="bi bi-collection text-sm"></i>
                        </span>
                        <span class="font-bold text-foreground" x-text="words.new"></span>
                    </button>
                @endif

                {{-- Whatever the host page wants in the same menu. Given its own
                     divider only when there is something in it, and closing the
                     menu is the slot's business: these are usually POST forms
                     that navigate anyway. --}}
                @isset($actions)
                    <div class="border-t border-gray-100">{{ $actions }}</div>
                @endisset
            </div>
        </div>
    @else
        @if($canManage)
            <button type="button" @click="openGroup(null)"
                    class="h-9 px-3 rounded-xl border border-gray-200 bg-white text-xs font-bold text-foreground
                           hover:bg-muted/60 hover:shadow-sm transition-all flex items-center gap-1.5"
                    :title="words.new">
                <i class="bi bi-collection"></i>
                <span class="hidden sm:inline" x-text="words.new"></span>
            </button>
        @endif

        {{-- ===== Edit the group the board is showing =====

         The sheet could always edit — `openGroup(id)` loads a group into the
         form, the PATCH endpoint has always existed, and the sheet even
         re-titles itself "Edit group" and grows a Delete button when it holds
         one. NOTHING EVER CALLED IT with an id: the only trigger was
         `openGroup(null)`, so a group's name could be set once, when it was
         created, and never corrected (reported 2026-09-04 — an event carrying a
         division called after a person, with no way to rename it).

         It sits between "New group" and "People" because that is the order the
         work happens in: make the group, fix what it says, then fill it. Shown
         only while the board HAS a division selected — there is otherwise
         nothing to edit — and only to whoever may manage the event. --}}
    @if($canManage)
        <button type="button" @click="openGroup(division)" x-show="division" x-cloak
                class="h-9 px-3 rounded-xl border border-gray-200 bg-white text-xs font-bold text-foreground
                       hover:bg-muted/60 hover:shadow-sm transition-all flex items-center gap-1.5"
                :title="words.edit + (groupName ? ' — ' + groupName : '')">
            <i class="bi bi-pencil"></i>
            <span class="hidden sm:inline" x-text="words.edit"></span>
        </button>
    @endif

        <button type="button" @click="openPeople()" x-show="division" x-cloak
                class="h-9 px-3 rounded-xl text-white text-xs font-bold shadow-sm hover:shadow-md
                       transition-all flex items-center gap-1.5"
                style="background: {{ $c }};"
                :title="words.people">
            <i class="bi bi-person-plus-fill"></i>
            <span class="hidden sm:inline" x-text="words.people"></span>
        </button>
    @endif

    {{-- ===================== SHEET: the group itself ===================== --}}
    <template x-teleport="body">
        <div x-show="groupOpen" x-cloak class="fixed inset-0 z-[70]" role="dialog" aria-modal="true">
            <div x-show="groupOpen" x-transition.opacity class="absolute inset-0 bg-black/50"
                 @click="groupOpen = false"></div>

            <div x-show="groupOpen" x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="absolute inset-x-0 bottom-0 mx-auto w-full sm:max-w-lg max-h-[92vh] flex flex-col
                        rounded-t-3xl bg-background shadow-2xl overflow-hidden">

                {{-- The gradient band every sheet opens with (Design Rule #8).
                     ⚠️ hex → hex+b0; an hsl() with b0 appended is an invalid
                     gradient and the whole declaration is dropped. --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $c }}, {{ $c }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-collection text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight"
                                x-text="groupForm.id ? words.edit : words.new"></h3>
                            <p class="text-[12px] text-white/85 mt-0.5" x-text="words.whatfor_hint"></p>
                        </div>
                        <button type="button" @click="groupOpen = false" :aria-label="words.cancel"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur
                                       grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" x-text="words.name"></label>
                        <input type="text" x-model="groupForm.name" :placeholder="words.name_hint"
                               class="w-full px-3 py-2.5 border border-gray-200 rounded-lg
                                      focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <p class="text-xs text-red-600 mt-1" x-show="errors.name" x-text="errors.name"></p>
                    </div>

                    <div>
                        <p class="text-xs font-extrabold uppercase tracking-wider text-muted-foreground mb-2"
                           x-text="words.whatfor"></p>

                        {{-- Selection cards, not a native select (Design Rule #4). --}}
                        <div class="grid grid-cols-3 gap-2">
                            <template x-for="opt in [
                                {v: null,      l: words.any,    i: 'bi-people-fill',        c: 'text-muted-foreground'},
                                {v: 'Male',    l: words.male,   i: 'bi-gender-male',        c: 'text-blue-500'},
                                {v: 'Female',  l: words.female, i: 'bi-gender-female',      c: 'text-pink-500'}
                            ]" :key="String(opt.v)">
                                <button type="button" @click="groupForm.gender = opt.v"
                                        :class="groupForm.gender === opt.v
                                            ? 'border-primary bg-primary/5 text-primary'
                                            : 'border-gray-200 text-foreground hover:bg-muted/60'"
                                        class="h-14 rounded-xl border flex flex-col items-center justify-center
                                               gap-1 text-xs font-bold transition-colors">
                                    <i class="bi" :class="opt.i + ' ' + (groupForm.gender === opt.v ? '' : opt.c)"></i>
                                    <span x-text="opt.l"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <span x-text="words.age"></span>
                                <span class="text-muted-foreground font-normal" x-text="'(' + words.yrs + ')'"></span>
                            </label>
                            <div class="flex items-center gap-2">
                                <input type="number" min="2" max="100" x-model.number="groupForm.min_age"
                                       :placeholder="words.from"
                                       class="w-full px-3 py-2.5 border border-gray-200 rounded-lg
                                              focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                <span class="text-muted-foreground text-xs">–</span>
                                <input type="number" min="2" max="100" x-model.number="groupForm.max_age"
                                       :placeholder="words.to"
                                       class="w-full px-3 py-2.5 border border-gray-200 rounded-lg
                                              focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            </div>
                            <p class="text-xs text-red-600 mt-1" x-show="errors.max_age" x-text="errors.max_age"></p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                <span x-text="words.weight"></span>
                                <span class="text-muted-foreground font-normal" x-text="'(' + words.kg + ')'"></span>
                            </label>
                            <div class="flex items-center gap-2">
                                <input type="number" min="10" max="300" step="0.1" x-model.number="groupForm.min_weight"
                                       :placeholder="words.from"
                                       class="w-full px-3 py-2.5 border border-gray-200 rounded-lg
                                              focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                <span class="text-muted-foreground text-xs">–</span>
                                <input type="number" min="10" max="300" step="0.1" x-model.number="groupForm.max_weight"
                                       :placeholder="words.to"
                                       class="w-full px-3 py-2.5 border border-gray-200 rounded-lg
                                              focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                            </div>
                            <p class="text-xs text-red-600 mt-1" x-show="errors.max_weight" x-text="errors.max_weight"></p>
                        </div>
                    </div>

                    <p class="text-xs text-muted-foreground leading-relaxed">
                        <i class="bi bi-info-circle me-1"></i>{{ __('personal.division_range_is_a_guide') }}
                    </p>

                    <button type="button" x-show="groupForm.id" @click="destroyGroup()"
                            class="w-full h-12 rounded-xl border border-red-300 text-red-600
                                   hover:bg-red-50 text-sm font-bold transition-colors flex items-center
                                   justify-center gap-2">
                        <i class="bi bi-trash"></i><span x-text="words.delete"></span>
                    </button>
                </div>

                <div class="flex-shrink-0 border-t border-gray-100 bg-white px-5 pt-3"
                     style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="saveGroup()" :disabled="groupSaving || !groupForm.name"
                            class="w-full h-12 rounded-xl bg-primary text-white text-sm font-bold
                                   hover:bg-primary/90 disabled:opacity-50 transition-colors
                                   flex items-center justify-center gap-2">
                        <i class="bi" :class="groupSaving ? 'bi-arrow-repeat animate-spin' : 'bi-check-lg'"></i>
                        <span x-text="words.save"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- ============ SHEET: registering somebody not on the platform ============
         The walk-in. Somebody hands over a name at the desk and competes in
         twenty minutes; they have no account and may never want one.

         A NAME is the only thing asked for. Everything else is offered because
         the desk sometimes knows it — and when it does, recording it is what
         lets the athlete be dropped into a group straight away instead of being
         chased a second time. Nothing here is ever demanded: a guessed birthdate
         is worse than a blank one, because it drives age groups and the minor
         safeguards (CLAUDE.md → "Who Fills The Form Decides What It Demands").

         They still get a claim link, so the person can own the record later. --}}
    <template x-teleport="body">
        <div x-show="regOpen" x-cloak class="fixed inset-0 z-[80]" role="dialog" aria-modal="true">
            <div x-show="regOpen" x-transition.opacity class="absolute inset-0 bg-black/50"
                 @click="regOpen = false"></div>

            <div x-show="regOpen" x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="absolute inset-x-0 bottom-0 mx-auto w-full sm:max-w-lg max-h-[92vh] flex flex-col
                        rounded-t-3xl bg-background shadow-2xl overflow-hidden">

                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $c }}, {{ $c }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-person-plus-fill text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight" x-text="words.register"></h3>
                            <p class="text-[12px] text-white/85 mt-0.5" x-text="words.register_sub"></p>
                        </div>
                        <button type="button" @click="regOpen = false" :aria-label="words.cancel"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur
                                       grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">
                    {{-- The one required field. --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" x-text="words.reg_name"></label>
                        <input type="text" x-model="newPerson.full_name" autocomplete="off"
                               class="w-full px-3 py-2.5 border border-gray-200 rounded-lg
                                      focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <p class="text-xs text-muted-foreground mt-1" x-text="words.reg_only_name"></p>
                        <p class="text-xs text-red-600 mt-1" x-show="errors.full_name" x-text="errors.full_name"></p>
                    </div>

                    <div>
                        <p class="text-xs font-extrabold uppercase tracking-wider text-muted-foreground mb-2"
                           x-text="words.whatfor"></p>
                        <div class="grid grid-cols-3 gap-2">
                            <template x-for="opt in [
                                {v: null,     l: words.unknown, i: 'bi-question-circle', c: 'text-muted-foreground'},
                                {v: 'Male',   l: words.male,    i: 'bi-gender-male',     c: 'text-blue-500'},
                                {v: 'Female', l: words.female,  i: 'bi-gender-female',   c: 'text-pink-500'}
                            ]" :key="'g'+String(opt.v)">
                                <button type="button" @click="newPerson.gender = opt.v"
                                        :class="newPerson.gender === opt.v
                                            ? 'border-primary bg-primary/5 text-primary'
                                            : 'border-gray-200 text-foreground hover:bg-muted/60'"
                                        class="h-14 rounded-xl border flex flex-col items-center justify-center
                                               gap-1 text-xs font-bold transition-colors">
                                    <i class="bi" :class="opt.i + ' ' + (newPerson.gender === opt.v ? '' : opt.c)"></i>
                                    <span x-text="opt.l"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    {{-- A calendar, never a native date input (Design Rule #4). --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1" x-text="words.birthdate"></label>
                        <x-date-picker model="newPerson.birthdate" :max="now()->toDateString()" />
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            <span x-text="words.weight"></span>
                            <span class="text-muted-foreground font-normal" x-text="'(' + words.kg + ')'"></span>
                        </label>
                        <input type="number" min="10" max="300" step="0.1" x-model.number="newPerson.weight"
                               class="w-full px-3 py-2.5 border border-gray-200 rounded-lg
                                      focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    </div>

                    {{-- The link that lets the athlete claim the record afterwards.
                         Shown once, right after it is minted — it is a credential
                         and is never handed back out of a listing. --}}
                    <div x-show="freshClaim" x-cloak
                         class="rounded-xl border border-green-200 bg-green-50 p-3 space-y-2">
                        <p class="text-xs font-bold text-green-800" x-text="words.reg_link"></p>
                        <div class="flex items-center gap-2">
                            <input type="text" readonly :value="freshClaim?.url"
                                   class="flex-1 min-w-0 px-2 py-1.5 rounded-lg border border-green-200
                                          bg-white text-[11px] text-gray-700">
                            <button type="button" @click="copyClaim()"
                                    class="px-3 py-1.5 rounded-lg bg-green-600 text-white text-xs font-bold
                                           hover:bg-green-700 transition-colors"
                                    x-text="words.reg_copy"></button>
                        </div>
                    </div>
                </div>

                <div class="flex-shrink-0 border-t border-gray-100 bg-white px-5 pt-3"
                     style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="registerPerson()"
                            :disabled="regSaving || !(newPerson.full_name || '').trim()"
                            class="w-full h-12 rounded-xl bg-primary text-white text-sm font-bold
                                   hover:bg-primary/90 disabled:opacity-50 transition-colors
                                   flex items-center justify-center gap-2">
                        <i class="bi" :class="regSaving ? 'bi-arrow-repeat animate-spin' : 'bi-person-check-fill'"></i>
                        <span x-text="words.reg_save"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- ===================== SHEET: who is in it ===================== --}}
    <template x-teleport="body">
        <div x-show="peopleOpen" x-cloak class="fixed inset-0 z-[70]" role="dialog" aria-modal="true">
            <div x-show="peopleOpen" x-transition.opacity class="absolute inset-0 bg-black/50"
                 @click="peopleOpen = false"></div>

            <div x-show="peopleOpen" x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="absolute inset-x-0 bottom-0 mx-auto w-full sm:max-w-2xl max-h-[92vh] flex flex-col
                        rounded-t-3xl bg-background shadow-2xl overflow-hidden">

                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $c }}, {{ $c }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-person-plus-fill text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight" x-text="groupName"></h3>
                            <p class="text-[12px] text-white/85 mt-0.5" x-text="rangeWords"></p>
                        </div>
                        <button type="button" @click="peopleOpen = false" :aria-label="words.cancel"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur
                                       grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <div class="relative mt-3 flex flex-wrap gap-1.5">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/20 text-[11px] font-bold">
                            <i class="bi bi-people-fill"></i><span x-text="inGroupCount"></span>
                        </span>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/20 text-[11px] font-bold"
                              x-show="pendingCount">
                            <i class="bi bi-pencil"></i><span x-text="pendingCount"></span>
                        </span>
                    </div>
                </div>

                {{-- Filters. Seeded from the group's own range, so the list opens
                     on the people it is for — and every one of them can be
                     cleared, which is how somebody outside the range is found. --}}
                <div class="flex-shrink-0 border-b border-gray-100 bg-white px-4 py-3 space-y-2.5">
                    {{-- ===== Search =====
                         A 44px field, not a 30px one: this is the control the
                         organiser uses most and it is used one-handed at a desk
                         beside a mat. --}}
                    <div class="relative">
                        <i class="bi bi-search absolute start-3.5 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input type="text" x-model="filters.q" :placeholder="words.search"
                               class="w-full h-11 ps-10 pe-10 bg-white border border-gray-200 rounded-xl text-sm
                                      focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        <button type="button" x-show="filters.q" x-cloak @click="filters.q = ''"
                                class="absolute end-2 top-1/2 -translate-y-1/2 w-8 h-8 rounded-lg grid place-items-center
                                       text-muted-foreground hover:bg-muted/60 transition-colors">
                            <i class="bi bi-x-lg text-xs"></i>
                        </button>
                    </div>

                    {{-- ===== Who to show =====
                         A full-width three-up segmented control, the same shape
                         as the group sheet's own gender picker one layer down —
                         so the two sheets teach each other. It replaced three
                         `px-3 py-1.5` pills that were 26px tall (reported
                         2026-09-04: "very narrow buttons"), which is under half
                         the 44px a finger needs. --}}
                    <div class="grid grid-cols-3 gap-2">
                        <template x-for="opt in [
                            {v: null,     l: words.any,    i: 'bi-people-fill',   c: 'text-muted-foreground'},
                            {v: 'Male',   l: words.male,   i: 'bi-gender-male',   c: 'text-blue-500'},
                            {v: 'Female', l: words.female, i: 'bi-gender-female', c: 'text-pink-500'}
                        ]" :key="'f'+String(opt.v)">
                            <button type="button" @click="filters.gender = opt.v"
                                    :class="filters.gender === opt.v
                                        ? 'border-primary bg-primary/5 text-primary'
                                        : 'border-gray-200 bg-white text-foreground hover:bg-muted/60'"
                                    class="h-11 rounded-xl border text-xs font-bold transition-colors
                                           flex items-center justify-center gap-1.5">
                                <i class="bi" :class="opt.i + ' ' + (filters.gender === opt.v ? '' : opt.c)"></i>
                                <span x-text="opt.l"></span>
                            </button>
                        </template>
                    </div>

                    {{-- ===== The range, folded away =====
                         Age and weight are the fine adjustment, reached once a
                         search and a gender have not narrowed it enough — so
                         they are closed by default (Mobile Pattern Language →
                         progressive disclosure) with a live count of what is
                         set, which is what stops a filter being left on by
                         accident with no sign of it. --}}
                    <div x-data="{ more: false }">
                        <div class="flex items-center gap-2">
                            <button type="button" @click="more = ! more"
                                    class="flex-1 h-11 px-3.5 rounded-xl border border-gray-200 bg-white
                                           text-xs font-bold text-foreground hover:bg-muted/60 transition-colors
                                           flex items-center gap-2">
                                <i class="bi bi-sliders text-primary"></i>
                                <span x-text="words.whatfor"></span>
                                <span x-show="activeFilters" x-cloak
                                      class="px-1.5 py-0.5 rounded-full bg-primary/10 text-primary text-[10px] font-extrabold"
                                      x-text="activeFilters"></span>
                                <i class="bi bi-chevron-down ms-auto text-muted-foreground transition-transform"
                                   :class="more && 'rotate-180'"></i>
                            </button>

                            <button type="button" @click="clearFilters()" x-show="activeFilters || filters.q" x-cloak
                                    class="h-11 px-3.5 rounded-xl border border-gray-200 bg-white text-xs font-bold
                                           text-primary hover:bg-primary/5 transition-colors flex items-center gap-1.5"
                                    :title="words.clear">
                                <i class="bi bi-x-circle"></i>
                                <span class="hidden sm:inline" x-text="words.clear"></span>
                            </button>
                        </div>

                        {{-- x-show + x-transition: the Alpine collapse plugin is
                             NOT registered in this project. --}}
                        <div x-show="more" x-cloak x-transition
                             class="mt-2.5 rounded-xl border border-gray-100 bg-white p-3 space-y-3">

                            {{-- Two labelled ranges, each a pair of 44px fields
                                 with a real dash between them, instead of four
                                 26px boxes in a row. --}}
                            <template x-for="row in [
                                {k: 'Age',    l: words.age, unit: words.yrs, min: 2,  max: 100, step: '1'},
                                {k: 'Weight', l: words.weight, unit: words.kg, min: 10, max: 300, step: '0.1'}
                            ]" :key="row.k">
                                <div>
                                    <p class="text-[11px] font-extrabold uppercase tracking-wider text-muted-foreground mb-1.5">
                                        <span x-text="row.l"></span>
                                        <span class="font-bold normal-case tracking-normal text-muted-foreground/70"
                                              x-text="'(' + row.unit + ')'"></span>
                                    </p>
                                    <div class="flex items-center gap-2">
                                        <input type="number" :min="row.min" :max="row.max" :step="row.step"
                                               :placeholder="words.from"
                                               x-model.number="filters[row.k === 'Age' ? 'minAge' : 'minWeight']"
                                               class="w-full h-11 px-3 bg-white border border-gray-200 rounded-xl text-sm tabular-nums
                                                      focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                        <span class="text-muted-foreground text-sm shrink-0">–</span>
                                        <input type="number" :min="row.min" :max="row.max" :step="row.step"
                                               :placeholder="words.to"
                                               x-model.number="filters[row.k === 'Age' ? 'maxAge' : 'maxWeight']"
                                               class="w-full h-11 px-3 bg-white border border-gray-200 rounded-xl text-sm tabular-nums
                                                      focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                                    </div>
                                </div>
                            </template>

                            {{-- The one switch on this sheet, as a full-width
                                 row with a 44px target — not an 16px checkbox
                                 with a label floating beside it. --}}
                            <button type="button" @click="filters.onlyFit = ! filters.onlyFit"
                                    :class="filters.onlyFit
                                        ? 'border-primary bg-primary/5 text-primary'
                                        : 'border-gray-200 bg-white text-foreground hover:bg-muted/60'"
                                    class="w-full h-11 px-3.5 rounded-xl border text-xs font-bold transition-colors
                                           flex items-center gap-2.5"
                                    role="switch" :aria-checked="filters.onlyFit">
                                <span class="w-5 h-5 rounded-md border-2 grid place-items-center shrink-0 transition-colors"
                                      :class="filters.onlyFit ? 'bg-primary border-primary text-white' : 'border-gray-300 bg-white'">
                                    <i class="bi bi-check-lg text-[11px]" x-show="filters.onlyFit" x-cloak></i>
                                </span>
                                <span class="text-start" x-text="words.onlyfit"></span>
                            </button>
                        </div>
                    </div>

                    {{-- Not on the platform at all — the walk-in at the desk.
                         Sits with the roster because that is where you discover
                         somebody is missing from it. --}}
                    <button type="button" @click="openRegister()"
                            class="w-full h-11 rounded-xl border border-dashed border-primary/40
                                   text-primary text-xs font-bold hover:bg-primary/5 transition-colors
                                   flex items-center justify-center gap-2">
                        <i class="bi bi-person-plus"></i><span x-text="words.register"></span>
                    </button>
                </div>

                {{-- The roster --}}
                <div class="flex-1 overflow-y-auto px-4 py-3">
                    <div x-show="loading" class="py-10 text-center text-muted-foreground">
                        <i class="bi bi-arrow-repeat animate-spin text-2xl"></i>
                    </div>

                    <div x-show="!loading && visible.length === 0" x-cloak
                         class="py-10 text-center text-muted-foreground text-sm" x-text="words.nobody"></div>

                    <div class="space-y-2" x-show="!loading">
                        <template x-for="p in visible" :key="p.competitor_id">
                            <button type="button" @click="toggle(p)"
                                    :class="isIn(p)
                                        ? 'border-primary bg-primary/5'
                                        : 'border-gray-100 bg-white hover:bg-muted/40'"
                                    class="w-full rounded-2xl border p-2.5 flex items-center gap-3 text-start
                                           transition-colors shadow-sm">

                                <span class="w-9 h-12 rounded-lg overflow-hidden flex-shrink-0 bg-muted">
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
                                        <i class="bi text-[11px]"
                                           :class="p.gender === 'Female' ? 'bi-gender-female text-pink-500'
                                                 : (p.gender === 'Male' ? 'bi-gender-male text-blue-500'
                                                 : 'bi-question-circle text-muted-foreground')"></i>

                                        {{-- Where they are now. Moving somebody out of another
                                             bracket is the common case and must never be silent. --}}
                                        <span x-show="p.division_name" x-cloak
                                              class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-50 text-amber-700
                                                     truncate" style="max-width:10rem"
                                              x-text="words.elsewhere + ': ' + p.division_name"></span>

                                        {{-- The exception, kept visible. --}}
                                        <span x-show="p.fit === 'out'" x-cloak
                                              class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-red-50 text-red-600"
                                              x-text="words.outside + ' · ' + missWords(p)"></span>
                                        <span x-show="p.fit === 'unknown'" x-cloak
                                              class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-gray-100 text-gray-500"
                                              x-text="words.unknown"></span>
                                    </span>
                                </span>

                                <span class="w-6 h-6 rounded-full border-2 grid place-items-center flex-shrink-0"
                                      :class="isIn(p) ? 'border-primary bg-primary text-white' : 'border-gray-300'">
                                    <i class="bi bi-check-lg text-xs" x-show="isIn(p)"></i>
                                </span>
                            </button>
                        </template>
                    </div>
                </div>

                <div class="flex-shrink-0 border-t border-gray-100 bg-white px-5 pt-3"
                     style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="applyMembers()" :disabled="saving || !pendingCount"
                            class="w-full h-12 rounded-xl bg-primary text-white text-sm font-bold
                                   hover:bg-primary/90 disabled:opacity-50 transition-colors
                                   flex items-center justify-center gap-2">
                        <i class="bi" :class="saving ? 'bi-arrow-repeat animate-spin' : 'bi-check-lg'"></i>
                        <span x-text="pendingCount ? (words.apply + ' (' + pendingCount + ')') : words.apply"></span>
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
 * The groups screen's behaviour.
 *
 * Registered once per page and instantiated by the markup above, so the
 * component carries its own logic rather than depending on a page script
 * (CLAUDE.md → Standalone Self-Contained Components).
 *
 * It talks to exactly two things outside itself, both documented public API:
 *   · window.BracketBoard.reload()  — redraw the board after a write
 *   · the board's bracket:loaded / bracket:state events — which division is open
 */
document.addEventListener('alpine:init', () => {
    Alpine.data('divisionGroups', (cfg) => ({
        eventKey: cfg.eventKey,
        canManage: cfg.canManage,
        avatars: cfg.avatars,
        urls: cfg.urls,
        words: cfg.words,

        divisions: [],
        division: null,

        groupOpen: false,
        groupSaving: false,
        groupForm: {},
        errors: {},

        peopleOpen: false,
        loading: false,
        saving: false,

        // Registering somebody who is not on the platform.
        regOpen: false,
        regSaving: false,
        newPerson: {},
        freshClaim: null,
        people: [],
        range: {},
        picked: {},              // competitor_id => true|false, the PENDING state
        filters: {},

        init() {
            this.groupForm = this.blankGroup();
            this.filters = this.blankFilters();
            this.newPerson = this.blankPerson();
        },

        blankPerson() {
            return { full_name: '', gender: null, birthdate: null, weight: null };
        },

        blankGroup() {
            return { id: null, name: '', weight_class: null, gender: null,
                     min_age: null, max_age: null, min_weight: null, max_weight: null };
        },

        blankFilters() {
            return { q: '', gender: null, minAge: null, maxAge: null,
                     minWeight: null, maxWeight: null, onlyFit: false };
        },

        /** The board told us what it is showing. */
        sync(detail) {
            if (!detail) return;
            if (detail.divisions) this.divisions = detail.divisions;
            if (detail.division !== undefined) this.division = detail.division;
        },

        /** How many of the fine filters are actually set — the count on the
         *  disclosure, so a range left on is never invisible. The text search
         *  is deliberately excluded: it has its own visible ✕. */
        get activeFilters() {
            const f = this.filters;
            return [f.gender, f.minAge, f.maxAge, f.minWeight, f.maxWeight]
                .filter(v => v !== null && v !== '' && v !== undefined && !Number.isNaN(v)).length
                + (f.onlyFit ? 1 : 0);
        },

        get current() {
            return this.divisions.find(d => d.id === this.division) ?? null;
        },

        get groupName() {
            return this.current?.name ?? '';
        },

        /** The group's range, said in words, for the sheet's sub-line. */
        get rangeWords() {
            const r = this.range || {};
            const bits = [];
            if (r.gender) bits.push(r.gender === 'Female' ? this.words.female : this.words.male);
            if (r.min_age != null || r.max_age != null) {
                bits.push((r.min_age ?? '') + '–' + (r.max_age ?? '') + ' ' + this.words.yrs);
            }
            if (r.min_weight != null || r.max_weight != null) {
                bits.push((r.min_weight ?? '') + '–' + (r.max_weight ?? '') + ' ' + this.words.kg);
            }
            return bits.length ? bits.join(' · ') : this.words.any;
        },

        /* ---------------- The group sheet ---------------- */

        openGroup(id) {
            this.errors = {};
            if (id) {
                const d = this.divisions.find(x => x.id === id);
                this.groupForm = Object.assign(this.blankGroup(), {
                    id: d?.id ?? null, name: d?.name ?? '',
                }, d?.range ?? {});
                delete this.groupForm.open;
            } else {
                this.groupForm = this.blankGroup();
            }
            this.groupOpen = true;
        },

        async saveGroup() {
            this.groupSaving = true;
            this.errors = {};

            // Blank means ANY — send null rather than '' so the server stores a
            // real absence instead of failing numeric validation on an empty string.
            const body = {};
            for (const [k, v] of Object.entries(this.groupForm)) {
                if (k === 'id') continue;
                body[k] = (v === '' || v === undefined || Number.isNaN(v)) ? null : v;
            }

            const editing = !!this.groupForm.id;
            const url = editing ? `${this.urls.base}/${this.groupForm.id}` : this.urls.store;

            try {
                const res = await this.send(url, editing ? 'PATCH' : 'POST', body);
                if (!res) return;

                this.groupOpen = false;
                window.showToast('success', res.message || '');
                await this.redraw();

                // Land the organiser in the group they just made, ready to fill it.
                if (!editing && res.division?.id) {
                    window.BracketBoard?.show?.(res.division.id);
                    this.division = res.division.id;
                    this.openPeople();
                }
            } finally {
                this.groupSaving = false;
            }
        },

        async destroyGroup() {
            if (!(await window.confirmAction({
                title: this.words.delete, message: this.words.delete_ask, type: 'danger',
                confirmText: this.words.delete,
            }))) return;

            const res = await this.send(`${this.urls.base}/${this.groupForm.id}`, 'DELETE', null);
            if (!res) return;

            this.groupOpen = false;
            window.showToast('success', res.message || '');
            await this.redraw();
        },

        /* ---------------- The people sheet ---------------- */

        async openPeople() {
            if (!this.division) return;
            this.peopleOpen = true;
            this.loading = true;
            this.picked = {};

            const res = await this.send(`${this.urls.base}/${this.division}/candidates`, 'GET', null);
            this.loading = false;
            if (!res) return;

            this.people = res.people || [];
            this.range = res.division?.range || {};

            // Open on the people the group is FOR. Every one of these is a
            // control the organiser can clear — which is how they reach the
            // athlete who does not belong to the range but belongs in the group.
            this.filters = Object.assign(this.blankFilters(), {
                gender: this.range.gender ?? null,
                minAge: this.range.min_age ?? null,
                maxAge: this.range.max_age ?? null,
                minWeight: this.range.min_weight ?? null,
                maxWeight: this.range.max_weight ?? null,
            });
        },

        clearFilters() { this.filters = this.blankFilters(); },

        /** Is this person in the group, counting unsaved changes? */
        isIn(p) {
            return this.picked[p.competitor_id] ?? p.here;
        },

        toggle(p) {
            const now = this.isIn(p);
            if (now === p.here) this.picked[p.competitor_id] = !now;
            else delete this.picked[p.competitor_id];       // back to where it started
        },

        get pendingCount() {
            return Object.keys(this.picked).length;
        },

        get inGroupCount() {
            return this.people.filter(p => this.isIn(p)).length;
        },

        /**
         * What the list shows.
         *
         * Anyone ALREADY in the group is always shown, whatever the filters say —
         * otherwise narrowing the range would hide the very person you added as
         * an exception, and you could never take them out again.
         */
        get visible() {
            const f = this.filters;
            const q = (f.q || '').trim().toLowerCase();

            return this.people.filter(p => {
                if (this.isIn(p)) return true;

                if (q && !(p.name || '').toLowerCase().includes(q)) return false;
                if (f.gender && p.gender && p.gender !== f.gender) return false;
                if (f.onlyFit && p.fit === 'out') return false;

                // A missing value never excludes somebody — it is not a
                // mismatch, and on a real roster it is most of the room.
                if (p.age != null) {
                    if (f.minAge != null && p.age < f.minAge) return false;
                    if (f.maxAge != null && p.age > f.maxAge) return false;
                }
                if (p.weight != null) {
                    if (f.minWeight != null && p.weight < f.minWeight) return false;
                    if (f.maxWeight != null && p.weight > f.maxWeight) return false;
                }
                return true;
            });
        },

        missWords(p) {
            return (p.misses || []).map(m => ({
                gender: this.words.miss_gender,
                age: this.words.miss_age,
                weight: this.words.miss_weight,
            }[m] ?? m)).join(', ');
        },

        /* ---------------- The walk-in ---------------- */

        openRegister() {
            this.errors = {};
            this.freshClaim = null;
            this.newPerson = this.blankPerson();
            this.regOpen = true;
        },

        /**
         * Register somebody who is not on the platform, and put them straight
         * into the group that is open.
         *
         * The second half is the point. Registering them and then making the
         * organiser find the same name again in a list of two hundred is two
         * jobs where there was one — and at a desk with a queue behind it, that
         * is where people get missed.
         */
        async registerPerson() {
            const name = (this.newPerson.full_name || '').trim();
            if (!name || this.regSaving) return;

            this.regSaving = true;
            this.errors = {};

            try {
                const res = await this.send(this.urls.enter, 'POST', {
                    full_name: name,
                    // Only what was actually filled in. A blank is a blank, not
                    // a guess — see the note on the sheet.
                    gender: this.newPerson.gender || null,
                    birthdate: this.newPerson.birthdate || null,
                    weight: this.newPerson.weight === '' ? null : (this.newPerson.weight ?? null),
                });
                if (!res) return;

                this.freshClaim = res.claim || null;
                window.showToast('success', res.message || '');

                // Back into the roster, with the new person already ticked for
                // this group. They are not saved into it until Save changes —
                // the same one confirmation everything else on this sheet takes.
                const id = res.claim?.competitor_id ?? null;
                await this.reloadPeople();
                if (id) this.picked[id] = true;

                this.newPerson = this.blankPerson();
            } finally {
                this.regSaving = false;
            }
        },

        async copyClaim() {
            if (!this.freshClaim?.url) return;
            try {
                await navigator.clipboard.writeText(this.freshClaim.url);
                window.showToast('success', this.words.reg_copied);
            } catch (e) {
                window.showToast('info', this.freshClaim.url);
            }
        },

        /** Re-read the roster without disturbing the filters or the pending picks. */
        async reloadPeople() {
            const res = await this.send(`${this.urls.base}/${this.division}/candidates`, 'GET', null);
            if (!res) return;
            this.people = res.people || [];
            this.range = res.division?.range || this.range;
        },

        async applyMembers() {
            const add = [], remove = [];

            for (const [id, wanted] of Object.entries(this.picked)) {
                (wanted ? add : remove).push(Number(id));
            }
            if (!add.length && !remove.length) return;

            this.saving = true;
            try {
                const res = await this.send(
                    `${this.urls.base}/${this.division}/members`, 'PUT', { add, remove }
                );
                if (!res) return;

                // The endpoint answers with the refreshed roster, so the sheet
                // patches in place rather than closing (No Page Reload Rule).
                this.people = res.people || [];
                this.range = res.division?.range || this.range;
                this.picked = {};

                window.showToast('success', this.words.apply);
                await this.redraw();
            } finally {
                this.saving = false;
            }
        },

        /* ---------------- Plumbing ---------------- */

        async redraw() {
            await window.BracketBoard?.reload?.();
        },

        /**
         * One request shape for the whole component. A 422 is a refusal the
         * organiser needs to read — field errors go under the fields, anything
         * else becomes a toast.
         */
        async send(url, method, body) {
            try {
                const res = await fetch(url, {
                    method,
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    },
                    credentials: 'same-origin',
                    body: body === null ? undefined : JSON.stringify(body),
                });

                const data = await res.json().catch(() => ({}));

                if (res.status === 422 && data.errors) {
                    this.errors = Object.fromEntries(
                        Object.entries(data.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v])
                    );
                    return null;
                }
                if (!res.ok || data.success === false) {
                    window.showToast('error', data.message || '');
                    return null;
                }
                return data;
            } catch (e) {
                window.showToast('error', '');
                return null;
            }
        },
    }));
});
</script>
@endpush
@endonce
