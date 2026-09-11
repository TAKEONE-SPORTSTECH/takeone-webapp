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
@php
    /*
        The priced extras this event sells, taken from the page's OWN payload
        ($e['fees'], built by PersonalEventController::eventView out of
        App\Events\Support\EventFee). No second query and no second price
        list: the join sheet and this sheet quote the same numbers because they
        read the same array.

        Empty for almost every event, and everything below that touches money is
        wrapped in $squadHasFees — an event with nothing to sell renders this
        sheet exactly as it did before multi-pricing existed, down to the submit
        handler it calls.
    */
    $squadFees = $e['fees'] ?? null;
    $squadFeeOptions = $squadFees['options'] ?? [];
    $squadHasFees = ! empty($squadFeeOptions);
@endphp
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
        <i class="bi bi-chevron-right text-muted-foreground"></i>
    </button>

    {{-- Teleported to <body>: the mobile shell leaves a transform on its
         children, which would make bottom-0 / max-h resolve against a wrapper
         instead of the viewport and clip the sheet. --}}
    <template x-teleport="body" data-teleport-template="true">
        <div x-show="squadOpen" x-cloak class="fixed inset-0 z-[70] flex items-end sm:items-center sm:justify-center sm:p-4"
             @keydown.escape.window="squadOpen = false" style="display:none;"
@if($squadHasFees)
             {{-- ===== What each athlete is entering (multi-pricing) =====

                  A nested scope on the sheet's own root, so the option map
                  lives with the sheet that collects it and the page's shared
                  state (picked, athletes, fees, feeMoney) is still inherited
                  from the parent. Written INLINE rather than registered,
                  because this markup is teleported to <body> and a script tag
                  inside a teleported template never runs.

                  Only ever UUIDs leave the browser. Every total drawn here is
                  for the coach to read — the server re-prices each entry from
                  the event's own rows in EventFee::quote(), so editing these
                  numbers in a console changes what one screen says and nothing
                  that anybody is charged. --}}
             x-data="{
                 /* option UUIDs, keyed by athlete user id: exactly the shape
                    me.events.entries wants back. */
                 feeSel: {},
                 /* what the apply-to-all shortcut pours over the squad. Held
                    apart from feeSel so a coach can set the common case once
                    and still correct one athlete afterwards. */
                 feeTemplate: [],

                 feeList() { return (this.fees && this.fees.options) || []; },
                 feeFor(id) { return this.feeSel[id] || []; },
                 athleteHasFee(id, key) { return this.feeFor(id).indexOf(key) !== -1; },

                 toggleAthleteFee(id, key) {
                     const cur = this.feeFor(id).slice();
                     const i = cur.indexOf(key);
                     if (i === -1) cur.push(key); else cur.splice(i, 1);
                     this.feeSel[id] = cur;
                 },
                 toggleTemplateFee(key) {
                     const i = this.feeTemplate.indexOf(key);
                     if (i === -1) this.feeTemplate.push(key); else this.feeTemplate.splice(i, 1);
                 },

                 /* The shortcut the coaches asked for: a real squad is mostly
                    one answer, so fill every picked athlete at once and leave
                    the exceptions to be corrected one by one. */
                 applyFeesToAll() {
                     this.picked.forEach(id => { this.feeSel[id] = this.feeTemplate.slice(); });
                     window.showToast('success', '{{ __('events.fee_applied_all') }}');
                 },

                 /* Mirrors EventFee::quote(): base + what is ticked + the late
                    penalty when the SERVER says it is live. */
                 athleteTotal(id) {
                     let total = (this.fees.base || 0);
                     this.feeList().forEach(o => { if (this.athleteHasFee(id, o.key)) total += (o.amount || 0); });
                     if (this.fees.late_active) total += (this.fees.late_amount || 0);
                     return total;
                 },
                 squadTotalMoney() {
                     return this.feeMoney(this.picked.reduce((sum, id) => sum + this.athleteTotal(id), 0));
                 },

                 /* The sheet posts its own entries once it has a map to send.
                    Same endpoint, same envelope and the same partial-success
                    handling as the shared submitEntries() — with fee_options
                    beside user_ids. An event that sells nothing never reaches
                    here: the button below still calls the shared path. */
                 async submitSquadEntries() {
                     if (! this.picked.length || this.squadSaving) return;

                     const map = {};
                     this.picked.forEach(id => { if (this.feeFor(id).length) map[id] = this.feeFor(id); });

                     this.squadSaving = true;
                     let d = null;
                     try {
                         const res = await fetch('{{ route('me.events.entries', $e['key']) }}', {
                             method: 'POST',
                             headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                             credentials: 'same-origin',
                             body: JSON.stringify({ user_ids: this.picked, fee_options: map, divisions: this.divisionsPayload() }),
                         });
                         d = await res.json().catch(() => ({}));
                         if (! res.ok || ! d.success) throw new Error(d.message || '{{ __('personal.event_show_action_failed') }}');
                     } catch (err) { this.squadSaving = false; window.showToast('error', err.message); return; }
                     this.squadSaving = false;

                     this.goingCount = d.going ?? this.goingCount;
                     (d.entered || []).forEach(row => {
                         const a = this.athletes.find(x => x.id === row.user_id);
                         if (a) { a.entered = true; a.division = row.division || a.division; }
                         /* Their choice is spent — it is on the registration
                            now, and leaving it here would re-send it if the
                            coach enters somebody else afterwards. */
                         delete this.feeSel[row.user_id];
                     });
                     (d.rejected || []).forEach(row => {
                         const a = this.athletes.find(x => x.id === row.user_id);
                         if (a) { a.can_enter = false; a.reason = row.message; }
                     });
                     this.picked = [];
                     window.showToast((d.rejected || []).length ? 'info' : 'success', d.message);
                 },
             }"
