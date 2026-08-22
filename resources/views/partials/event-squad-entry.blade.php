{{--
    Entering a squad — the coach's door into an event.

    A tournament does not receive entries one athlete at a time: a coach enters
    fourteen in a sitting, knows who is fighting, and until now could not do it
    at all (the right was hard-coded to two role slugs). It is offered to whoever
    holds their club's `enter-athletes` grant, and every athlete still goes
    through exactly the checks self-entry goes through — this is a convenience,
    never a way around the rules, so a refusal shows the server's own reason
    beside the name rather than disappearing into a toast.

    The second tab is the other half of the same relationship: athletes who
    entered themselves claiming this club. The club cannot approve them — they
    are already in — it can only take its name off, which leaves them competing
    unattached.

    Expects: $e, and $canEnterAthletes from PersonalEventController::show.
--}}
@if($canEnterAthletes ?? false)
    <button type="button" @click="openSquad()"
            class="m-card m-press w-full rounded-2xl p-4 flex items-center gap-3 text-start">
        <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0 text-white"
              style="background: {{ $e['color'] }};">
            <i class="bi bi-people-fill"></i>
        </span>
        <span class="min-w-0 flex-1">
            <span class="block text-sm font-bold text-foreground">{{ __('personal.event_show_squad_title') }}</span>
            <span class="block text-[11px] text-muted-foreground leading-snug">{{ __('personal.event_show_squad_hint') }}</span>
        </span>
        <i class="bi bi-chevron-right text-muted-foreground rtl:rotate-180"></i>
    </button>

    {{-- Teleported to <body>: the mobile shell leaves a transform on its
         children, which would make bottom-0 / max-h resolve against a wrapper
         instead of the viewport and clip the sheet. --}}
    <template x-teleport="body" data-teleport-template="true">
        <div x-show="squadOpen" x-cloak class="fixed inset-0 z-[70] flex items-end sm:items-center sm:justify-center sm:p-4"
             @keydown.escape.window="squadOpen = false" style="display:none;">
            <div x-show="squadOpen" x-transition.opacity class="absolute inset-0 bg-black/40" @click="squadOpen = false"></div>

            <div x-show="squadOpen"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full sm:translate-y-4 sm:opacity-0" x-transition:enter-end="translate-y-0 sm:opacity-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0 sm:opacity-100" x-transition:leave-end="translate-y-full sm:translate-y-4 sm:opacity-0"
                 class="relative w-full sm:max-w-lg max-h-[92vh] sm:max-h-[85vh] flex flex-col bg-white rounded-t-3xl sm:rounded-2xl shadow-2xl">

                <div class="flex-shrink-0 px-5 pt-3 pb-3 border-b border-gray-100">
                    <div class="w-10 h-1.5 bg-gray-200 rounded-full mx-auto mb-3 sm:hidden"></div>
                    <h3 class="text-lg font-bold text-gray-900">{{ __('personal.event_show_squad_title') }}</h3>
                    <p class="text-sm text-muted-foreground truncate">{{ $e['title'] }}</p>

                    <div class="mt-3 grid grid-cols-2 gap-1 p-1 rounded-xl bg-muted/60">
                        <button type="button" @click="squadTab = 'roster'"
                                :class="squadTab === 'roster' ? 'bg-white shadow-sm text-foreground' : 'text-muted-foreground'"
                                class="py-1.5 rounded-lg text-[12px] font-bold transition-colors">
                            {{ __('personal.event_show_squad_tab_roster') }}
                        </button>
                        <button type="button" @click="squadTab = 'claims'"
                                :class="squadTab === 'claims' ? 'bg-white shadow-sm text-foreground' : 'text-muted-foreground'"
                                class="py-1.5 rounded-lg text-[12px] font-bold transition-colors">
                            {{ __('personal.event_show_squad_tab_claims') }}
                            <span x-show="claims.length" x-cloak
                                  class="ms-1 px-1.5 py-0.5 rounded-full bg-primary/10 text-primary text-[10px]"
                                  x-text="claims.length"></span>
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-4">

                    <div x-show="squadLoading && squadTab === 'claims'" class="py-10 text-center text-muted-foreground text-sm">
                        <i class="bi bi-arrow-repeat animate-spin"></i>
                    </div>

                    {{-- ---- Who this coach may enter ---- --}}
                    <div x-show="squadTab === 'roster'" x-cloak class="space-y-2">

                        {{-- Find one athlete rather than scroll a club.
                             Searches name, email and phone SERVER-side, so the
                             list on screen never has to carry anyone's contact
                             details to be searchable by them. --}}
                        <div class="relative">
                            <i class="bi bi-search absolute start-3 top-1/2 -translate-y-1/2 text-muted-foreground text-sm pointer-events-none"></i>
                            <input type="search" x-model="squadQuery" @input="searchSquad()"
                                   inputmode="search" autocomplete="off"
                                   placeholder="{{ __('personal.event_show_squad_search_placeholder') }}"
                                   class="w-full ps-9 pe-9 py-2.5 text-sm border border-gray-200 rounded-xl
                                          focus:ring-2 focus:ring-primary focus:border-transparent">
                            <button type="button" x-show="squadQuery" x-cloak @click="clearSquadSearch()"
                                    class="absolute end-2 top-1/2 -translate-y-1/2 w-7 h-7 rounded-lg grid place-items-center text-muted-foreground hover:bg-muted"
                                    aria-label="{{ __('personal.event_show_squad_search_clear') }}">
                                <i class="bi bi-x-lg text-xs"></i>
                            </button>
                        </div>

                        <div x-show="squadLoading" class="py-8 text-center text-muted-foreground text-sm">
                            <i class="bi bi-arrow-repeat animate-spin"></i>
                        </div>

                        <div class="flex items-center justify-between" x-show="athletes.length && ! squadLoading">
                            <p class="text-[11px] text-muted-foreground">
                                <span x-text="enterableCount"></span> {{ __('personal.event_show_squad_available') }}
                                {{-- Say plainly when the club is bigger than the page. --}}
                                <span x-show="squadTotal > squadShown" x-cloak
                                      x-text="' · {{ __('personal.event_show_squad_showing') }} ' + squadShown + '/' + squadTotal"></span>
                            </p>
                            <button type="button" @click="pickAllEnterable()" x-show="enterableCount"
                                    class="text-[11px] font-bold text-primary">{{ __('personal.event_show_squad_select_all') }}</button>
                        </div>

                        <template x-for="a in (squadLoading ? [] : athletes)" :key="a.id">
                            <button type="button" @click="a.can_enter && ! a.entered ? togglePick(a.id) : null"
                                    :disabled="a.entered || ! a.can_enter"
                                    :class="picked.includes(a.id) ? 'border-primary bg-primary/5' : 'border-gray-200'"
                                    class="w-full rounded-xl border-2 p-3 flex items-center gap-3 text-start transition-colors disabled:opacity-70">
                                <span class="w-5 h-5 rounded-md border-2 grid place-items-center flex-shrink-0"
                                      :class="a.entered ? 'border-green-500 bg-green-500 text-white'
                                            : (picked.includes(a.id) ? 'border-primary bg-primary text-white' : 'border-gray-300')">
                                    <i class="bi bi-check text-[11px]" x-show="a.entered || picked.includes(a.id)"></i>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-[13px] font-bold text-foreground truncate" x-text="a.name"></span>
                                    {{-- Where they will fight, that they are still
                                         to be weighed, or why they cannot enter.
                                         A missing weight is a NOTE, not a refusal
                                         — they are weighed on the day like
                                         everyone else. --}}
                                    <span class="block text-[11px] leading-snug truncate"
                                          :class="! a.can_enter ? 'text-amber-600' : (a.pending_weigh_in ? 'text-primary' : 'text-muted-foreground')"
                                          x-text="a.entered
                                                ? '{{ __('personal.event_show_squad_entered') }}' + (a.division ? ' · ' + a.division : '{{ __('personal.event_show_squad_at_weigh_in') }}')
                                                : (a.can_enter ? (a.pending_weigh_in ? (a.reason || '') : (a.division || '')) : (a.reason || ''))"></span>
                                </span>
                                <span x-show="a.entered" x-cloak
                                      class="text-[10px] font-bold text-green-600 flex-shrink-0">{{ __('personal.event_show_squad_in') }}</span>
                            </button>
                        </template>

                        <div x-show="! athletes.length && ! squadLoading" x-cloak class="py-10 text-center">
                            <i class="bi text-3xl text-gray-300" :class="squadQuery ? 'bi-search' : 'bi-people'"></i>
                            <p class="text-sm text-muted-foreground mt-2"
                               x-text="squadQuery
                                    ? '{{ __('personal.event_show_squad_no_match') }}'
                                    : '{{ __('personal.event_show_squad_empty') }}'"></p>
                        </div>
                    </div>

                    {{-- ---- Athletes who entered themselves under this club ---- --}}
                    <div x-show="squadTab === 'claims' && ! squadLoading" x-cloak class="space-y-2">
                        <p class="text-[11px] text-muted-foreground leading-snug" x-show="claims.length">
                            {{ __('personal.event_show_claims_hint') }}
                        </p>

                        <template x-for="c in claims" :key="c.user_id">
                            <div class="rounded-xl border border-gray-200 p-3 flex items-center gap-3">
                                <span class="min-w-0 flex-1">
                                    <span class="block text-[13px] font-bold text-foreground truncate" x-text="c.name"></span>
                                    <span class="block text-[11px] text-muted-foreground truncate"
                                          x-text="(c.division || '') + (c.at ? ' · ' + c.at : '')"></span>
                                </span>
                                <span x-show="c.disowned" x-cloak
                                      class="text-[10px] font-bold text-amber-600 flex-shrink-0">{{ __('events.claim_unattached') }}</span>
                                <button type="button" x-show="! c.disowned" @click="disownClaim(c.user_id, c.name)"
                                        class="m-press text-[11px] font-bold px-2.5 py-1.5 rounded-lg border border-gray-200 text-foreground hover:bg-muted flex-shrink-0">
                                    {{ __('personal.event_show_disown_btn') }}
                                </button>
                            </div>
                        </template>

                        <div x-show="! claims.length" x-cloak class="py-10 text-center">
                            <i class="bi bi-flag text-3xl text-gray-300"></i>
                            <p class="text-sm text-muted-foreground mt-2">{{ __('personal.event_show_claims_empty') }}</p>
                        </div>
                    </div>
                </div>

                <div class="flex-shrink-0 px-5 pt-3 border-t border-gray-100 space-y-2"
                     style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" x-show="squadTab === 'roster'" @click="submitEntries()"
                            :disabled="! picked.length || squadSaving"
                            class="m-press w-full py-3 rounded-xl bg-primary text-white font-bold text-sm
                                   flex items-center justify-center gap-2 active:scale-[.98] transition disabled:opacity-50">
                        <i class="bi bi-person-plus"></i>
                        <span x-text="squadSaving
                            ? '{{ __('personal.event_show_join_working') }}'
                            : '{{ __('personal.event_show_squad_enter') }}' + (picked.length ? ' (' + picked.length + ')' : '')"></span>
                    </button>
                    <button type="button" @click="squadOpen = false" :disabled="squadSaving"
                            class="w-full py-2 text-[12px] font-semibold text-muted-foreground">
                        {{ __('shared.close') }}
                    </button>
                </div>
            </div>
        </div>
    </template>
@endif
