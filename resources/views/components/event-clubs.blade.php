{{--
    The clubs standing behind a competition.

    A panel on the organiser's console: one row per club, and one button that
    opens the two ways a club gets onto a start list.

    Those two ways are genuinely different questions, which is why the sheet
    asks which one first rather than showing a form with an "or" in it:

      · **Already here.** The club has an account and an owner. The organiser
        does not describe it — they ASK it, and it answers for itself. Search,
        pick, send. The row then says "waiting" until they reply.
      · **Not on TAKEONE.** A team with a coach and a crest and no account.
        Nothing is going to make them register before Saturday, so the organiser
        writes down what they know: a name, a logo, and an Instagram page if
        that is the only address the club has.

    Name and logo are required for the second because they are what a board
    needs to name a team and tell it from the next one. Instagram is optional
    and deliberately so — demanding a website nobody has produces a blank field,
    not a website.

    Standalone: every piece of state, every request and every DOM update lives
    in this file. Drop it into any view that can hand it an event uuid.

    Logos render as the bare image on a transparent ground (Design Rule #5) —
    never on a white tile, which is what makes a transparent PNG look broken
    against a coloured band.

    Props:
      event     ClubEvent uuid (the public key — never an id)
      clubs     array of presented clubs, for the first paint
      canManage whether the viewer may add or remove
      color     the event's colour, for the accents
--}}
@props([
    'event',
    'clubs' => [],
    'canManage' => false,
    'color' => '#7c3aed',
])

@php
    /* Organiser-supplied, and it lands in a style attribute — so it is
       whitelisted here rather than trusted. */
    $ev = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $color) ? $color : '#7c3aed';
    $band = \App\Support\Palette::eventBand($ev);
    $tint = \App\Support\Palette::alpha($ev, .1);
@endphp

@php
    /* Handed to the component as data rather than interpolated into its script:
       the script is registered once per page (@once), so it cannot carry values
       that belong to one instance. */
    $clubUrls = [
        'search'  => route('me.events.clubs.search', $event),
        'store'   => route('me.events.clubs.store', $event),
        'invite'  => route('me.events.clubs.invite', $event),
        'update' => route('me.events.clubs.update', ['event' => $event, 'eventClub' => '__UUID__']),
        'destroy' => route('me.events.clubs.destroy', ['event' => $event, 'eventClub' => '__UUID__']),
        'entrants' => route('me.events.clubs.entrants', $event),
        'athletes' => route('me.events.clubs.athletes', ['event' => $event, 'eventClub' => '__UUID__']),
        'promote' => route('me.events.clubs.promote', $event),
    ];

    $clubLabels = [
        'tab_invite' => __('events.clubs_tab_invite'),
        'tab_new' => __('events.clubs_tab_new'),
        'state_listed' => __('events.club_state_listed'),
        'state_invited' => __('events.club_state_invited'),
        'state_accepted' => __('events.club_state_accepted'),
        'state_declined' => __('events.club_state_declined'),
        'remove_confirm' => __('events.club_remove_confirm'),
        'remove_body' => __('events.club_remove_body'),
        'remove_body_n' => __('events.club_remove_body_n'),
        'shape_square' => __('events.shape_square'),
        'shape_circle' => __('events.shape_circle'),
        'edit' => __('events.club_edit'),
        'save' => __('events.club_save'),
        'add' => __('events.clubs_add'),
        'remove' => __('shared.delete'),
        'athletes' => __('events.club_athletes'),
        'athletes_none' => __('events.club_athletes_none'),
        'athletes_taken' => __('events.club_athletes_taken'),
        'temporary' => __('events.club_temporary'),
        'promoted' => __('events.club_promoted'),
        'promote' => __('events.clubs_promote'),
        'promote_confirm' => __('events.clubs_promote'),
    ];
@endphp

