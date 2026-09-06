{{--
    Entering when you ALREADY have an account.

    The other branch of the first question. One card instead of three steps,
    because the three steps exist to BUILD a profile and this person has one:
    their name, birthdate and gender are read from it and never re-asked. What
    is left is what belongs to the entry rather than to the person — the weight
    and the belt — and both stay optional, like everywhere else.

    It is the same door as the guest flow, not a shortcut past it: the request
    lands in the organiser's one queue as an EventPublicEntry, and
    App\Events\Support\PublicEntry::enrolExisting() takes every decision.

    Expects $e, $me, $alreadyIn, $alreadyAsked, $belts, $ev, plus the fee
    variables entry/public/enrol.blade.php resolves before including this
    ($feeHasOptions, $feeOptions, $feeCurrency, $feeLateActive, $feeLateAmount,
    $feePrices, $feeBase, $feeCurrencyLabel). All of them are optional here:
    an event that sells nothing draws the card it always drew.
--}}
<div x-data="enrolMine()" class="-mx-4 -mt-4">

    {{-- ===== The band — the design's, minus the step count and the segments:
         there is only one screen here, so a progress bar would be measuring
         nothing. ===== --}}
    <header class="relative overflow-hidden text-white"
            style="padding: 22px 24px 26px; background: {{ \App\Support\Palette::eventBand($e['color']) }};">
        <div class="absolute rounded-full" style="right:-56px; top:-56px; width:190px; height:190px; background:rgba(255,255,255,.07);"></div>

        <div class="flex items-center justify-between gap-3 relative z-10">
            <a href="{{ route('events.public', ['event' => $e['key']]) }}"
               class="m-press inline-flex items-center w-10 h-10 justify-center rounded-full bg-white/15 border border-white/25 backdrop-blur text-white text-sm font-semibold no-underline"
           aria-label="{{ __('events.public_enrol_back_event') }}" title="{{ __('events.public_enrol_back_event') }}">
                <i class="bi bi-chevron-left"></i>
            </a>
        </div>

        <div class="relative z-10" style="margin-top:22px;">
            <span class="flex items-center" style="gap:10px;">
                <span class="flex-none" style="width:38px; height:3px; border-radius:2px; background:rgba(255,255,255,.85);"></span>
                <span class="uppercase" style="font-size:11px; font-weight:600; letter-spacing:.2em; color:rgba(255,255,255,.85);">{{ __('events.public_enrol_step_entry') }}</span>
            </span>

            <h1 style="margin:12px 0 0; font-size:23px; line-height:1.2; font-weight:700; letter-spacing:-.01em;">{{ $e['title'] }}</h1>

            @if($e['club'])
                <p class="flex items-center" style="margin:9px 0 0; gap:8px; font-size:13px; color:rgba(255,255,255,.82);">
                    <i class="bi bi-building"></i>{{ $e['club'] }}
                </p>
            @endif
        </div>
    </header>

    <div class="mx-auto w-full max-w-lg px-4 -mt-3 relative z-10">
        <div class="pb-[max(7rem,calc(6rem+env(safe-area-inset-bottom)))]">

        {{-- ===== Already settled — say so, and offer the way back =====
             Both of these are dead ends for a form, so the page does not draw
             one (Navigation Integrity). --}}
        @if($alreadyIn || $alreadyAsked)
            <div style="background:#fff; border-radius:18px; border-top:3px solid {{ $ev }};
                        box-shadow:0 22px 60px rgba(30,44,79,.13), 0 2px 6px rgba(30,44,79,.06);
                        padding:30px 22px; text-align:center;">
                <span class="w-16 h-16 mx-auto rounded-full grid place-items-center text-2xl"
                      style="color: {{ $ev }}; background: {{ \App\Support\Palette::alpha($ev, .1) }};">
                    <i class="bi {{ $alreadyIn ? 'bi-check-lg' : 'bi-hourglass-split' }}"></i>
                </span>
                <h2 class="text-[20px] font-black leading-tight text-foreground mt-4">
                    {{ $alreadyIn ? __('events.public_enrol_already_in_title') : __('events.public_enrol_already_asked_title') }}
                </h2>
                <p class="text-[13px] text-muted-foreground mt-1.5">
                    {{ $alreadyIn ? __('events.public_enrol_already_in') : __('events.public_enrol_already_pending') }}
                </p>

                {{-- The dead end this card used to be: it said "you are already
                     in" and offered only the way back, while the screen that
                     lets you CHANGE what you entered sat one URL away with
                     nothing linking to it. An entry you cannot open is an entry
                     you cannot correct.

                     Only when they are actually IN: `my-entry` is the entrant's
                     own panel, and a request still waiting on the organiser has
                     no entry to open yet. --}}
                @if($alreadyIn)
                    <a href="{{ route('events.public.my-entry', ['event' => $e['key']]) }}"
                       class="m-press mt-6 inline-flex items-center justify-center gap-2 h-12 px-6 rounded-2xl font-black text-[14px] text-white no-underline"
                       style="background: {{ $ev }}; box-shadow: 0 18px 40px -18px {{ $ev }};">
                        {{ __('events.my_entry_open') }}<i class="bi bi-arrow-right"></i>
                    </a>
                @endif

                {{-- The way back. It is DEMOTED to an outline only when the
                     button above it exists — a card with one action keeps the
                     filled button it has always had, so the pending state looks
                     exactly as it did before this link was added. --}}
                @if($alreadyIn)
                    <a href="{{ route('events.public', ['event' => $e['key']]) }}"
                       class="m-press mt-3 inline-flex items-center justify-center gap-2 h-12 px-6 rounded-2xl font-bold text-[14px] no-underline border"
                       style="color: {{ $ev }}; border-color: {{ \App\Support\Palette::alpha($ev, .35) }};">
                        {{ __('events.public_enrol_back_to_event') }}<i class="bi bi-arrow-right"></i>
                    </a>
                @else
                    <a href="{{ route('events.public', ['event' => $e['key']]) }}"
                       class="m-press mt-6 inline-flex items-center justify-center gap-2 h-12 px-6 rounded-2xl font-black text-[14px] text-white no-underline"
                       style="background: {{ $ev }}; box-shadow: 0 18px 40px -18px {{ $ev }};">
                        {{ __('events.public_enrol_back_to_event') }}<i class="bi bi-arrow-right"></i>
                    </a>
                @endif
            </div>

        {{-- ===== The confirmation ===== --}}
        @else
            <div x-show="!done" class="e-inR">
                <div style="background:#fff; border-radius:18px; border-top:3px solid {{ $ev }};
                            box-shadow:0 22px 60px rgba(30,44,79,.13), 0 2px 6px rgba(30,44,79,.06);
                            padding:26px 22px;">
                    <h2 class="text-[22px] font-black leading-tight text-foreground">{{ __('events.public_enrol_mine_title') }}</h2>
                    <p class="text-[13px] text-muted-foreground mt-1">{{ __('events.public_enrol_mine_hint') }}</p>

                    {{-- Who is being entered. Shown rather than asked, because
                         it is already known — and shown because an entry made
                         under the wrong account is the one mistake this screen
                         can still make. A profile picture is portrait 3:4
                         (CLAUDE.md), and falls back to the gendered avatar. --}}
                    <div class="flex items-center gap-3 mt-5 p-3 rounded-2xl bg-muted/40">
                        <span class="w-9 h-12 rounded-xl overflow-hidden flex-shrink-0 bg-white">
                            @if($me['photo'])
                                <img src="{{ $me['photo'] }}" alt="" class="w-full h-full object-cover">
                            @else
                                <x-gender-avatar :gender="$me['gender']" class="w-full h-full" :bg="$ev" />
                            @endif
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[10px] font-bold uppercase tracking-[0.14em] text-muted-foreground">{{ __('events.public_enrol_mine_as') }}</span>
                            <span class="block text-[14px] font-black text-foreground truncate">{{ $me['name'] }}</span>
                            {{-- An account can have no email at all since
                                 2026-09-04 (the telephone number is the
                                 identifier), so this line is only drawn when
                                 there is one — an empty grey line under a name
                                 reads as something missing. --}}
                            @if($me['email'])
                                <span class="block text-[11px] text-muted-foreground truncate">{{ $me['email'] }}</span>
                            @endif
                        </span>
                    </div>

                    <div class="mobile-stagger space-y-4 mt-5">
                        {{-- Weight — the same control the guest flow uses, and
                             the same rule: never demanded, and the scale on the
                             day is the final word. --}}
                        <div class="m-card rounded-2xl p-4">
                            <div class="flex items-center justify-between">
                                <label class="text-[12px] font-bold text-foreground">{{ __('events.claim_weight') }}</label>
                                <button type="button" @click="weight = null" x-show="weight"
                                        class="m-press text-[11px] font-bold text-muted-foreground">{{ __('shared.clear') }}</button>
                            </div>
                            <div class="flex items-center justify-center gap-5 mt-2">
                                <button type="button" @click="bump(-0.5)"
                                        class="m-press w-11 h-11 rounded-full bg-muted border border-gray-200 grid place-items-center text-lg text-foreground">
                                    <i class="bi bi-dash-lg"></i>
                                </button>
                                <div class="text-center min-w-[7rem]">
                                    <span class="text-[40px] font-black leading-none tabular-nums text-foreground"
                                          x-text="weight ? weight.toFixed(1) : '—'"></span>
                                    <span class="text-[13px] font-bold text-muted-foreground ms-1">kg</span>
                                </div>
                                <button type="button" @click="bump(0.5)"
                                        class="m-press w-11 h-11 rounded-full bg-muted border border-gray-200 grid place-items-center text-lg text-foreground">
                                    <i class="bi bi-plus-lg"></i>
                                </button>
                            </div>
                            <input type="range" min="20" max="140" step="0.5" class="e-range w-full mt-3"
                                   :value="weight ?? 60" @input="weight = Number($event.target.value)">
                            <p x-show="!weight" class="text-[11.5px] text-muted-foreground mt-2 text-center">
                                {{ __('events.claim_weight_blank') }}
                            </p>
                        </div>

                        {{-- Belt — selection cards, never a dropdown inside a
                             scrolling body (Mobile Pattern Language). --}}
                        <div>
                            <label class="block text-[12px] font-bold text-foreground mb-2">{{ __('events.claim_belt') }}</label>
                            <div class="grid grid-cols-3 gap-2">
                                @foreach ($belts as $b)
                                    <button type="button" @click="belt = '{{ $b['v'] }}'"
                                            class="m-press e-pick rounded-2xl py-3 flex flex-col items-center gap-1.5"
                                            :class="belt === '{{ $b['v'] }}' && 'is-on'">
                                        <span class="w-8 h-2.5 rounded-full border border-white/25" style="background: {{ $b['hex'] }}"></span>
                                        <span class="text-[11.5px] font-bold">{{ $b['label'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- The club they compete FOR. Asked even of somebody
                             whose profile is on file, because it belongs to the
                             ENTRY rather than to the person: an athlete
                             competes for one club this weekend and another next
                             year. Picking a real club fills
                             `representing_tenant_id`, which the draw and the
                             flag beside their name read. --}}
                        <div>
                            <label class="block text-[12px] font-bold text-foreground mb-2">{{ __('events.public_enrol_club') }}</label>

                            <template x-if="club.tenant || club.typed">
                                <div class="e-pick is-on rounded-2xl px-3 py-3 flex items-center gap-3">
                                    <template x-if="club.logo">
                                        <span class="w-9 h-9 flex-shrink-0">
                                            <img :src="club.logo" alt="" class="w-full h-full object-contain">
                                        </span>
                                    </template>
                                    <template x-if="!club.logo">
                                        <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0"
                                              style="color: {{ $ev }}; background: {{ \App\Support\Palette::alpha($ev, .1) }};">
                                            <i class="bi bi-building"></i>
                                        </span>
                                    </template>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-[13.5px] font-black text-foreground truncate" x-text="club.name"></span>
                                        <span class="block text-[11px] text-muted-foreground"
                                              x-text="club.tenant ? @js(__('events.public_enrol_club_on_platform')) : @js(__('events.public_enrol_club_typed'))"></span>
                                    </span>
                                    <span x-show="club.country" class="flex-shrink-0"
                                          :class="'fi fi-' + (club.country || '').toLowerCase()"
                                          style="width:20px; height:15px; border-radius:3px;"></span>
                                    <button type="button" @click="clearClub()" class="m-press flex-shrink-0 text-muted-foreground"
                                            aria-label="{{ __('shared.clear') }}"><i class="bi bi-x-lg text-xs"></i></button>
                                </div>
                            </template>

                            {{-- Typing it by hand: a club that is not on this
                                 platform gets its own field, not a repurposed
                                 search box. Committed on Done rather than while
                                 typing, so the card above does not swap in and
                                 out from under the keyboard. --}}
                            <template x-if="!club.tenant && !club.typed && clubManual">
                                <div>
                                    <input type="text" x-model="manualName" maxlength="120"
                                           x-ref="manualClub" @keydown.enter.prevent="commitClub()"
                                           placeholder="{{ __('events.public_enrol_club_manual_placeholder') }}"
                                           class="e-field w-full h-12 px-4 rounded-2xl text-[15px]">
                                    <p class="text-[11px] text-muted-foreground mt-1.5">{{ __('events.public_enrol_club_manual_hint') }}</p>

                                    <div class="grid grid-cols-2 gap-2 mt-2.5">
                                        <button type="button" @click="clubManual = false; manualName = ''"
                                                class="m-press e-pick rounded-2xl px-3 py-2.5 text-[11.5px] font-bold text-muted-foreground">
                                            {{ __('events.public_enrol_club_back_to_search') }}
                                        </button>
                                        <button type="button" @click="commitClub()"
                                                :disabled="manualName.trim().length < 2"
                                                :class="manualName.trim().length < 2 ? 'opacity-40' : ''"
                                                class="m-press rounded-2xl px-3 py-2.5 text-[11.5px] font-black text-white"
                                                style="background: {{ $ev }};">
                                            {{ __('events.public_enrol_club_manual_done') }}
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <template x-if="!club.tenant && !club.typed && !clubManual">
                                <div>
                                    <div class="relative">
                                        <input type="text" x-model="clubQuery" @input.debounce.300ms="searchClubs()"
                                               placeholder="{{ __('events.public_enrol_club_search') }}"
                                               class="e-field w-full h-12 ps-10 pe-4 rounded-2xl text-[15px]">
                                        <i class="bi bi-search absolute start-4 top-1/2 -translate-y-1/2 text-muted-foreground text-sm"></i>
                                    </div>

                                    <div x-show="clubResults.length" x-cloak class="mt-2 space-y-1.5">
                                        <template x-for="c in clubResults" :key="c.slug">
                                            <button type="button" @click="pickClub(c)"
                                                    class="m-press e-pick w-full rounded-2xl px-3 py-2.5 flex items-center gap-3 text-start">
                                                <template x-if="c.logo">
                                                    <span class="w-8 h-8 flex-shrink-0">
                                                        <img :src="c.logo" alt="" class="w-full h-full object-contain">
                                                    </span>
                                                </template>
                                                <template x-if="!c.logo">
                                                    <span class="w-8 h-8 rounded-lg grid place-items-center flex-shrink-0"
                                                          style="color: {{ $ev }}; background: {{ \App\Support\Palette::alpha($ev, .1) }};">
                                                        <i class="bi bi-building text-xs"></i>
                                                    </span>
                                                </template>
                                                <span class="min-w-0 flex-1 text-[13px] font-bold text-foreground truncate" x-text="c.name"></span>
                                                <span x-show="c.country" :class="'fi fi-' + (c.country || '').toLowerCase()"
                                                      class="flex-shrink-0" style="width:20px; height:15px; border-radius:3px;"></span>
                                            </button>
                                        </template>
                                    </div>

                                    <p x-show="!clubResults.length && !clubSearching" x-cloak
                                       class="text-[11.5px] text-muted-foreground mt-2">{{ __('events.public_enrol_club_none_found') }}</p>

                                    <div class="grid grid-cols-2 gap-2 mt-2.5">
                                        <button type="button" @click="typeClub()"
                                                class="m-press e-pick rounded-2xl px-3 py-2.5 text-[11.5px] font-bold text-foreground">
                                            {{ __('events.public_enrol_club_not_listed') }}
                                        </button>
                                        <button type="button" @click="clearClub(); club.none = true"
                                                class="m-press e-pick rounded-2xl px-3 py-2.5 text-[11.5px] font-bold text-foreground"
                                                :class="club.none && 'is-on'">
                                            {{ __('events.public_enrol_club_own') }}
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>

@if(($feeHasOptions ?? false) || ($feeLateActive ?? false))
                        {{-- ===== What they are entering =====
                             The guest form's card, on the member branch, so the
                             same door quotes the same price whichever way
                             somebody came through it. Selection cards for the
                             same reason as everywhere else on this page: a
                             popover inside a scrolling column is clipped by it.

                             UUIDs are all that is posted; storeMine() re-prices
                             from the event's own rows. --}}
                        <div class="m-card rounded-2xl p-4">
                            @if($feeHasOptions ?? false)
                                <label class="text-[12px] font-bold text-foreground">{{ __('events.fee_select_options') }}</label>

                                <div class="space-y-2 mt-2.5">
                                    @foreach($feeOptions as $opt)
                                        <button type="button" @click="toggleFee('{{ $opt->uuid }}')"
                                                :class="feeChosen.includes('{{ $opt->uuid }}') ? 'is-on' : ''"
                                                class="m-press e-pick w-full rounded-2xl px-3 py-3 flex items-center gap-3 text-start">
                                            <span class="w-5 h-5 rounded-md border-2 grid place-items-center flex-shrink-0"
                                                  :class="feeChosen.includes('{{ $opt->uuid }}') ? 'text-white' : 'border-gray-300'"
                                                  :style="feeChosen.includes('{{ $opt->uuid }}') ? 'background: {{ $ev }}; border-color: {{ $ev }}' : ''">
                                                <i class="bi bi-check text-[11px]" x-show="feeChosen.includes('{{ $opt->uuid }}')"></i>
                                            </span>
                                            <span class="min-w-0 flex-1 text-[13px] font-bold text-foreground">{{ $opt->label }}</span>
                                            <span class="text-[12px] font-black flex-shrink-0" style="color: {{ $ev }};">
                                                {{ \App\Events\Support\EventFee::display((float) $opt->amount, $feeCurrency) }}
                                            </span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            @if($feeLateActive ?? false)
                                <p class="text-[12px] text-muted-foreground leading-snug mt-3 flex items-start gap-2">
                                    <i class="bi bi-clock-history mt-0.5" style="color:#b45309;"></i>
                                    <span>{{ __('events.fee_late_applies', ['amount' => \App\Events\Support\EventFee::display($feeLateAmount, $feeCurrency)]) }}</span>
                                </p>
                            @endif

                            <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100">
                                <span class="text-[12px] font-bold text-muted-foreground">{{ __('events.fee_total') }}</span>
                                <span class="text-[15px] font-black text-foreground" x-text="feeTotalText"></span>
                            </div>
                        </div>
@endif

                        {{-- What happens next, said before the button is pressed. --}}
                        <div class="m-card rounded-2xl px-4 py-3 flex items-start gap-2.5">
                            <i class="bi bi-info-circle-fill mt-0.5" style="color: {{ $ev }}"></i>
                            <p class="text-[12px] text-muted-foreground leading-snug">
                                {{ __('events.public_enrol_review_note') }}
                                @if($e['fee_is_paid']) {{ __('events.public_entry') }}: <span class="font-bold text-foreground">{{ $e['fee'] }}</span>@endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===== Sent ===== --}}
            <div x-show="done" x-cloak class="text-center pt-6">
                <div class="mx-auto w-24 h-24 rounded-full grid place-items-center border-2"
                     style="border-color: {{ $ev }}; background: {{ \App\Support\Palette::alpha($ev, .12) }}">
                    <i class="bi text-4xl" style="color: {{ $ev }}" :class="accepted ? 'bi-check-lg' : 'bi-send-check-fill'"></i>
                </div>

                <h2 class="text-[24px] font-black mt-5 text-foreground"
                    x-text="accepted ? @js(__('events.public_enrol_done_title')) : @js(__('events.public_enrol_pending_title'))"></h2>
                <p class="text-[13.5px] text-muted-foreground mt-1.5 px-2"
                   x-text="accepted ? @js(__('events.public_enrol_done_body')) : @js(__('events.public_enrol_pending_body'))"></p>

                <div class="m-card rounded-2xl px-4 py-3.5 flex items-center gap-3 mt-6 text-start" x-show="accepted" x-cloak>
                    <i class="bi bi-diagram-3 bracket-icon text-lg" style="color: {{ $ev }}"></i>
                    <div class="min-w-0 flex-1">
                        <p class="text-[11px] uppercase tracking-wide text-muted-foreground font-bold">{{ __('events.public_enrol_your_division') }}</p>
                        <p class="text-[14px] font-black text-foreground" x-text="division || @js(__('events.claim_set_at_weigh_in'))"></p>
                    </div>
                </div>

                {{-- The dead end this card used to be: it said "you are already
                     in" and offered only the way back, while the screen that
                     lets you CHANGE what you entered sat one URL away with
                     nothing linking to it. An entry you cannot open is an entry
                     you cannot correct.

                     Only when they are actually IN: `my-entry` is the entrant's
                     own panel, and a request still waiting on the organiser has
                     no entry to open yet. --}}
                @if($alreadyIn)
                    <a href="{{ route('events.public.my-entry', ['event' => $e['key']]) }}"
                       class="m-press mt-6 inline-flex items-center justify-center gap-2 h-12 px-6 rounded-2xl font-black text-[14px] text-white no-underline"
                       style="background: {{ $ev }}; box-shadow: 0 18px 40px -18px {{ $ev }};">
                        {{ __('events.my_entry_open') }}<i class="bi bi-arrow-right"></i>
                    </a>
                @endif

                {{-- The way back. It is DEMOTED to an outline only when the
                     button above it exists — a card with one action keeps the
                     filled button it has always had, so the pending state looks
                     exactly as it did before this link was added. --}}
                @if($alreadyIn)
                    <a href="{{ route('events.public', ['event' => $e['key']]) }}"
                       class="m-press mt-3 inline-flex items-center justify-center gap-2 h-12 px-6 rounded-2xl font-bold text-[14px] no-underline border"
                       style="color: {{ $ev }}; border-color: {{ \App\Support\Palette::alpha($ev, .35) }};">
                        {{ __('events.public_enrol_back_to_event') }}<i class="bi bi-arrow-right"></i>
                    </a>
                @else
                    <a href="{{ route('events.public', ['event' => $e['key']]) }}"
                       class="m-press mt-6 inline-flex items-center justify-center gap-2 h-12 px-6 rounded-2xl font-black text-[14px] text-white no-underline"
                       style="background: {{ $ev }}; box-shadow: 0 18px 40px -18px {{ $ev }};">
                        {{ __('events.public_enrol_back_to_event') }}<i class="bi bi-arrow-right"></i>
                    </a>
                @endif
            </div>
        @endif
        </div>
    </div>

    {{-- ===== The action bar — reachable, safe-area padded ===== --}}
    @if(! $alreadyIn && ! $alreadyAsked)
        <div x-show="!done" class="ev-app-fixed fixed inset-x-0 bottom-0 z-30 px-5 pt-3 bg-white border-t border-gray-100"
             style="padding-bottom: calc(0.9rem + env(safe-area-inset-bottom));">
            <div class="mx-auto w-full max-w-lg">
                <button type="button" @click="submit()" :disabled="saving"
                        class="m-press w-full h-14 rounded-2xl font-black text-[15px] flex items-center justify-center gap-2 text-white"
                        :class="saving ? 'opacity-40' : ''"
                        style="background: {{ $ev }}; box-shadow: 0 18px 40px -18px {{ $ev }};">
                    <span x-text="saving ? '…' : @js(__('events.public_enrol_confirm_entry'))"></span>
                    <i class="bi bi-arrow-right" x-show="!saving"></i>
                </button>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
function enrolMine() {
    return {
        weight: null,
        belt: '',
        /* The club they compete for. `tenant` is a SLUG — never an id, which is
           not a public key — and `typed` is the fallback for a club that is not
           on this platform. Exactly one is ever set. */
        club: { tenant: '', typed: '', name: '', logo: '', country: '', none: false },
        clubQuery: '',
        clubResults: [],
        clubSearching: false,
        clubManual: false,
        manualName: '',
        saving: false,
        done: false,
        accepted: false,
        division: '',
@if(($feeHasOptions ?? false) || ($feeLateActive ?? false))

        /* The extras ticked, as UUIDs and nothing else. The price list is the
           one this page was rendered with and exists only to draw the total —
           EventFee::quote() re-prices the entry server-side before anybody is
           charged. */
        feeChosen: [],
        feePrices: @js($feePrices ?? []),
        feeBase: @js($feeBase ?? 0),
        feeLate: @js($feeLateAmount ?? 0),

        toggleFee(key) {
            const i = this.feeChosen.indexOf(key);
            if (i === -1) this.feeChosen.push(key); else this.feeChosen.splice(i, 1);
        },

        /* Mirrors EventFee::quote(): base + what is ticked + the penalty when
           the server says it is live. */
        get feeTotalText() {
            let total = this.feeBase + this.feeLate;
            for (const o of this.feePrices) {
                if (this.feeChosen.includes(o.key)) total += o.amount;
            }
            return @js($feeCurrencyLabel ?? '') + ' ' + total.toFixed(3).replace(/\.?0+$/, '');
        },
@endif

        bump(by) {
            const v = (this.weight ?? 60) + by;
            this.weight = Math.min(140, Math.max(20, Math.round(v * 2) / 2));
        },

        async searchClubs() {
            const q = this.clubQuery.trim();
            /* No minimum length any more. The endpoint answers with THIS
               EVENT's clubs, not every club on the platform, so an empty box
               is a short list worth showing rather than a directory dump --
               and an entrant who does not know how their club is spelled can
               now just look. */

            this.clubSearching = true;
            try {
                const url = @js(route('events.public.enter.clubs', ['event' => $e['uuid']])) + '?q=' + encodeURIComponent(q);
                const res = await fetch(url, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                const d = await res.json().catch(() => ({}));
                this.clubResults = Array.isArray(d.clubs) ? d.clubs : [];
            } catch (e) {
                this.clubResults = [];
            } finally {
                this.clubSearching = false;
            }
        },

        pickClub(c) {
            this.club = { tenant: c.slug, typed: '', name: c.name, logo: c.logo || '', country: c.country || '', none: false };
            this.clubQuery = '';
            this.clubResults = [];
        },

        /* A club we have never heard of. Opens a field of its own, seeded with
           whatever was typed into the search so the name is never typed twice. */
        typeClub() {
            this.manualName = (this.clubQuery || '').trim().slice(0, 120);
            this.clubManual = true;
            this.clubResults = [];
            this.$nextTick(() => this.$refs.manualClub?.focus());
        },

        commitClub() {
            const typed = (this.manualName || '').trim().replace(/\s+/g, ' ').slice(0, 120);
            if (typed.length < 2) { notice(@js(__('events.public_enrol_club_type_first'))); return; }

            this.club = { tenant: '', typed, name: typed, logo: '', country: '', none: false };
            this.clubManual = false;
            this.manualName = '';
            this.clubQuery = '';
        },

        clearClub() {
            this.club = { tenant: '', typed: '', name: '', logo: '', country: '', none: false };
            this.clubManual = false;
            this.manualName = '';
        },

        async submit() {
            if (this.saving) return;
            this.saving = true;
            try {
                const res = await fetch(@js(route('events.public.enter.mine', ['event' => $e['uuid']])), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        weight: this.weight,
                        belt: this.belt || null,
                        club_slug: this.club.tenant || null,
                        club_name: this.club.typed || null,
@if($feeHasOptions ?? false)
                        fee_options: this.feeChosen,
@endif
                    }),
                });
                const d = await res.json().catch(() => ({}));
                if (!res.ok || !d.success) throw new Error(d.message || @js(__('events.public_enrol_closed')));

                this.accepted = d.state === 'accepted';
                this.division = d.division || '';
                this.done = true;
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } catch (e) {
                notice(e.message);
            } finally {
                this.saving = false;
            }
        },
    };
}
</script>
@endpush