@endif
             >
            <div x-show="squadOpen" x-transition.opacity class="absolute inset-0 bg-black/40" @click="squadOpen = false"></div>

            <div x-show="squadOpen"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full sm:translate-y-4 sm:opacity-0" x-transition:enter-end="translate-y-0 sm:opacity-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0 sm:opacity-100" x-transition:leave-end="translate-y-full sm:translate-y-4 sm:opacity-0"
                 class="relative w-full sm:max-w-lg max-h-[92vh] sm:max-h-[85vh] flex flex-col bg-white rounded-t-3xl sm:rounded-2xl shadow-2xl">

                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl sm:rounded-t-2xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $e['color'] }}, {{ $e['color'] }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3 sm:hidden"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-people-fill text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('personal.event_show_squad_title') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5 truncate">{{ $e['title'] }}</p>
                        </div>
                        <button type="button" @click="squadOpen = false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="flex-shrink-0 px-5 pb-3 pt-3 bg-white border-b border-gray-100">
                    <div class="grid grid-cols-3 gap-1 p-1 rounded-xl bg-muted/60">
                        <button type="button" @click="squadTab = 'roster'"
                                :class="squadTab === 'roster' ? 'bg-white shadow-sm text-foreground' : 'text-muted-foreground'"
                                class="py-1.5 rounded-lg text-[12px] font-bold transition-colors">
                            {{ __('personal.event_show_squad_tab_roster') }}
                        </button>
                        {{-- Door B: a name is enough. The athlete supplies the rest. --}}
                        <button type="button" @click="openByName()"
                                :class="squadTab === 'byname' ? 'bg-white shadow-sm text-foreground' : 'text-muted-foreground'"
                                class="py-1.5 rounded-lg text-[12px] font-bold transition-colors">
                            {{ __('personal.event_show_squad_tab_byname') }}
                            <span x-show="waitingCount" x-cloak
                                  class="ms-1 px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-700 text-[10px]"
                                  x-text="waitingCount"></span>
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

                        {{-- ---- WHICH ACTIVITIES the squad is entering ----

                             An event may run several activities and an athlete
                             enters as many as they are paying for, each its own
                             entry (owner's ruling, 2026-09-11). Chips, not a
                             dropdown: a short known list inside a scrolling
                             sheet, where an absolutely positioned panel would
                             be clipped (Mobile Pattern Language).

                             Drawn only when there is a CHOICE to make — an
                             event with one activity (or none named yet) leaves
                             the division to the package's own gate, exactly as
                             before, and this tray never appears. --}}
                        <div x-show="picked.length && squadDivisions.length > 1" x-cloak
                             class="rounded-xl border border-gray-200 p-3 space-y-2">
                            <p class="text-[11px] font-bold text-muted-foreground uppercase tracking-wide">
                                {{ __('events.entry_activities') }}
                            </p>
                            <p class="text-[11px] text-muted-foreground leading-snug">{{ __('events.entry_activities_hint') }}</p>

                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="d in squadDivisions" :key="d.id">
                                    <button type="button" @click="toggleTemplateDivision(d.id)"
                                            :class="divTemplate.includes(d.id) ? 'border-primary bg-primary/10 text-primary' : 'border-gray-200 text-muted-foreground'"
                                            class="m-press px-2.5 py-1.5 rounded-lg border text-[11px] font-bold transition-colors">
                                        <span x-text="d.name"></span>
                                    </button>
                                </template>
                            </div>

                            <button type="button" @click="applyDivisionsToAll()"
                                    class="m-press w-full py-2 rounded-xl border border-gray-200 text-[11.5px] font-bold text-foreground flex items-center justify-center gap-1.5">
                                <i class="bi bi-check2-all"></i>{{ __('events.fee_apply_all') }}
                            </button>
                        </div>

@if($squadHasFees)
                        {{-- ---- What the squad is entering ----
                             Chips rather than a dropdown: a short, known list
                             inside a scrolling sheet, where an absolutely
                             positioned panel would be clipped by the body it
                             sits in (Mobile Pattern Language). Only drawn once
                             somebody is picked — before that there is nobody to
                             apply it to.

                             The prices are shown on the chips because the coach
                             is committing somebody else's money and should be
                             able to add it up on the way past. --}}
                        <div x-show="picked.length" x-cloak class="rounded-xl border border-gray-200 p-3 space-y-2">
                            <p class="text-[11px] font-bold text-muted-foreground uppercase tracking-wide">
                                {{ __('events.fee_select_options') }}
                            </p>

                            <div class="flex flex-wrap gap-1.5">
                                @foreach($squadFeeOptions as $opt)
                                    <button type="button" @click="toggleTemplateFee('{{ $opt['key'] }}')"
                                            :class="feeTemplate.includes('{{ $opt['key'] }}') ? 'border-primary bg-primary/10 text-primary' : 'border-gray-200 text-muted-foreground'"
                                            class="m-press px-2.5 py-1.5 rounded-lg border text-[11px] font-bold transition-colors">
                                        {{ $opt['label'] }} · {{ $opt['display'] }}
                                    </button>
                                @endforeach
                            </div>

                            {{-- The penalty is decided on the SERVER and is in
                                 every total below; said out loud so a coach is
                                 never surprised by the squad total. --}}
                            <p x-show="fees.late_active" x-cloak
                               class="text-[11px] font-bold text-amber-600 flex items-center gap-1.5">
                                <i class="bi bi-clock-history"></i><span x-text="lateFeeNote()"></span>
                            </p>

                            <button type="button" @click="applyFeesToAll()"
                                    class="m-press w-full py-2 rounded-xl border border-gray-200 text-[11.5px] font-bold text-foreground flex items-center justify-center gap-1.5">
                                <i class="bi bi-check2-all"></i>{{ __('events.fee_apply_all') }}
                            </button>
                        </div>
@endif

                        <template x-for="a in (squadLoading ? [] : athletes)" :key="a.id">
{{-- The row is wrapped so it can carry the athlete's own chips underneath it —
     the priced options, and which activities they are entering. The wrapper is
     unconditional since 2026-09-11: the activities tray belongs to every event
     that runs more than one, whether or not it sells anything. --}}
                          <div>
                            {{-- An athlete already entered is STILL pickable
                                 when the event runs an activity they are not in
                                 yet — that is how a second entry is added. With
                                 one activity, or none left, the row is done and
                                 says so. --}}
                            <button type="button" @click="canPickAthlete(a) ? togglePick(a.id) : null"
                                    :disabled="! canPickAthlete(a)"
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
                                                ? '{{ __('personal.event_show_squad_entered') }}' + ((a.divisions && a.divisions.length) ? ' · ' + a.divisions.join(' · ') : (a.division ? ' · ' + a.division : '{{ __('personal.event_show_squad_at_weigh_in') }}'))
                                                : (a.can_enter ? (a.pending_weigh_in ? (a.reason || '') : (a.division || '')) : (a.reason || ''))"></span>
                                </span>
                                <span x-show="a.entered" x-cloak
                                      class="text-[10px] font-bold text-green-600 flex-shrink-0">{{ __('personal.event_show_squad_in') }}</span>
                            </button>
                            {{-- This athlete's own activities, revealed once
                                 they are picked (progressive disclosure) and
                                 only where the event runs more than one. An
                                 activity they already hold shows as done: a
                                 second entry in the same division is the
                                 duplicate this whole change exists to prevent. --}}
                            <div x-show="picked.includes(a.id) && squadDivisions.length > 1" x-cloak
                                 class="mt-1.5 mb-1 ps-8 pe-1">
                                <div class="flex flex-wrap gap-1.5">
                                    <template x-for="d in squadDivisions" :key="d.id">
                                        <button type="button"
                                                @click="athleteInDivision(a, d.id) ? null : toggleAthleteDivision(a.id, d.id)"
                                                :disabled="athleteInDivision(a, d.id)"
                                                :class="athleteInDivision(a, d.id)
                                                    ? 'border-green-200 bg-green-50 text-green-700'
                                                    : (athleteHasDivision(a.id, d.id) ? 'border-primary bg-primary/10 text-primary' : 'border-gray-200 text-muted-foreground')"
                                                class="m-press px-2.5 py-1.5 rounded-lg border text-[11px] font-bold transition-colors disabled:opacity-80">
                                            <i class="bi bi-check2 me-1" x-show="athleteInDivision(a, d.id)" x-cloak></i>
                                            <span x-text="d.name"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>

@if($squadHasFees)
                            {{-- One athlete's own answer, revealed only once
                                 they are picked (progressive disclosure): a
                                 squad of forty with four chips each would be a
                                 wall. It starts from whatever apply-to-all
                                 poured in and can differ per athlete, because a
                                 real squad is mixed — some in Gi, some not. --}}
                            <div x-show="picked.includes(a.id)" x-cloak class="mt-1.5 mb-1 ps-8 pe-1 space-y-1.5">
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($squadFeeOptions as $opt)
                                        <button type="button" @click="toggleAthleteFee(a.id, '{{ $opt['key'] }}')"
                                                :class="athleteHasFee(a.id, '{{ $opt['key'] }}') ? 'border-primary bg-primary/10 text-primary' : 'border-gray-200 text-muted-foreground'"
                                                class="m-press px-2.5 py-1.5 rounded-lg border text-[11px] font-bold transition-colors">
                                            {{ $opt['label'] }} · {{ $opt['display'] }}
                                        </button>
                                    @endforeach
                                </div>
                                <p class="text-[11px] text-muted-foreground">
                                    {{ __('events.fee_total') }} ·
                                    <span class="font-bold text-foreground" x-text="feeMoney(athleteTotal(a.id))"></span>
                                </p>
                            </div>
@endif
                          </div>
                        </template>

                        <div x-show="! athletes.length && ! squadLoading" x-cloak class="py-10 text-center">
                            <i class="bi text-3xl text-gray-300" :class="squadQuery ? 'bi-search' : 'bi-people'"></i>
                            <p class="text-sm text-muted-foreground mt-2"
                               x-text="squadQuery
                                    ? '{{ __('personal.event_show_squad_no_match') }}'
                                    : '{{ __('personal.event_show_squad_empty') }}'"></p>
                        </div>
                    </div>

                    {{-- ---- Entering somebody who is not on the roster ---- --}}
                    <div x-show="squadTab === 'byname'" x-cloak class="space-y-3">

                        {{-- The form. A NAME and nothing else is the point: a
                             coach who is made to guess a birthdate invents one,
                             and an invented one is worse than a blank. --}}
                        <div class="rounded-2xl border border-gray-200 p-3.5 space-y-3">
                            <div>
                                <label class="block text-[12px] font-bold text-foreground mb-1.5">
                                    {{ __('personal.event_show_byname_label') }}
                                </label>
                                <input type="text" x-model="byName.full_name" autocomplete="off"
                                       @keydown.enter.prevent="issueClaim()"
                                       placeholder="{{ __('personal.event_show_byname_placeholder') }}"
                                       class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl
                                              focus:ring-2 focus:ring-primary focus:border-transparent">
                                <p class="text-[11px] text-muted-foreground mt-1.5 leading-snug">
                                    {{ __('personal.event_show_byname_hint') }}
                                </p>
                            </div>

                            {{-- Optional, and only so the platform can deliver
                                 the link for him. Folded away by default so the
                                 form still reads as "just a name". --}}
                            <button type="button" @click="byNameContact = ! byNameContact"
                                    class="text-[11.5px] font-bold text-primary flex items-center gap-1.5">
                                <i class="bi" :class="byNameContact ? 'bi-chevron-up' : 'bi-chevron-down'"></i>
                                {{ __('personal.event_show_byname_contact_toggle') }}
                            </button>

                            <div x-show="byNameContact" x-cloak x-transition class="space-y-2">
                                <input type="email" x-model="byName.contact_email" inputmode="email" autocomplete="off"
                                       placeholder="{{ __('personal.event_show_byname_email') }}"
                                       class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl
                                              focus:ring-2 focus:ring-primary focus:border-transparent">
                                <input type="tel" x-model="byName.contact_phone" inputmode="tel" autocomplete="off"
                                       placeholder="{{ __('personal.event_show_byname_phone') }}"
                                       class="w-full px-3 py-2.5 text-sm border border-gray-200 rounded-xl
                                              focus:ring-2 focus:ring-primary focus:border-transparent">
                            </div>

                            <button type="button" @click="issueClaim()"
                                    :disabled="! byName.full_name.trim() || claimSaving"
                                    class="m-press w-full py-2.5 rounded-xl bg-primary text-white font-bold text-sm
                                           flex items-center justify-center gap-2 disabled:opacity-50">
                                <i class="bi bi-person-plus"></i>
                                <span x-text="claimSaving
                                    ? '{{ __('personal.event_show_join_working') }}'
                                    : '{{ __('personal.event_show_byname_submit') }}'"></span>
                            </button>
                        </div>

                        {{-- The link just minted. Shown ONCE, to the person who
                             created it — a claim link is a credential and is
                             never handed back out of the listing below. --}}
                        <div x-show="freshClaim" x-cloak x-transition
                             class="rounded-2xl p-3.5 border-2 border-primary/40 bg-primary/5 space-y-2.5">
                            <div class="flex items-start gap-2.5">
                                <i class="bi bi-link-45deg text-primary text-lg"></i>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[13px] font-black text-foreground" x-text="freshClaim?.name"></p>
                                    <p class="text-[11px] text-muted-foreground leading-snug">
                                        {{ __('personal.event_show_byname_share_hint') }}
                                    </p>
                                </div>
                                <button type="button" @click="freshClaim = null"
                                        class="text-muted-foreground" aria-label="{{ __('shared.close') }}">
                                    <i class="bi bi-x-lg text-xs"></i>
                                </button>
                            </div>

                            <p class="text-[11px] font-mono break-all bg-white rounded-lg border border-gray-200 px-2.5 py-2 text-muted-foreground"
                               x-text="freshClaim?.url"></p>

                            <div class="grid grid-cols-3 gap-2">
                                <button type="button" @click="copyClaim()"
                                        class="m-press py-2 rounded-xl bg-white border border-gray-200 text-[11.5px] font-bold flex items-center justify-center gap-1.5">
                                    <i class="bi bi-clipboard"></i>{{ __('personal.event_show_byname_copy') }}
                                </button>
                                <a :href="'https://wa.me/?text=' + encodeURIComponent(freshClaim ? (freshClaim.name + ' — ' + freshClaim.url) : '')"
                                   target="_blank" rel="noopener"
                                   class="m-press py-2 rounded-xl bg-white border border-gray-200 text-[11.5px] font-bold flex items-center justify-center gap-1.5">
                                    <i class="bi bi-whatsapp text-green-600"></i>{{ __('personal.event_show_byname_whatsapp') }}
                                </a>
                                <button type="button" @click="shareClaim()"
                                        class="m-press py-2 rounded-xl bg-white border border-gray-200 text-[11.5px] font-bold flex items-center justify-center gap-1.5">
                                    <i class="bi bi-share"></i>{{ __('personal.event_show_byname_share') }}
                                </button>
                            </div>
                        </div>

                        {{-- Who is still waiting on somebody. This is the
                             checklist the day depends on: an incomplete entry
                             has no division, so it is never drawn. --}}
                        <div x-show="pendingClaims.length" x-cloak class="pt-1">
                            <p class="text-[11px] font-bold text-muted-foreground uppercase tracking-wide mb-2">
                                {{ __('personal.event_show_byname_waiting') }}
                            </p>

                            <template x-for="c in pendingClaims" :key="c.uuid">
                                <div class="rounded-xl border border-gray-200 p-3 flex items-center gap-3 mb-2">
                                    <span class="w-8 h-8 rounded-lg grid place-items-center flex-shrink-0 text-[11px] font-black"
                                          :class="c.entry_state === 'complete' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700'">
                                        <i class="bi" :class="c.entry_state === 'complete' ? 'bi-check-lg' : 'bi-hourglass-split'"></i>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-[13px] font-bold text-foreground truncate" x-text="c.name"></span>
                                        <span class="block text-[11px] truncate"
                                              :class="c.entry_state === 'complete' ? 'text-green-600' : 'text-muted-foreground'"
                                              x-text="c.entry_state === 'complete'
                                                    ? (c.division || '{{ __('personal.event_show_squad_at_weigh_in') }}')
                                                    : claimStateLabel(c)"></span>
                                    </span>
                                    <div class="flex items-center gap-1 flex-shrink-0" x-show="c.entry_state !== 'complete'">
                                        <button type="button" @click="relinkClaim(c)"
                                                class="m-press w-8 h-8 rounded-lg border border-gray-200 grid place-items-center text-muted-foreground"
                                                :title="'{{ __('personal.event_show_byname_relink') }}'">
                                            <i class="bi bi-arrow-repeat text-xs"></i>
                                        </button>
                                        <button type="button" @click="revokeClaim(c)"
                                                class="m-press w-8 h-8 rounded-lg border border-red-200 text-red-600 grid place-items-center"
                                                :title="'{{ __('personal.event_show_byname_withdraw') }}'">
                                            <i class="bi bi-trash text-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <div x-show="! pendingClaims.length && ! freshClaim" x-cloak class="py-6 text-center">
                            <i class="bi bi-person-plus text-3xl text-gray-300"></i>
                            <p class="text-sm text-muted-foreground mt-2">{{ __('personal.event_show_byname_empty') }}</p>
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
@if($squadHasFees)
                    {{-- What the club is committing to, before it commits. --}}
                    <div x-show="squadTab === 'roster' && picked.length" x-cloak
                         class="flex items-center justify-between text-[12px]">
                        <span class="text-muted-foreground">{{ __('events.fee_squad_total') }}</span>
                        <span class="font-black text-foreground" x-text="squadTotalMoney()"></span>
                    </div>
@endif
                    {{-- An event that sells nothing keeps the shared path
                         untouched; only a priced one goes through the sheet's
                         own submit, which carries the option map. --}}
                    <button type="button" x-show="squadTab === 'roster'" @click="{{ $squadHasFees ? 'submitSquadEntries()' : 'submitEntries()' }}"
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