<div x-data="eventClubs()" x-init="init()"
     {{-- The band's own control is outside this component, so it asks by
          event rather than by reaching in. --}}
     @clubs-promote.window="promote()"
     class="mt-6"
     data-clubs='@json($clubs)'
     data-urls='@json($clubUrls)'
     data-labels='@json($clubLabels)'>

    {{-- ===== The way in =====
         A card, not a pill in a header strip.

         The band above already says "Clubs" and counts them, so a second title
         under it was saying the same word twice and the sentence explaining the
         section was squeezed beside a button. What belongs here is the ACTION,
         in the same shape as everything below it — icon tile, title, sub-line,
         chevron — so the list reads as one column of cards with the way to add
         one at the top.

         Dashed rather than solid: it is the one card here that is not a club,
         and the border says so before the words do. --}}
    @if($canManage)
        <button type="button" @click="open()"
                class="m-press w-full rounded-2xl border-2 border-dashed p-3.5 flex items-center gap-3.5 text-start transition-colors"
                style="border-color: {{ \App\Support\Palette::alpha($ev, .35) }}; background: {{ \App\Support\Palette::alpha($ev, .04) }};">
            <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0"
                  style="color: #fff; background: {{ $ev }};">
                <i class="bi bi-plus-lg text-lg"></i>
            </span>
            <span class="min-w-0 flex-1">
                <span class="block text-[15px] font-black text-foreground leading-tight">{{ __('events.clubs_add') }}</span>
                <span class="block text-xs text-muted-foreground mt-0.5">{{ __('events.clubs_tile_sub') }}</span>
            </span>
            <i class="bi bi-chevron-right text-xs flex-shrink-0" style="color: {{ \App\Support\Palette::alpha($ev, .55) }};"></i>
        </button>
    @endif

    {{-- ===== Search =====
         The same box the public entry list carries, so a reader moving between
         the two screens is not learning a second control. --}}
    <div class="relative mt-2.5" x-show="clubs.length > 3" x-cloak>
        <i class="bi bi-search absolute start-3.5 top-1/2 -translate-y-1/2 text-muted-foreground text-sm"></i>
        <input type="search" x-model="q" placeholder="{{ __('personal.event_people_search_clubs') }}"
               class="w-full ps-10 pe-10 py-2.5 text-sm bg-white border border-gray-100 rounded-xl shadow-sm focus:ring-2 focus:ring-primary focus:border-transparent">
        <button type="button" x-show="q" x-cloak @click="q = ''"
                class="absolute end-3 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                aria-label="{{ __('personal.event_people_search_clear') }}">
            <i class="bi bi-x-circle-fill"></i>
        </button>
    </div>

    {{-- ===== The clubs =====
         Crest-forward cards on the page ground — the public entry list's club
         tab, element for element: a w-14 sizing box holding the mark on
         nothing, the name in font-black text-[15px], and one meta line under it
         carrying the flag and the squad size. Siblings on the ground rather
         than rows inside a panel, which is what makes a list of clubs read as a
         list of clubs. --}}
    <div class="mt-2.5 space-y-2.5">
        <template x-for="club in visible" :key="club.uuid">
            {{-- The card IS the way into the squad. "Who did they bring?" is the
                 question asked of every club on the list — invited or written
                 down — so it is the card's own tap rather than a small control
                 hiding on one kind of row. --}}
            <div @click="openAthletes(club)" role="button" tabindex="0"
                 @keydown.enter="openAthletes(club)" @keydown.space.prevent="openAthletes(club)"
                 class="m-card m-press bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3.5 cursor-pointer text-start w-full">

                {{-- Sizing box only — no fill, no ring, no padding tile
                     (Design Rule #5). --}}
                <span class="w-14 h-14 flex-shrink-0 grid place-items-center">
                    <template x-if="club.logo">
                        <img :src="club.logo" alt="" class="w-full h-full object-contain">
                    </template>
                    <template x-if="!club.logo">
                        <i class="bi bi-buildings text-2xl text-muted-foreground"></i>
                    </template>
                </span>

                <div class="min-w-0 flex-1">
                    <p class="font-black text-[15px] text-foreground leading-tight truncate" x-text="club.name"></p>

                    <p class="text-xs text-muted-foreground mt-1 flex items-center gap-2 flex-wrap">
                        <span x-show="club.country" x-cloak
                              :class="'fi fi-' + (club.country || '').toLowerCase()"
                              class="w-4 h-3 rounded-[2px] shrink-0"></span>

                        {{-- How it got here, and what happens to it next. --}}
                        <span class="inline-flex items-center gap-1" :class="stateClass(club.state)">
                            <i class="bi" :class="stateIcon(club.state)"></i><span x-text="stateLabel(club.state)"></span>
                        </span>

                        {{-- The squad. For a temporary club it is also the
                             thing that decides whether it outlives the event,
                             which is why zero is stated rather than hidden. --}}
                        <span class="inline-flex items-center gap-1 font-bold"
                              :style="club.athletes ? 'color: {{ $ev }}' : ''">
                            <i class="bi bi-person-arms-up"></i>
                            <span x-text="club.athletes
                                ? @js(__('events.club_athletes')).replace(':n', club.athletes)
                                : @js(__('events.club_athletes_none'))"></span>
                        </span>
                    </p>

                    <p class="text-xs mt-1 flex items-center gap-2 flex-wrap">
                        <span x-show="club.temporary" x-cloak
                              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-muted text-[10px] font-bold uppercase text-muted-foreground"
                              style="letter-spacing:.06em;">
                            <i class="bi bi-clock-history"></i>{{ __('events.club_temporary') }}
                        </span>

                        <span x-show="club.promoted" x-cloak
                              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase text-green-700"
                              style="letter-spacing:.06em; background: rgba(5,150,105,.12);">
                            <i class="bi bi-patch-check-fill"></i>{{ __('events.club_promoted') }}
                        </span>

                        {{-- Checked a third time, in the browser. The value is
                             already canonical on the way in and on the way out;
                             this is what keeps the SINK itself safe whatever
                             reaches it. --}}
                        <a x-show="safeInstagram(club.instagram)" x-cloak
                           :href="safeInstagram(club.instagram)" target="_blank" rel="noopener noreferrer"
                           class="inline-flex items-center gap-1 text-muted-foreground no-underline">
                            <i class="bi bi-instagram"></i><span x-text="handle(club.instagram)"></span>
                        </a>
                    </p>
                </div>

                <i class="bi bi-chevron-right text-muted-foreground/50 text-xs flex-shrink-0"></i>

                @if($canManage)
                    {{-- Only a club written down HERE can be edited: an invited
                         club's name and crest are its own record. --}}
                    <button type="button" x-show="club.temporary" x-cloak @click.stop="openEdit(club)" :disabled="busy"
                            :aria-label="labels.edit" :title="labels.edit"
                            class="m-press w-9 h-9 rounded-xl grid place-items-center flex-shrink-0 text-muted-foreground hover:text-foreground transition-colors">
                        <i class="bi bi-pencil"></i>
                    </button>

                    <button type="button" @click.stop="remove(club)" :disabled="busy"
                            aria-label="{{ __('shared.delete') }}" title="{{ __('shared.delete') }}"
                            class="m-press w-9 h-9 rounded-xl grid place-items-center flex-shrink-0 text-muted-foreground hover:text-red-600 transition-colors">
                        <i class="bi bi-trash3"></i>
                    </button>
                @endif
            </div>
        </template>

        {{-- Nothing on the list at all. --}}
        <div x-show="!clubs.length" x-cloak class="bg-white rounded-2xl shadow-sm border border-gray-100">
            @include('entry.public.partials.empty', [
                'ev' => $ev, 'icon' => 'bi-buildings',
                'title' => __('events.clubs_empty'),
            ])
        </div>

        {{-- Typing found nothing — a different answer from an empty list. --}}
        <div x-show="clubs.length && !visible.length" x-cloak class="bg-white rounded-2xl shadow-sm border border-gray-100">
            @include('entry.public.partials.empty', [
                'ev' => $ev, 'icon' => 'bi-search',
                'title' => __('personal.event_people_no_matches'),
            ])
        </div>
    </div>

    @if($canManage)
        {{-- The rule, written where the clubs are. Promotion itself is a
             deliberate act: the organiser's button on this screen (the
             `clubs-promote` window event), or `php artisan events:promote-clubs`
             by hand. Nothing sweeps on a timer — a job creating clubs on live
             data unattended is not switched on by accident. --}}
        <div x-show="clubs.some(c => c.temporary)" x-cloak class="mt-2 px-1">
            <p class="text-[11.5px] text-muted-foreground flex items-start gap-1.5">
                <i class="bi bi-info-circle-fill mt-0.5 flex-shrink-0"></i>
                <span>{{ __('events.club_temporary_hint') }}</span>
            </p>
        </div>

    {{-- ===== The sheet =====
         Teleported to <body>: the mobile shell leaves a transform on its
         children, and a fixed element inside one resolves against the wrapper
         instead of the viewport. --}}
    <template x-teleport="body">
        <div x-show="sheet" x-cloak class="fixed inset-0 z-[70]">
            <div x-show="sheet" x-transition.opacity @click="close()" class="absolute inset-0 bg-black/50"></div>

            <div x-show="sheet"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="translate-y-full sm:translate-y-4 sm:opacity-0"
                 x-transition:enter-end="translate-y-0 sm:opacity-100"
                 class="absolute inset-x-0 bottom-0 sm:inset-0 sm:m-auto sm:h-fit sm:max-w-lg
                        max-h-[92vh] flex flex-col bg-background rounded-t-3xl sm:rounded-3xl overflow-hidden shadow-2xl">

                {{-- The band: every sheet on the platform opens with one. --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: {{ $band }};">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-people-fill text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight"
                                x-text="editing ? labels.edit : labels.add"></h3>
                            <p class="text-[12px] text-white/85 mt-0.5"
                               x-text="editing ? editing.name : @js(__('events.clubs_sheet_hint'))"></p>
                        </div>
                        <button type="button" @click="close()" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                {{-- The body scrolls; the footer never does. --}}
                <div class="flex-1 overflow-y-auto px-5 py-4">

                    {{-- Which question is this? Selection cards, not a dropdown:
                         two known options, and a pop-over inside a scrolling
                         sheet would be clipped anyway. --}}
                    <div class="grid grid-cols-2 gap-2" x-show="!editing" x-cloak>
                        <template x-for="opt in modes" :key="opt.key">
                            <button type="button" @click="mode = opt.key"
                                    class="m-press rounded-2xl border p-3 text-start transition-colors"
                                    :class="mode === opt.key ? 'border-2' : 'border-gray-200 bg-white'"
                                    :style="mode === opt.key ? 'border-color: {{ $ev }}; background: {{ $tint }};' : ''">
                                <span class="w-9 h-9 rounded-xl grid place-items-center mb-2"
                                      :style="mode === opt.key ? 'color:#fff; background: {{ $ev }};' : 'color: {{ $ev }}; background: {{ $tint }};'">
                                    <i class="bi" :class="opt.icon"></i>
                                </span>
                                <span class="block text-[12.5px] font-black text-foreground leading-tight" x-text="opt.label"></span>
                            </button>
                        </template>
                    </div>

                    {{-- ---------- Already here ---------- --}}
                    <div x-show="mode === 'invite' && !editing" class="mt-4">
                        <label class="block text-[12px] font-bold text-foreground mb-1.5">{{ __('events.clubs_search_label') }}</label>
                        <div class="relative">
                            <i class="bi bi-search absolute start-3.5 top-1/2 -translate-y-1/2 text-muted-foreground text-sm"></i>
                            <input type="search" x-model="query" @input.debounce.300ms="find()"
                                   placeholder="{{ __('events.clubs_search_placeholder') }}"
                                   class="w-full h-12 ps-10 pe-4 rounded-2xl border border-gray-200 text-[15px] bg-white
                                          focus:ring-2 focus:border-transparent" style="--tw-ring-color: {{ $ev }};">
                        </div>

                        <div class="mt-3 space-y-2">
                            <template x-for="c in results" :key="c.slug">
                                <button type="button" @click="pick = c"
                                        class="m-press w-full rounded-2xl border p-3 flex items-center gap-3 text-start transition-colors"
                                        :class="pick && pick.slug === c.slug ? 'border-2' : 'border-gray-200 bg-white'"
                                        :style="pick && pick.slug === c.slug ? 'border-color: {{ $ev }}; background: {{ $tint }};' : ''">
                                    <span class="w-10 h-10 flex-shrink-0 grid place-items-center">
                                        <template x-if="c.logo">
                                            <img :src="c.logo" :alt="c.name" class="w-full h-full object-contain">
                                        </template>
                                        <template x-if="!c.logo">
                                            <span class="w-10 h-10 rounded-xl grid place-items-center"
                                                  style="color: {{ $ev }}; background: {{ $tint }};"><i class="bi bi-shield"></i></span>
                                        </template>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-[13px] font-black text-foreground truncate" x-text="c.name"></span>
                                        <span class="block text-[11px] text-muted-foreground" x-text="c.country || ''"></span>
                                    </span>
                                    <i class="bi flex-shrink-0"
                                       :class="pick && pick.slug === c.slug ? 'bi-check-circle-fill' : 'bi-circle'"
                                       :style="pick && pick.slug === c.slug ? 'color: {{ $ev }}' : 'color:#cbd5e1'"></i>
                                </button>
                            </template>

                            <p x-show="query.length >= 2 && !results.length && !searching" x-cloak
                               class="text-[12px] text-muted-foreground text-center py-4">{{ __('events.clubs_search_none') }}</p>
                        </div>

                        <p class="text-[11.5px] text-muted-foreground mt-3 flex items-start gap-1.5">
                            <i class="bi bi-info-circle-fill mt-0.5 flex-shrink-0"></i>
                            <span>{{ __('events.clubs_search_hint') }}</span>
                        </p>
                    </div>

                    {{-- ---------- Not on TAKEONE ---------- --}}
                    <div x-show="mode === 'new'" class="mt-4 space-y-4">
                        <div>
                            <label class="block text-[12px] font-bold text-foreground mb-1.5">{{ __('events.club_name_label') }}</label>
                            <input type="text" x-model="form.name" maxlength="120"
                                   placeholder="{{ __('events.club_name_placeholder') }}"
                                   class="w-full h-12 px-4 rounded-2xl border border-gray-200 text-[15px] bg-white
                                          focus:ring-2 focus:border-transparent" style="--tw-ring-color: {{ $ev }};">
                        </div>

                        <div>
                            <label class="block text-[12px] font-bold text-foreground mb-1.5">{{ __('events.club_logo_label') }}</label>

                            {{-- Square or circle. A crest is one or the other —
                                 a shield fills a square, a roundel needs the
                                 corners gone — and choosing after the fact means
                                 re-uploading, so it is asked before the crop. --}}
                            <div class="grid grid-cols-2 gap-2 mb-2">
                                <template x-for="opt in logoShapes" :key="opt.key">
                                    <button type="button" @click="logoShape = opt.key"
                                            class="m-press rounded-xl border p-2 flex items-center justify-center gap-2 transition-colors"
                                            :class="logoShape === opt.key ? 'border-2' : 'border-gray-200 bg-white'"
                                            :style="logoShape === opt.key ? 'border-color: {{ $ev }}; background: {{ $tint }};' : ''">
                                        <span class="w-5 h-5 border-2 border-current"
                                              :class="opt.key === 'circle' ? 'rounded-full' : 'rounded-[3px]'"
                                              :style="'color: ' + (logoShape === opt.key ? '{{ $ev }}' : '#cbd5e1')"></span>
                                        <span class="text-[12px] font-bold text-foreground" x-text="opt.label"></span>
                                    </button>
                                </template>
                            </div>

                            <div class="rounded-2xl border border-gray-200 bg-white p-3 flex items-center gap-3">
                                {{-- The crest as it will be seen: on nothing, in
                                     the shape that was chosen. --}}
                                <span class="w-16 h-16 flex-shrink-0 grid place-items-center overflow-hidden"
                                      :class="logoShape === 'circle' ? 'rounded-full' : 'rounded-xl'"
                                      :style="form.logo ? '' : 'background: {{ $tint }};'">
                                    <template x-if="form.logo">
                                        <img :src="form.logo" alt="" class="w-full h-full object-contain">
                                    </template>
                                    <template x-if="!form.logo">
                                        <i class="bi bi-image text-xl" style="color: {{ $ev }};"></i>
                                    </template>
                                </span>

                                <div class="min-w-0 flex-1">
                                    <button type="button" @click="pickLogo()"
                                            class="m-press inline-flex items-center gap-2 h-10 px-4 rounded-xl text-white text-[12.5px] font-bold"
                                            style="background: {{ $ev }};">
                                        <i class="bi bi-crop"></i>
                                        <span x-text="form.logo
                                            ? @js(__('events.club_logo_change'))
                                            : @js(__('events.club_logo_pick'))"></span>
                                    </button>
                                    <p class="text-[11px] text-muted-foreground mt-1.5 leading-relaxed">{{ __('events.club_logo_hint') }}</p>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="flex items-center gap-2 text-[12px] font-bold text-foreground mb-1.5">
                                {{ __('events.club_instagram_label') }}
                                <span class="px-2 py-0.5 rounded-full bg-muted text-[10px] font-bold text-muted-foreground uppercase" style="letter-spacing:.08em;">
                                    {{ __('events.club_instagram_optional') }}
                                </span>
                            </label>
                            <div class="relative">
                                <i class="bi bi-instagram absolute start-3.5 top-1/2 -translate-y-1/2 text-muted-foreground text-sm"></i>
                                <input type="text" x-model="form.instagram" maxlength="200" dir="ltr"
                                       placeholder="{{ __('events.club_instagram_placeholder') }}"
                                       class="w-full h-12 ps-10 pe-4 rounded-2xl border border-gray-200 text-[15px] bg-white
                                              focus:ring-2 focus:border-transparent" style="--tw-ring-color: {{ $ev }};">
                            </div>
                            <p class="text-[11.5px] text-muted-foreground mt-2 flex items-start gap-1.5">
                                <i class="bi bi-info-circle-fill mt-0.5 flex-shrink-0"></i>
                                <span>{{ __('events.club_instagram_hint') }}</span>
                            </p>
                        </div>
                    </div>
                </div>

                {{-- The footer stays reachable, and clears the home indicator. --}}
                <div class="flex-shrink-0 px-5 pt-3 border-t border-gray-100 bg-background"
                     style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="submit()" :disabled="busy || !ready"
                            class="m-press w-full h-12 rounded-2xl font-black text-[14px] text-white inline-flex items-center justify-center gap-2"
                            :class="(busy || !ready) && 'opacity-50'"
                            style="background: {{ $ev }};">
                        {{-- No paper plane: nothing is sent. Looking a club up
                             ADDS it to the list, exactly as typing one in does
                             (the invitation flow was switched off 2026-09-06). --}}
                        <i class="bi bi-check-lg"></i>
                        <span x-text="editing
                            ? labels.save
                            : (mode === 'invite'
                                ? @js(__('events.club_add'))
                                : @js(__('events.club_add_cta')))"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- ===== Who did this club bring? =====
         Its own sheet, because it is its own question and answering it decides
         whether the club outlives the event. Teleported for the same reason the
         other one is. ===== --}}
    <template x-teleport="body">
        <div x-show="athletesFor" x-cloak class="fixed inset-0 z-[70]">
            <div x-show="athletesFor" x-transition.opacity @click="athletesFor = null" class="absolute inset-0 bg-black/50"></div>

            <div x-show="athletesFor"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="translate-y-full sm:translate-y-4 sm:opacity-0"
                 x-transition:enter-end="translate-y-0 sm:opacity-100"
                 class="absolute inset-x-0 bottom-0 sm:inset-0 sm:m-auto sm:h-fit sm:max-w-lg
                        max-h-[92vh] flex flex-col bg-background rounded-t-3xl sm:rounded-3xl overflow-hidden shadow-2xl">

                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: {{ $band }};">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-people-fill text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('events.club_athletes_title') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5" x-text="athletesFor ? athletesFor.name : ''"></p>
                        </div>
                        <button type="button" @click="athletesFor = null" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <div class="relative mt-3 flex flex-wrap gap-1.5">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/20 text-[11px] font-bold">
                            <i class="bi bi-check2-square"></i><span x-text="chosen.length"></span>
                        </span>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-4">
                    <p class="text-[11.5px] text-muted-foreground mb-3">{{ __('events.club_athletes_hint') }}</p>

                    <div class="relative mb-3">
                        <i class="bi bi-search absolute start-3.5 top-1/2 -translate-y-1/2 text-muted-foreground text-sm"></i>
                        <input type="search" x-model="athleteQuery"
                               placeholder="{{ __('events.club_athletes_search') }}"
                               class="w-full h-11 ps-10 pe-4 rounded-2xl border border-gray-200 text-[14px] bg-white
                                      focus:ring-2 focus:border-transparent" style="--tw-ring-color: {{ $ev }};">
                    </div>

                    <div class="space-y-1.5">
                        <template x-for="a in visibleEntrants" :key="a.id">
                            <button type="button" @click="toggle(a.id)"
                                    class="m-press w-full rounded-2xl border p-3 flex items-center gap-3 text-start transition-colors"
                                    :class="chosen.includes(a.id) ? 'border-2' : 'border-gray-200 bg-white'"
                                    :style="chosen.includes(a.id) ? 'border-color: {{ $ev }}; background: {{ $tint }};' : ''">
                                <span class="w-6 h-6 rounded-md grid place-items-center flex-shrink-0 border"
                                      :style="chosen.includes(a.id)
                                          ? 'background: {{ $ev }}; border-color: {{ $ev }}; color:#fff'
                                          : 'border-color:#cbd5e1; color:transparent'">
                                    <i class="bi bi-check-lg text-sm"></i>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-[13px] font-black text-foreground truncate" x-text="a.name"></span>
                                    {{-- Somebody else's athlete, said plainly
                                         rather than silently stolen on save. --}}
                                    <span x-show="a.club && athletesFor && a.club !== athletesFor.uuid" x-cloak
                                          class="block text-[11px] text-amber-600" x-text="takenLabel(a)"></span>
                                    <span x-show="!a.club && a.real_club" x-cloak
                                          class="block text-[11px] text-muted-foreground" x-text="a.real_club"></span>
                                </span>
                            </button>
                        </template>

                        <p x-show="!visibleEntrants.length" x-cloak
                           class="text-[12px] text-muted-foreground text-center py-6">—</p>
                    </div>
                </div>

                <div class="flex-shrink-0 px-5 pt-3 border-t border-gray-100 bg-background"
                     style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="saveAthletes()" :disabled="busy"
                            class="m-press w-full h-12 rounded-2xl font-black text-[14px] text-white inline-flex items-center justify-center gap-2"
                            :class="busy && 'opacity-50'" style="background: {{ $ev }};">
                        <i class="bi bi-check-lg"></i>{{ __('events.club_athletes_save') }}
                    </button>
                </div>
            </div>
        </div>
    </template>

    {{-- ===== The crop editors =====
         Off-screen and OUTSIDE the sheet, deliberately.

         The sheet animates with a transform, and a CSS transform makes an
         element the containing block for any `position: fixed` descendant — so
         the cropper's own full-screen editor, rendered inside it, would be
         confined to the sheet instead of covering the viewport (CLAUDE.md,
         "Teleport fixed overlays to body"). Parked OFF THE TOP rather
         than `display:none`, because a hidden ancestor would take the editor
         with it.

         ⚠️ Off the TOP, never off the SIDE. It sat at `left:-9999px`, which is
         invisible in an LTR page and a BLANK PAGE in an RTL one: overflow to
         the left is unreachable in LTR, but RTL anchors the scroll origin at
         the right edge, so those 9999px became real scrollable width — a
         10,373px-wide document on a 390px phone — and an Arabic reader opened
         this screen already scrolled onto nine thousand pixels of empty white.
         Overflow ABOVE the viewport is unreachable whichever way the page
         runs, so `top:-9999px` is the direction-safe park.

         Two instances because the crop SHAPE is fixed when the widget renders.
         The one matching the chosen shape is the one whose file input is
         opened. Both are the shared `<x-takeone-cropper>` in inline mode — the
         one cropper this project has (CLAUDE.md, "One Cropper Everywhere"), in
         `form` mode so the cropped bytes come back on `cropperCropped` instead
         of being uploaded somewhere this form does not control.

         A transparent PNG survives: the widget encodes to PNG when the source
         has an alpha channel and only falls back to JPEG when it does not, so a
         crest keeps its transparent ground (Design Rule #5). --}}
    <div aria-hidden="true"
         style="position:fixed; top:-9999px; left:0; width:1px; height:1px; overflow:hidden;">
        <x-takeone-cropper id="clubLogoSquare" mode="form" :inline="true"
                           :width="512" :height="512" shape="rectangle" :canvasHeight="300"
                           folder="events" filename="club-logo" inputName="club_logo_square"
                           sheetMaxWidth="100%" sheetClass="rounded-t-3xl shadow-2xl bg-background"
                           :showControls="false" :showCancel="false"
                           :uploadAsIs="true"
                           saveText="{{ __('events.club_logo_crop') }}"
                           uploadAsIsText="{{ __('events.club_logo_as_is') }}" />

        <x-takeone-cropper id="clubLogoCircle" mode="form" :inline="true"
                           :width="512" :height="512" shape="circle" :canvasHeight="300"
                           folder="events" filename="club-logo" inputName="club_logo_circle"
                           sheetMaxWidth="100%" sheetClass="rounded-t-3xl shadow-2xl bg-background"
                           :showControls="false" :showCancel="false"
                           :uploadAsIs="true"
                           saveText="{{ __('events.club_logo_crop') }}"
                           uploadAsIsText="{{ __('events.club_logo_as_is') }}" />
    </div>
    @endif
</div>

@once
@push('scripts')
<script>
/*
 * Registered, not inline-scoped: this component can be teleported and can be
 * swapped in by the shell navigator, and a scope defined by a bare <script>
 * inside a <template x-teleport> never runs at all.
 */
(() => {
    const define = () => {
        if (window.Alpine.__eventClubs) return;
        window.Alpine.__eventClubs = true;

        window.Alpine.data('eventClubs', () => ({
            clubs: [],
            sheet: false,
            mode: 'invite',
            busy: false,
            searching: false,
            query: '',
            results: [],
            pick: null,
            form: { name: '', logo: null, instagram: '' },
            logoShape: 'square',
            editing: null,
            athletesFor: null,
            entrants: [],
            chosen: [],
            athleteQuery: '',
            q: '',
            urls: {},
            labels: {},

            init() {
                this.urls = JSON.parse(this.$el.dataset.urls || '{}');
                this.labels = JSON.parse(this.$el.dataset.labels || '{}');
                this.clubs = JSON.parse(this.$el.dataset.clubs || '[]');

                /* The cropper announces a finished crop on `document`, so the
                   listener is de-duplicated: the mobile shell re-runs inline
                   scripts on every AJAX navigation, and without this they stack
                   up (CLAUDE.md, Realtime §6). */
                if (window.__clubLogoCropped) {
                    document.removeEventListener('cropperCropped', window.__clubLogoCropped);
                }

                window.__clubLogoCropped = (e) => {
                    const id = e.detail && e.detail.id;
                    if (id !== 'clubLogoSquare' && id !== 'clubLogoCircle') return;

                    this.form.logo = e.detail.base64;
                    this.logoShape = id === 'clubLogoCircle' ? 'circle' : 'square';
                };

                document.addEventListener('cropperCropped', window.__clubLogoCropped);
            },

            /* Client-side, because every club is already on the page: typing
               narrows it with no round trip. */
            get visible() {
                const s = (this.q || '').trim().toLowerCase();
                return s ? this.clubs.filter(c => (c.name || '').toLowerCase().includes(s)) : this.clubs;
            },

            get logoShapes() {
                return [
                    { key: 'square', label: this.labels.shape_square },
                    { key: 'circle', label: this.labels.shape_circle },
                ];
            },

            /* Open the crop editor for the chosen shape. The widget owns the
               picker, the editor and the encoding; this only says which of the
               two to open. */
            pickLogo() {
                const input = document.getElementById(
                    this.logoShape === 'circle' ? 'input_clubLogoCircle' : 'input_clubLogoSquare'
                );
                if (input) { input.value = ''; input.click(); }
            },

            get modes() {
                return [
                    { key: 'invite', icon: 'bi-search',      label: this.labels.tab_invite },
                    { key: 'new',    icon: 'bi-pencil-fill', label: this.labels.tab_new },
                ];
            },

            /* The footer says no before the server has to. */
            get ready() {
                if (this.editing) {
                    // A crest is already on file, so only the name is demanded.
                    return (this.form.name || '').trim().length >= 2;
                }

                return this.mode === 'invite'
                    ? !!this.pick
                    : ((this.form.name || '').trim().length >= 2 && !!this.form.logo);
            },

            stateLabel(s) { return this.labels['state_' + s] || s; },
            stateIcon(s)  {
                /* `invited` and `declined` are kept in the map, not dropped:
                   rows written before the invitation flow was switched off are
                   still in the table and must still draw something honest. */
                return { listed: 'bi-pencil-fill', invited: 'bi-hourglass-split',
                         accepted: 'bi-check-circle-fill', declined: 'bi-x-circle-fill' }[s] || 'bi-circle';
            },
            stateClass(s) {
                return { accepted: 'text-green-600', declined: 'text-red-500',
                         invited: 'text-amber-600' }[s] || 'text-muted-foreground';
            },
            /* An https instagram.com page, or nothing at all. Never a bare
               string handed straight to an href. */
            safeInstagram(url) {
                if (!url) return '';
                try {
                    const u = new URL(url);
                    const ok = u.protocol === 'https:'
                        && (u.hostname === 'instagram.com' || u.hostname === 'www.instagram.com');
                    return ok ? u.href : '';
                } catch (e) {
                    return '';
                }
            },

            handle(url) {
                const safe = this.safeInstagram(url);
                return safe ? '@' + safe.split('/').filter(Boolean).pop() : '';
            },

            open()  { this.editing = null; this.sheet = true; },

            /* The same sheet, opened on a club that already exists. Only the
               written-down kind gets here — the card offers no pencil on an
               invited club, and the endpoint refuses one anyway. */
            openEdit(club) {
                this.editing = club;
                this.mode = 'new';
                this.form = {
                    name: club.name || '',
                    // The crest as it stands. A URL, not a data URI: it is here
                    // to be LOOKED at, and only a fresh crop (which is always a
                    // data URI) is sent as a replacement.
                    logo: club.logo || null,
                    instagram: club.instagram ? '@' + String(club.instagram).split('/').filter(Boolean).pop() : '',
                };
                this.sheet = true;
            },

            /* Who this club brought. The start list is fetched once and reused,
               because it is the same list for every club on the event. */
            async openAthletes(club) {
                this.athletesFor = club;
                this.athleteQuery = '';

                if (!this.entrants.length) {
                    try {
                        const res = await fetch(this.urls.entrants, {
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin',
                        });
                        const data = await res.json();
                        this.entrants = data.entrants || [];
                    } catch (e) {
                        this.entrants = [];
                    }
                }

                this.chosen = this.entrants.filter(a => a.club === club.uuid).map(a => a.id);
            },

            get visibleEntrants() {
                const q = (this.athleteQuery || '').trim().toLowerCase();
                return q ? this.entrants.filter(a => (a.name || '').toLowerCase().includes(q)) : this.entrants;
            },

            takenLabel(a) {
                const other = this.clubs.find(c => c.uuid === a.club);
                return (this.labels.athletes_taken || '').replace(':club', other ? other.name : '');
            },

            toggle(id) {
                const i = this.chosen.indexOf(id);
                i === -1 ? this.chosen.push(id) : this.chosen.splice(i, 1);
            },

            async saveAthletes() {
                const club = this.athletesFor;
                if (!club || this.busy) return;

                const ok = await this.post(
                    this.urls.athletes.replace('__UUID__', club.uuid),
                    { entrants: this.chosen }
                );

                // The local copy of the start list has to agree with what was
                // just saved, or reopening another club would show a stale tick.
                this.entrants = this.entrants.map(a => ({
                    ...a,
                    club: this.chosen.includes(a.id) ? club.uuid : (a.club === club.uuid ? null : a.club),
                }));

                this.athletesFor = null;
            },

            async promote() {
                const ok = await window.confirmAction({
                    title: this.labels.promote_confirm || 'Register the clubs that competed?',
                    confirmText: this.labels.promote || 'Register',
                });
                if (!ok) return;

                return this.post(this.urls.promote, {});
            },
            close() {
                this.sheet = false;
                this.editing = null;
                this.query = ''; this.results = []; this.pick = null;
                this.form = { name: '', logo: null, instagram: '' };
            },

            async find() {
                const q = (this.query || '').trim();
                if (q.length < 2) { this.results = []; return; }

                this.searching = true;
                try {
                    const res = await fetch(this.urls.search + '?q=' + encodeURIComponent(q), {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin',
                    });
                    const data = await res.json();
                    this.results = data.clubs || [];
                } catch (e) {
                    this.results = [];
                } finally {
                    this.searching = false;
                }
            },

            submit() {
                if (this.busy || !this.ready) return;

                if (this.editing) {
                    return this.post(this.urls.update.replace('__UUID__', this.editing.uuid), {
                        name: this.form.name.trim(),
                        // Only a fresh crop travels. An untouched crest is a
                        // URL, and sending it back would be asking the server to
                        // store a picture of itself.
                        logo: (this.form.logo || '').startsWith('data:') ? this.form.logo : null,
                        instagram: (this.form.instagram || '').trim() || null,
                    }, 'PUT');
                }

                return this.mode === 'invite'
                    ? this.post(this.urls.invite, { club: this.pick.slug })
                    : this.post(this.urls.store, {
                          name: this.form.name.trim(),
                          logo: this.form.logo,
                          instagram: (this.form.instagram || '').trim() || null,
                      });
            },

            async post(url, body, method = 'POST') {
                this.busy = true;
                try {
                    const res = await fetch(url, {
                        method,
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: body ? JSON.stringify(body) : undefined,
                    });
                    const data = await res.json();

                    if (!res.ok || !data.success) {
                        window.showToast('error', data.message || 'That did not work.');
                        return;
                    }

                    if (data.clubs) this.clubs = data.clubs;
                    window.showToast('success', data.message);
                    this.close();
                } catch (e) {
                    window.showToast('error', 'That did not work.');
                } finally {
                    this.busy = false;
                }
            },

            async remove(club) {
                /* The dialog says what happens to the PEOPLE, because that is
                   the question — an organiser striking a club off is not trying
                   to withdraw four athletes who have paid. */
                const ok = await window.confirmAction({
                    title: (this.labels.remove_confirm || '').replace(':name', club.name),
                    message: club.athletes
                        ? (this.labels.remove_body_n || '').replace(':n', club.athletes)
                        : this.labels.remove_body,
                    type: 'danger',
                    confirmText: this.labels.remove || 'Remove',
                });
                if (!ok) return;

                return this.post(this.urls.destroy.replace('__UUID__', club.uuid), null, 'DELETE');
            },
        }));
    };

    window.Alpine ? define() : document.addEventListener('alpine:init', define);
})();
</script>
@endpush
@endonce
