{{--
    Who has joined — participants, spectators and (for organisers) the blocked
    list. Its own partial because it now renders on its own page
    (personal.event-people), reached from a card on the event screen: on a phone
    a 48-name roster buried under the event detail is a lot to scroll past, and
    moderation deserves the whole screen.

    Expects $e, $canManage, $hasTicket, $byQual, and the Alpine state from
    partials.event-show-script (moderate(), goingCount, spectators,
    blockedCount) — so whatever includes this must sit inside that x-data.
--}}
        @php
            $showTabs = $hasTicket || ($canManage ?? false);

            // flag-icons needs a lowercase ISO alpha-2 class. Normalised the same
            // way the bracket runtime does it, so a stray code can never emit a
            // broken `fi fi-` class. Returns '' when unusable.
            $flag = function ($code) {
                $c = strtolower(substr(preg_replace('/[^a-zA-Z]/', '', (string) $code), 0, 2));

                return strlen($c) === 2 ? '<span class="fi fi-'.$c.' rounded-sm shrink-0"></span>' : '';
            };
        @endphp
        {{-- No card around the whole list: each person is their own card, so the
             roster reads as a stack of people rather than one long slab. Only the
             heading and tabs are grouped. --}}
        <div x-data="{
                rtab: 'participants', open: false, q: '',
                // Filtering is client-side on purpose: every name in the current
                // list is already on the page, so typing narrows it instantly
                // with no round trip. It only ever hides rows the server already
                // decided this viewer may see.
                match(name) {
                    const s = this.q.trim().toLowerCase();
                    return !s || (name || '').toLowerCase().includes(s);
                },
                // Names per list, so a list can say when a search matched nothing.
                names: {
                    participants: @js(collect($e['participants'] ?? [])->pluck('name')->values()),
                    spectators: @js(collect($e['spectators_list'] ?? [])->pluck('name')->values()),
                    blocked: @js(collect($e['bans_list'] ?? [])->pluck('name')->values()),
                },
                noMatches(list) {
                    return this.q.trim() !== '' && !(this.names[list] || []).some(n => this.match(n));
                },
             }">
            {{-- Heading and the list switcher on one line. A menu rather than a
                 row of tabs: three full-width tabs ate a whole band of a phone
                 screen before a single name appeared, and the counts read just as
                 well inside the menu. --}}
            <div class="flex items-center justify-between gap-3 px-1">
                {{-- Search sits where the heading was: the page title already says
                     whose list this is, so the space is better spent narrowing it. --}}
                <div class="relative flex-1 min-w-0">
                    <i class="bi bi-search absolute start-3 top-1/2 -translate-y-1/2 text-muted-foreground text-xs pointer-events-none"></i>
                    {{-- Same shell as the list dropdown beside it: rounded-xl,
                         border-gray-200, shadow-sm. The border is kept on focus
                         (no focus:border-transparent) so the two controls stay
                         the same shape while you type. --}}
                    <input type="search" x-model="q"
                           placeholder="{{ __('personal.event_show_search_people') }}"
                           aria-label="{{ __('personal.event_show_search_people') }}"
                           class="w-full ps-8 pe-8 py-1.5 rounded-xl border border-gray-200 bg-white shadow-sm
                                  text-xs font-bold text-foreground transition-colors
                                  focus:ring-2 focus:ring-purple-500/40 outline-none">
                    <button type="button" x-show="q" x-cloak @click="q = ''"
                            class="absolute end-2 top-1/2 -translate-y-1/2 w-5 h-5 grid place-items-center rounded-full
                                   text-muted-foreground hover:bg-muted transition-colors"
                            aria-label="{{ __('personal.event_show_search_clear') }}">
                        <i class="bi bi-x-lg text-[0.6rem]"></i>
                    </button>
                </div>

                @if($showTabs)
                    <div class="relative shrink-0"
                         @keydown.escape.window="open = false"
                         @click.outside="open = false">

                        <button type="button" @click="open = !open"
                                :aria-expanded="open" aria-haspopup="listbox"
                                class="flex items-center gap-2 ps-2.5 pe-2 py-1.5 rounded-xl border border-gray-200 bg-white
                                       text-xs font-bold text-foreground shadow-sm hover:bg-muted/60 transition-colors">
                            {{-- The selected list. Kept as siblings rather than a
                                 lookup so each label stays translatable. --}}
                            <span x-show="rtab==='participants'" class="flex items-center gap-1.5">
                                <i class="bi bi-person-arms-up text-primary"></i>{{ __('personal.event_show_participants') }}
                            </span>
                            @if($hasTicket)
                                <span x-show="rtab==='spectators'" x-cloak class="flex items-center gap-1.5">
                                    <i class="bi bi-eye text-sky-500"></i>{{ __('personal.event_show_spectators') }}
                                </span>
                            @endif
                            @if($canManage ?? false)
                                <span x-show="rtab==='blocked'" x-cloak class="flex items-center gap-1.5">
                                    <i class="bi bi-shield-x text-red-500"></i>{{ __('personal.event_show_blocked') }}
                                </span>
                            @endif

                            <span class="px-1.5 py-0.5 rounded-full bg-muted text-muted-foreground text-[0.6rem] font-extrabold"
                                  x-text="rtab==='participants' ? goingCount : (rtab==='spectators' ? spectators : blockedCount)">{{ $e['participants_total'] ?? $e['going'] }}</span>
                            <i class="bi bi-chevron-down text-muted-foreground transition-transform" :class="open && 'rotate-180'"></i>
                        </button>

                        <div x-show="open" x-cloak x-transition.opacity.duration.120ms role="listbox"
                             class="absolute end-0 mt-2 w-56 rounded-xl border border-gray-200 bg-white shadow-xl z-40 py-1">
                            <button type="button" role="option" :aria-selected="rtab==='participants'"
                                    @click="rtab='participants'; open = false"
                                    :class="rtab==='participants' ? 'bg-primary/10 text-primary' : 'text-foreground hover:bg-muted/60'"
                                    class="w-full flex items-center gap-2.5 px-3 py-2 text-start text-xs font-bold transition-colors">
                                <i class="bi bi-person-arms-up text-primary shrink-0"></i>
                                <span class="flex-1 truncate">{{ __('personal.event_show_participants') }}</span>
                                <span class="px-1.5 py-0.5 rounded-full bg-muted text-muted-foreground text-[0.6rem] font-extrabold"
                                      x-text="goingCount">{{ $e['participants_total'] }}</span>
                                <i class="bi bi-check-lg shrink-0" x-show="rtab==='participants'"></i>
                            </button>

                            @if($hasTicket)
                                <button type="button" role="option" :aria-selected="rtab==='spectators'"
                                        @click="rtab='spectators'; open = false"
                                        :class="rtab==='spectators' ? 'bg-primary/10 text-primary' : 'text-foreground hover:bg-muted/60'"
                                        class="w-full flex items-center gap-2.5 px-3 py-2 text-start text-xs font-bold transition-colors">
                                    <i class="bi bi-eye text-sky-500 shrink-0"></i>
                                    <span class="flex-1 truncate">{{ __('personal.event_show_spectators') }}</span>
                                    <span class="px-1.5 py-0.5 rounded-full bg-muted text-muted-foreground text-[0.6rem] font-extrabold"
                                          x-text="spectators"></span>
                                    <i class="bi bi-check-lg shrink-0" x-show="rtab==='spectators'" x-cloak></i>
                                </button>
                            @endif

                            @if($canManage ?? false)
                                <button type="button" role="option" :aria-selected="rtab==='blocked'"
                                        @click="rtab='blocked'; open = false"
                                        :class="rtab==='blocked' ? 'bg-primary/10 text-primary' : 'text-foreground hover:bg-muted/60'"
                                        class="w-full flex items-center gap-2.5 px-3 py-2 text-start text-xs font-bold transition-colors">
                                    <i class="bi bi-shield-x text-red-500 shrink-0"></i>
                                    <span class="flex-1 truncate">{{ __('personal.event_show_blocked') }}</span>
                                    <span class="px-1.5 py-0.5 rounded-full bg-muted text-muted-foreground text-[0.6rem] font-extrabold"
                                          x-text="blockedCount">{{ count($e['bans_list'] ?? []) }}</span>
                                    <i class="bi bi-check-lg shrink-0" x-show="rtab==='blocked'" x-cloak></i>
                                </button>
                            @endif
                        </div>
                    </div>
                @else
                    <span class="text-[11px] font-semibold text-primary shrink-0" x-text="`${goingCount} {{ __('personal.event_show_in') }}`">{{ $e['participants_total'] ?? $e['going'] }} {{ __('personal.event_show_in') }}</span>
                @endif
            </div>

            {{-- Participants (competitors only) --}}
            <div class="mt-3 space-y-2.5" @if($showTabs) x-show="rtab==='participants'" x-transition @endif>
                @forelse($e['participants'] as $i => $pp)
                    @php $initials = collect(explode(' ', $pp['name']))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode(''); @endphp
                    <div class="m-card rounded-2xl p-3 flex items-center gap-3" x-show="match(@js($pp['name']))" @if($pp['id'] ?? false) id="prow-{{ $pp['id'] }}" @endif>
                        <div class="w-9 h-9 rounded-full grid place-items-center text-white text-[11px] font-bold flex-shrink-0"
                             style="background: hsl({{ ($i * 67) % 360 }} 55% 58%);">{{ $initials }}</div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-foreground truncate flex items-center gap-1.5">
                                {!! $flag($pp['country'] ?? null) !!}<span class="truncate">{{ $pp['name'] }}</span>
                            </p>
                            @php
                                $bits = array_filter([
                                    $pp['gender'] ?? null,
                                    $pp['category'] ?? null,
                                    $pp['weight_class'] ?? null,
                                ]);
                            @endphp
                            <p class="text-[11px] text-muted-foreground truncate">{{ $bits ? implode(' · ', $bits) : $pp['meta'] }}</p>

                            {{-- The three gates to being drawn, each shown done or
                                 not — an organiser scanning the list can see WHICH
                                 one is missing, which a single "Pending" badge
                                 never told them. --}}
                            @php
                                // Enrolment is a fact the system owns, so it has
                                // no unverified middle state. Payment and weight
                                // are claims until an official signs them off.
                                $paidState = ($pp['paid_verified'] ?? false) ? 'verified'
                                    : (($pp['paid'] ?? false) ? 'claimed' : 'none');
                                $weighState = ($pp['weighed_verified'] ?? false) ? 'verified'
                                    : (($pp['weighed'] ?? false) ? 'claimed' : 'none');
                            @endphp
                            <div class="flex items-center gap-1 mt-1.5 flex-wrap">
                                <x-event-status-chip :state="($pp['enrolled'] ?? true) ? 'verified' : 'none'"
                                                     icon="bi-person-check" done-icon="bi-person-check-fill"
                                                     :label="__('personal.event_show_chip_enrolled')" />
                                <x-event-status-chip :state="$paidState"
                                                     icon="bi-cash" done-icon="bi-cash-coin"
                                                     :label="__('personal.event_show_chip_paid')" />
                                <x-event-status-chip :state="$weighState"
                                                     icon="bi-speedometer" done-icon="bi-speedometer2"
                                                     :label="__('personal.event_show_chip_weighed')" />
                            </div>
                        </div>
                        @if(($canManage ?? false) && ($pp['id'] ?? false))
                            <x-event-moderate-menu :id="$pp['id']" :name="$pp['name']" />
                        @endif
                    </div>
                @empty
                    <p class="text-[11px] text-muted-foreground text-center py-3">{{ __('personal.event_show_no_competitors') }}</p>
                @endforelse
                {{-- No "+ N more": this page lists everyone. The controller asks
                     eventView() for the whole roster (wholeRoster: true). --}}
                {{-- Nothing matched the search — distinct from "nobody has joined". --}}
                <p x-show="noMatches('participants')" x-cloak class="text-[11px] text-muted-foreground text-center py-3">
                    {{ __('personal.event_show_search_none') }}
                </p>
            </div>

            @if($hasTicket)
                {{-- Spectators (ticket holders) --}}
                <div class="mt-3 space-y-2.5" x-show="rtab==='spectators'" x-cloak x-transition>
                    @forelse($e['spectators_list'] as $i => $sp)
                        @php $sinitials = collect(explode(' ', $sp['name']))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode(''); @endphp
                        <div class="m-card rounded-2xl p-3 flex items-center gap-3" x-show="match(@js($sp['name']))" @if($sp['id'] ?? false) id="srow-{{ $sp['id'] }}" @endif>
                            <div class="w-9 h-9 rounded-full grid place-items-center text-white text-[11px] font-bold flex-shrink-0"
                                 style="background: hsl({{ (($i + 3) * 53) % 360 }} 45% 60%);">{{ $sinitials }}</div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-foreground truncate">{{ $sp['name'] }}</p>
                                <p class="text-[11px] text-muted-foreground truncate">{{ __('personal.event_show_spectator') }}{{ str_contains(strtolower($e['spectator']['fee']),'free') ? '' : ' · '.$e['spectator']['fee'] }}</p>
                            </div>
                            @if(($sp['paid'] ?? true))
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-sky-50 text-sky-600 flex-shrink-0"><i class="bi bi-ticket-perforated"></i> {{ str_contains(strtolower($e['spectator']['fee']),'free') ? __('personal.event_show_pass') : __('personal.event_show_ticket') }}</span>
                            @else
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-50 text-amber-600 flex-shrink-0"><i class="bi bi-hourglass-split"></i> {{ __('personal.event_show_pending') }}</span>
                            @endif
                            @if(($canManage ?? false) && ($sp['id'] ?? false))
                                <x-event-moderate-menu :id="$sp['id']" :name="$sp['name']" />
                            @endif
                        </div>
                    @empty
                        <p class="text-[11px] text-muted-foreground text-center py-3">{{ __('personal.event_show_no_spectators') }}</p>
                    @endforelse
                    {{-- Every spectator too, for the same reason. --}}
                    {{-- Nothing matched the search — distinct from "nobody has joined". --}}
                    <p x-show="noMatches('spectators')" x-cloak class="text-[11px] text-muted-foreground text-center py-3">
                        {{ __('personal.event_show_search_none') }}
                    </p>
                </div>
            @endif

            @if($canManage ?? false)
                {{-- Blocked / blacklisted (manager only) --}}
                <div class="mt-3" x-show="rtab==='blocked'" x-cloak x-transition>
                    <div id="blocked-list" class="space-y-2.5">
                        @foreach($e['bans_list'] ?? [] as $bn)
                            @php $binit = collect(explode(' ', $bn['name']))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode(''); @endphp
                            <div id="brow-{{ $bn['id'] }}" class="m-card rounded-2xl p-3 flex items-center gap-3" x-show="match(@js($bn['name']))">
                                <div class="w-9 h-9 rounded-full grid place-items-center text-white text-[11px] font-bold flex-shrink-0 bg-gray-400">{{ $binit }}</div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-foreground truncate">{{ $bn['name'] }}</p>
                                    <p class="text-[11px] text-muted-foreground truncate">{{ $bn['scope'] === 'club' ? __('personal.event_show_blacklisted_scope') : __('personal.event_show_blocked_scope') }}</p>
                                </div>
                                <button type="button" @click="unblock({{ $bn['id'] }})"
                                        class="m-press text-[10px] font-bold px-2.5 py-1 rounded-full border border-gray-200 text-foreground hover:bg-muted flex-shrink-0">
                                    <i class="bi bi-arrow-counterclockwise"></i> {{ __('personal.event_show_unblock') }}
                                </button>
                            </div>
                        @endforeach
                    </div>
                    <p id="blocked-empty" class="text-[11px] text-muted-foreground text-center py-3" @if(count($e['bans_list'] ?? [])) style="display:none" @endif>{{ __('personal.event_show_no_blocked') }}</p>
                    {{-- Nothing matched the search — distinct from "nobody has joined". --}}
                    <p x-show="noMatches('blocked')" x-cloak class="text-[11px] text-muted-foreground text-center py-3">
                        {{ __('personal.event_show_search_none') }}
                    </p>
                </div>
            @endif
        </div>
