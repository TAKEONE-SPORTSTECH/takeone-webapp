{{--
    Who has joined — participants, spectators and (for organisers) the blocked
    list. Its own partial because it now renders on its own page
    (personal.event-people), reached from a card on the event screen: on a phone
    a 48-name roster buried under the event detail is a lot to scroll past, and
    moderation deserves the whole screen.

    Expects $e, $canManage, $hasTicket, $byQual, and the Alpine state from
    partials.event-show-script (moderate(), goingCount, spectators,
    blockedCount) — so whatever includes this must sit inside that x-data.

    ===== $sheetOnly =====
    Included a second time, by the ENTRY LIST (personal.event-people), with
    `$sheetOnly = true`: that page renders its own roster of cards and wants
    only the PERSON SHEET from here — the weigh-in, the payment and the receipt
    for one entry, opened in place rather than by sending an organiser to
    another screen. Everything below the scope is skipped in that mode, so
    there is one sheet in the project and two places it can be opened from.
--}}
        @php
            $sheetOnly = $sheetOnly ?? false;
            $showTabs = ! $sheetOnly && ($hasTicket || ($canManage ?? false));

            $canWeigh = $canWeigh ?? false;
            $canPay = $canPay ?? false;
            $officiating = $canWeigh || $canPay;

            // flag-icons needs a lowercase ISO alpha-2 class. Normalised the same
            // way the bracket runtime does it, so a stray code can never emit a
            // broken `fi fi-` class. Returns '' when unusable.
            $flag = function ($code) {
                $c = strtolower(substr(preg_replace('/[^a-zA-Z]/', '', (string) $code), 0, 2));

                return strlen($c) === 2 ? '<span class="fi fi-'.$c.' rounded-sm shrink-0"></span>' : '';
            };

            // The live state of the two gates, keyed by user id so a server-
            // rendered row can bind to it. Built ONLY from rows the controller
            // decided this viewer may officiate (they carry reg_id); everyone
            // else gets an empty map and none of the controls below render.
            // ===== ONE GATE PER ENTRY, keyed by the REGISTRATION =====
            //
            // It used to be keyed by user id, which meant an athlete entered in
            // two divisions had ONE gate: the desk could sign for a weight and
            // approve a fee on one entry and had no door at all to the other,
            // silently. A fee, a receipt and a weigh-in signature all belong to
            // one entry, so each entry is its own gate — and the sheet carries
            // a switcher between the entries of the same person.
            //
            // The card, by contrast, is one per PERSON
            // (RosterPeople::byPerson). That is the whole
            // split: people on the list, entries at the desk.
            $entryGates = function (array $p): array {
                // The per-entry payloads the controller attached. A row it knew
                // nothing about falls back to the one flattened entry, which is
                // the shape every reader had before.
                if (! empty($p['entry_gates'])) {
                    return $p['entry_gates'];
                }

                return ($p['reg_id'] ?? null) ? [[
                    'reg_id' => $p['reg_id'],
                    'division' => $p['category'] ?? $p['weight_class'] ?? null,
                    'weigh_verified' => (bool) ($p['weighed_verified'] ?? false),
                    'pay_verified' => (bool) ($p['paid_verified'] ?? false),
                    'weight' => $p['weight'] ?? null,
                    'has_proof' => (bool) ($p['has_proof'] ?? false),
                    'proof_url' => $p['proof_url'] ?? null,
                    'fee_options' => $p['fee_options'] ?? [],
                    'fee_recorded' => (bool) ($p['fee_recorded'] ?? false),
                    'fee_charged' => $p['fee_charged'] ?? null,
                ]] : [];
            };

            $gates = collect($e['participants'] ?? [])
                ->filter(fn ($p) => ($p['id'] ?? null))
                // ⚠️ mapWithKeys, NOT flatMap: flatMap collapses, and collapsing
                // re-indexes the keys — the whole map came out as a plain ARRAY
                // and every gate lookup missed. mapWithKeys merges the several
                // pairs one person returns and keeps them keyed.
                ->mapWithKeys(function ($p) use ($entryGates, $canWeigh, $canPay) {
                    $entries = $entryGates($p);

                    // Every entry this person holds, for the switcher. Only when
                    // there is more than one — a single entry needs no chooser,
                    // and the sheet then looks exactly as it always did.
                    $siblings = count($entries) > 1
                        ? collect($entries)->map(fn ($en) => [
                            'reg' => $en['reg_id'],
                            'label' => $en['division'] ?: __('personal.event_people_action_verify'),
                        ])->values()->all()
                        : [];

                    return collect($entries)->mapWithKeys(fn ($en) => [$en['reg_id'] => [
                        'reg_id' => $en['reg_id'],
                        // Moderation acts on the PERSON, so the gate carries
                        // their id as a field now that it is no longer the key.
                        'user_id' => $p['id'],
                        'name' => $p['name'],
                        // The same identity line the row shows, so the sheet opens
                        // on the person you tapped rather than on a bare name. The
                        // division is THIS ENTRY's, which is how the sheet says
                        // which of the two you are signing for.
                        'meta' => implode(' · ', array_filter([
                            $p['gender'] ?? null, $en['division'] ?? null,
                        ])) ?: ($p['meta'] ?? ''),
                        'entries' => $siblings,
                        // Both roles get the signed/not-signed pair — that is what
                        // the "cleared for the draw" badge is made of.
                        'weigh_verified' => (bool) ($en['weigh_verified'] ?? false),
                        'pay_verified' => (bool) ($en['pay_verified'] ?? false),
                    ] + ($canWeigh ? [
                        'weight' => $en['weight'] ?? null,
                    ] : []) + ($canPay ? [
                        // No stored payment method: a member who paid online uploads
                        // a receipt, a member paying cash has nothing to upload. So
                        // the presence of a proof file IS how they paid, and the
                        // sheet asks the official a different question for each.
                        'has_proof' => (bool) ($en['has_proof'] ?? false),
                        'proof_url' => $en['proof_url'] ?? null,
                        // WHAT they are paying for. `fee_recorded` false means no
                        // amount was ever quoted for this entry — the case the
                        // picker below exists to fix.
                        'fee_options' => $en['fee_options'] ?? [],
                        'fee_recorded' => (bool) ($en['fee_recorded'] ?? false),
                        'fee_charged' => $en['fee_charged'] ?? null,
                    ] : [])])->all();
                })->all();
        @endphp
        {{-- No card around the whole list: each person is their own card, so the
             roster reads as a stack of people rather than one long slab. Only the
             heading and tabs are grouped. --}}
        {{-- The page header shows the readiness count, and it lives OUTSIDE this
             component. x-effect re-runs whenever readyCount/gateTotal/rtab change,
             so the header follows the desk without either side reaching into the
             other's scope. $dispatch bubbles, so the header listens on .window. --}}
        <div @if($officiating) x-effect="$dispatch('roster-readiness', { ready: readyCount, total: gateTotal, tab: rtab })" @endif
             x-init="deepLink()"
             @open-verification.window="openEntry($event.detail.entry)"
             x-data="{
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

                @if($officiating)
                {{-- ── Officiating ──────────────────────────────────────────
                     The two gates an entry passes before it can be drawn, run
                     from the roster row itself.

                     Emitted ONLY for the roles that sign them off. Not because
                     the endpoints would trust it — each one re-authorises — but
                     because an ordinary competitor has no use for a scale and a
                     receipt viewer, and shipping the wiring to them just puts
                     the shape of the officials' tools in everyone's page. --}}
                officiating: true,
                gates: @js($gates),
                vfilter: 'all',
                busy: null,
                sel: null,        {{-- user id whose action sheet is open --}}
                draft: '',        {{-- the weight being typed for them --}}

                {{-- ===== Arriving already focused on one entry =====

                     The entry list's own action sheet sends an organiser here
                     for the weigh-in and the payment of ONE person, and landing
                     on a desk of two hundred names to find them again is the
                     work the tap was meant to save. So `?entry=<registration>`
                     opens that person's sheet on arrival.

                     Keyed on the REGISTRATION id, which the entry list already
                     holds and already puts in the DOM — never the numeric user
                     id, which is not a public key (CLAUDE.md → Unpredictable
                     Resource Identifiers). It discloses nothing either way: the
                     sheet still renders only what this official may see, and a
                     stranger's id in the query just opens nothing.

                     The row is CLICKED rather than openPerson() being called
                     directly, so a row that is not tappable for this viewer
                     stays exactly that. --}}
                deepLink() {
                    const wanted = new URLSearchParams(window.location.search).get('entry');
                    if (! wanted) return;

                    this.$nextTick(() => {
                        /* ⚠️ NO DOUBLE QUOTES ANYWHERE IN THIS FILE. The whole
                           partial is the VALUE of an x-data attribute, so one
                           double quote — even inside a string or a comment —
                           closes that attribute early and dumps everything
                           after it onto the page as visible text. A
                           a querySelector with a quoted attribute selector did just that.
                           Matching by hand needs no quoting at all. */
                        const row = Array.from(document.querySelectorAll('[data-entry]'))
                            .find(el => el.getAttribute('data-entry') === String(wanted));
                        if (! row) return;
                        row.click();
                        row.scrollIntoView({ block: 'center' });
                    });
                },

                {{-- One sheet, opened from the row. Everything an official or
                     organiser can do to this person is in it, so the row itself
                     stays a single readable line. --}}
                {{-- Opened from the entry list's action sheet, which knows the
                     REGISTRATION rather than the user id (a numeric user id is
                     not a public key). Same sheet, same gate: a registration
                     with no gate for this viewer opens nothing. --}}
                {{-- The gates are keyed by the registration, so the id the
                     entry list hands over IS the key. --}}
                openEntry(reg) {
                    if (this.gates[reg]) this.openPerson(reg);
                },

                {{-- `uid` is now an ENTRY key (a registration id), not a user
                     id. The name is left alone because every caller and every
                     row below reads the same way — what changed is which thing
                     one sheet is about: one entry, not one person's several. --}}
                openPerson(uid) {
                    this.sel = uid;
                    this.draft = this.gates[uid]?.weight ?? '';
                    {{-- Seeded from whatever BeltRank resolved for this athlete,
                         so the official confirms a rank rather than re-typing one
                         the system already had. --}}
                    const belt = this.gates[uid]?.belt;
                    this.beltColour = belt?.colour ?? '';
                    this.beltGrade = belt?.grade ?? '';
                    {{-- The fee picker opens on what is on file. An entry with
                         nothing on file opens with nothing ticked, which is the
                         honest starting point: nobody knows what they paid for. --}}
                    this.feeDraft = (this.gates[uid]?.fee_options || []).slice();
                    this.feeTouched = false;
                },
                closePerson() { this.sel = null; this.draft = ''; this.beltColour = ''; this.beltGrade = ''; this.feeDraft = []; this.feeTouched = false; },
                get current() { return this.sel === null ? null : (this.gates[this.sel] || null); },

                gate(uid) { return this.gates[uid] || null; },
                {{-- Both signatures, and nothing else. The weight itself is not
                     part of this test: the weigh-in endpoint writes the weight
                     and the signature together, so `weigh_verified` already
                     implies one exists — and a payments official is not sent the
                     number to test against. --}}
                isReady(uid) {
                    const g = this.gates[uid];
                    return !!(g && g.pay_verified && g.weigh_verified);
                },
                get readyCount() { return Object.keys(this.gates).filter(u => this.isReady(u)).length; },
                get gateTotal() { return Object.keys(this.gates).length; },
                {{-- Search and the ready/pending filter both narrow the SAME
                     list rather than opening a second one. --}}
                passes(uid) {
                    if (! this.officiating || this.vfilter === 'all') return true;
                    return this.vfilter === 'ready' ? this.isReady(uid) : ! this.isReady(uid);
                },

                async send(url, body) {
                    const res = await fetch(url, {
                        method: 'PUT',
                        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json',
                                   'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                        credentials: 'same-origin',
                        body: JSON.stringify(body),
                    });
                    const d = await res.json().catch(() => ({}));
                    if (! res.ok || ! d.success) throw new Error(d.message || '{{ __('personal.event_verify_failed') }}');
                    return d;
                },

                {{-- The belt ladder, as chips. Free text on the server, so this
                     list is only the shortcut — a federation that grades some
                     other way types it into the grade field. --}}
                beltColour: '',
                beltGrade: '',
                {{-- ONE ladder, from App\Sports\Combat\BeltRank::ladder() — the
                     same list the athlete's own doors offer. It used to be a
                     Title-Case copy of it, so this desk wrote 'White' into a
                     column every other door writes 'white' into. --}}
                beltColours: @js(\App\Sports\Combat\BeltRank::ladder()),

                async weigh(uid) {
                    const g = this.gates[uid];
                    const weight = parseFloat(this.draft);
                    if (! g || ! (weight > 0)) { window.showToast('error', @js(__('personal.event_verify_bad_weight'))); return; }

                    this.busy = uid;
                    try {
                        {{-- Belt fields are only sent when the official filled
                             them in: an absent key leaves whatever is already on
                             the row alone, so re-weighing someone never silently
                             erases the rank recorded a minute earlier. --}}
                        const body = { weight };
                        if (this.beltColour) body.belt_colour = this.beltColour;
                        if (this.beltGrade.trim()) body.belt_grade = this.beltGrade.trim();

                        const d = await this.send(`{{ url('me/events/'.$e['key'].'/verify') }}/${g.reg_id}/weigh-in`, body);
                        g.weight = d.weight; g.weigh_verified = true;
                        if (d.belt !== undefined) g.belt = d.belt;

                        {{-- An athlete their club entered before anyone had a
                             weight for them is placed by THIS scale. Show the
                             division on the sheet straight away — and warn,
                             rather than congratulate, when the weight fits no
                             division this event is running. --}}
                        if (d.division) {
                            g.meta = [g.meta, d.division].filter(Boolean).join(' · ');
                        }
                        window.showToast(d.unplaced ? 'warning' : 'success', d.message);
                    } catch (e) { window.showToast('error', e.message); }
                    finally { this.busy = null; }
                },

                {{-- ── What the entry is being charged for ─────────────────
                     `feeDraft` is the working copy of the ticked boxes; it is
                     seeded when the sheet opens (openEntry) so the picker shows
                     what is on file, and only the SERVER prices it. --}}
                feeTypes: @js($feeTypes ?? []),
                feeDraft: [],
                feeSaving: false,
                {{-- Did the official actually touch the boxes? An untouched
                     sheet must never post `fee_options: []` with an approval:
                     the server reads an explicit empty list as "they chose
                     nothing", which records the entry as FREE, where the truth
                     is that nobody has said yet. --}}
                feeTouched: false,
                {{-- Its own formatter: the outer scope's money() reads the
                     finance payload, which this page does not load. --}}
                feeCurrency: @js($feeCurrency ?? 'BHD'),
                feeMoney(n) { return this.feeCurrency + ' ' + (parseFloat(n) || 0).toFixed(3); },

                toggleFee(key) {
                    this.feeTouched = true;
                    const i = this.feeDraft.indexOf(key);
                    if (i === -1) this.feeDraft.push(key);
                    else this.feeDraft.splice(i, 1);
                },

                {{-- A courtesy total, never the authority: the amount that gets
                     stored is the one the event's own rows produce. --}}
                get feeDraftTotal() {
                    return this.feeTypes
                        .filter((t) => this.feeDraft.includes(t.key))
                        .reduce((sum, t) => sum + Number(t.amount || 0), 0);
                },

                get feeDirty() {
                    const g = this.current;
                    if (! g) return false;
                    const was = (g.fee_options || []).slice().sort().join(',');

                    return was !== this.feeDraft.slice().sort().join(',') || ! g.fee_recorded;
                },

                async saveFees() {
                    const uid = this.sel, g = this.gates[uid];
                    if (! g || this.feeSaving) return;

                    this.feeSaving = true;
                    try {
                        const d = await this.send(
                            `{{ url('me/events/'.$e['key'].'/verify') }}/${g.reg_id}/fees`,
                            { fee_options: this.feeDraft }
                        );
                        g.fee_options = d.fee_options || [];
                        g.fee_recorded = true;
                        g.fee_charged = d.charged;
                        this.feeDraft = (g.fee_options || []).slice();
                        this.feeTouched = false;
                        window.showToast('success', d.message);
                    } catch (e) { window.showToast('error', e.message); }
                    finally { this.feeSaving = false; }
                },

                async pay(uid, approve) {
                    const g = this.gates[uid];
                    if (! g) return;

                    this.busy = uid;
                    try {
                        {{-- Approving carries the ticked types with it, so one
                             tap both takes the money and records what it was
                             for. Sent only on approval and only when the
                             official actually touched the picker: an untouched
                             sheet must not tell the server "they chose
                             nothing", which is a different statement from "we
                             do not know" (see recordFees). --}}
                        const body = { approve };

                        if (approve && this.feeTouched && this.feeTypes.length) {
                            body.fee_options = this.feeDraft;
                        }

                        const d = await this.send(`{{ url('me/events/'.$e['key'].'/verify') }}/${g.reg_id}/payment`, body);
                        g.pay_verified = approve;

                        {{-- The server answers with what it stored whenever the
                             approval wrote a record — so the picker and the
                             "recorded" line follow without a reload. --}}
                        if (d.fee_options) {
                            g.fee_options = d.fee_options;
                            g.fee_recorded = true;
                            g.fee_charged = d.charged;
                            if (this.sel === uid) { this.feeDraft = d.fee_options.slice(); this.feeTouched = false; }
                        }

                        window.showToast('success', d.message);
                    } catch (e) { window.showToast('error', e.message); }
                    finally { this.busy = null; }
                },

                {{-- Moderation runs through the event screen's own moderate()
                     (outer x-data), so removing someone behaves identically to
                     before. The sheet just has to get out of the way first —
                     its subject is about to leave the list. --}}
                async moderateFromSheet(action) {
                    const g = this.current;
                    if (! g) return;
                    this.closePerson();
                    {{-- Moderation is about the PERSON, and the gate key is an
                         entry — so the user id travels on the gate itself. --}}
                    await this.moderate(g.user_id, g.name, action);
                },
                @else
                {{-- Everyone else: the roster is a list of names. `passes()` is
                     the one hook the row markup calls unconditionally, so it
                     stays — and always says yes. --}}
                officiating: false,
                passes(uid) { return true; },
                @endif
             }">
        @unless($sheetOnly)
            {{-- One group, floating on the band's edge: the search and the list
                 switcher. These are the controls you always have, whichever list
                 you are on, so they share one tray. The competitor filter is NOT
                 in here — it belongs to the list below, not to the page. --}}
            <div class="mb-3">
                <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-2 space-y-2">
                    <div class="flex items-center gap-2">
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
                           class="w-full ps-8 pe-8 py-2 rounded-xl bg-muted border-0
                                  text-xs font-bold text-foreground transition-colors
                                  focus:ring-2 focus:ring-primary/40 outline-none">
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
                                class="m-press flex items-center gap-2 ps-2.5 pe-2 py-2 rounded-xl bg-muted
                                       text-xs font-bold text-foreground hover:bg-muted/70 transition-colors">
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
                    </div>{{-- /search + switcher row --}}
                </div>{{-- /tray --}}
            </div>

            @if($officiating)
                {{-- Out of the tray, on the page: the tray is the controls you
                     always have, this is a filter over the list right below it.
                     Two states, equal width — tapping the active one returns to
                     the whole roster, so there is no third "All" pill whose only
                     job is to undo another. On the page background they carry
                     their own white fill and border; muted would disappear. --}}
                <div class="flex gap-2 mb-3" x-show="rtab==='participants'" x-cloak x-transition>
                    @foreach(['pending' => __('personal.event_verify_pending'), 'ready' => __('personal.event_verify_ready')] as $key => $label)
                        <button type="button" @click="vfilter = (vfilter === '{{ $key }}' ? 'all' : '{{ $key }}')"
                                :aria-pressed="vfilter === '{{ $key }}'"
                                class="m-press flex-1 min-w-0 px-3 py-2 rounded-xl text-xs font-bold border transition-colors flex items-center justify-center gap-1.5"
                                :class="vfilter === '{{ $key }}'
                                    ? 'bg-primary text-white border-transparent shadow-sm shadow-primary/25'
                                    : 'bg-white text-muted-foreground border-gray-200 shadow-sm'">
                            <span class="truncate">{{ $label }}</span>
                            <span class="text-[10px] font-black tabular-nums px-1.5 py-0.5 rounded-full flex-shrink-0"
                                  :class="vfilter === '{{ $key }}' ? 'bg-white/20' : 'bg-muted'"
                                  x-text="{{ $key === 'ready' ? 'readyCount' : 'gateTotal - readyCount' }}"></span>
                        </button>
                    @endforeach
                </div>
            @endif


            {{-- Participants (competitors only) --}}
            <div class="mt-3 space-y-2.5" @if($showTabs) x-show="rtab==='participants'" x-transition @endif>
                @forelse($e['participants'] as $i => $pp)
                    @php $initials = collect(explode(' ', $pp['name']))->map(fn($p) => mb_substr($p, 0, 1))->take(2)->implode(''); @endphp
                    @php
                        $uid = $pp['id'] ?? null;
                        /* The gate's key is the ENTRY (see the $gates note
                           above), so every gate call below asks about this
                           entry; `$uid` stays for the row's own DOM id, which
                           is about the person. */
                        $gkey = $pp['reg_id'] ?? null;
                        $hasGate = $officiating && $gkey && $uid;
                        // One tap target per person. The row stays a single line
                        // whatever your role; everything you can DO to this
                        // person lives in the sheet it opens. A viewer with no
                        // actions gets an inert row, not a button that does
                        // nothing. Gated on $hasGate alone — an organiser always
                        // has both verify roles, so this covers moderation too,
                        // and a row with no gate would open an empty sheet.
                        $tappable = $hasGate;
                    @endphp
                    <div class="m-card rounded-2xl p-3 @if($tappable) cursor-pointer m-press hover:bg-muted/30 transition-colors @endif"
                         x-show="match(@js($pp['name'])) @if($hasGate) && passes({{ $gkey }}) @endif"
                         @if($hasGate) :class="isReady({{ $gkey }}) && 'border-green-200'" @endif
                         @if($tappable)
                             role="button" tabindex="0"
                             @click="openPerson({{ $gkey }})"
                             @keydown.enter.prevent="openPerson({{ $gkey }})"
                             @keydown.space.prevent="openPerson({{ $gkey }})"
                             aria-haspopup="dialog"
                         @endif
                         @if($uid) id="prow-{{ $uid }}" @endif
                         @if($pp['reg_id'] ?? null) data-entry="{{ $pp['reg_id'] }}" @endif>
                      <div class="flex items-center gap-3">
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
                            {{-- Only for the people who sign these off — and for
                                 your own row. The controller decides (show_status)
                                 and omits the underlying fields entirely for
                                 everyone else, so this cannot leak by accident.
                                 Suppressed on a row that has live gates below:
                                 these chips are a server-rendered snapshot and
                                 would go stale the moment an official acts. --}}
                            @if(($pp['show_status'] ?? false) && ! $hasGate)
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
                            @endif
                        </div>
                        @if($hasGate)
                            {{-- Whether this entry can be drawn, on the row that
                                 decides it — so an official never has to hold the
                                 answer and the buttons on two different screens. --}}
                            <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-black self-start"
                                  :class="isReady({{ $gkey }}) ? 'bg-green-50 text-green-600' : 'bg-amber-50 text-amber-600'"
                                  x-text="isReady({{ $gkey }}) ? @js(__('personal.event_verify_in_draw')) : @js(__('personal.event_verify_held'))"></span>
                        @endif
                        @if($tappable)
                            {{-- Affordance only — the whole card is the target. --}}
                            <i class="bi bi-chevron-right text-muted-foreground text-xs flex-shrink-0"></i>
                        @endif
                      </div>
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
                            {{-- Ticket state is staff-only (and your own row);
                                 everyone else just sees that they hold a ticket. --}}
                            @if($sp['show_status'] ?? false)
                                @if(($sp['paid'] ?? true))
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-sky-50 text-sky-600 flex-shrink-0"><i class="bi bi-ticket-perforated"></i> {{ str_contains(strtolower($e['spectator']['fee']),'free') ? __('personal.event_show_pass') : __('personal.event_show_ticket') }}</span>
                                @else
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-amber-50 text-amber-600 flex-shrink-0"><i class="bi bi-hourglass-split"></i> {{ __('personal.event_show_pending') }}</span>
                                @endif
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

            @endunless

            @if($officiating)
                {{-- ===== The person sheet =====
                     Tapping a roster row opens this. It is the whole reason the
                     row could go back to one line: the two gates, the receipt,
                     and moderation all live here, for one person at a time.

                     Teleported to <body> per the mobile-forms rule — the roster
                     scrolls inside a transformed shell ancestor, and a fixed
                     overlay left inside it would be clipped. Scrollable body,
                     safe-area footer. --}}
                <template x-teleport="body" data-teleport-template="true">
                    <div x-show="sel !== null" x-cloak
                         class="fixed inset-0 z-[70] flex items-end justify-center"
                         @keydown.escape.window="closePerson()" role="dialog" aria-modal="true" style="display:none;">
                        <div x-show="sel !== null" x-transition.opacity class="absolute inset-0 bg-black/50" @click="closePerson()"></div>

                        <div x-show="sel !== null"
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="translate-y-full"
                             x-transition:enter-end="translate-y-0"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="translate-y-0"
                             x-transition:leave-end="translate-y-full"
                             class="relative w-full sm:max-w-md max-h-[92vh] sm:max-h-[85vh] flex flex-col bg-white rounded-t-3xl shadow-2xl">

                            {{-- Who --}}
                            <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                                 style="background: linear-gradient(150deg, {{ $e['color'] }}, {{ $e['color'] }}b0);">
                                <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                                <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>

                                <div class="relative flex items-start gap-3">
                                    <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                                        <i class="bi bi-person-badge text-xl"></i>
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <h3 class="text-lg font-black leading-tight truncate" x-text="current?.name"></h3>
                                        <p class="text-[12px] text-white/85 mt-0.5 truncate" x-text="current?.meta"></p>
                                    </div>
                                    <button type="button" @click="closePerson()" aria-label="{{ __('shared.close') }}"
                                            class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>

                                <div class="relative mt-3 flex flex-wrap gap-1.5">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/20 text-[11px] font-bold"
                                          x-text="isReady(sel) ? @js(__('personal.event_verify_in_draw')) : @js(__('personal.event_verify_held'))"></span>
                                </div>

                                {{-- ===== The same athlete's OTHER entries =====

                                     One person, two entries — a Gi group and a
                                     No-Gi group at the same championship. The
                                     list outside shows them as ONE card, because
                                     they are one person; the desk signs for one
                                     ENTRY at a time, because that is what a fee
                                     and a scale reading belong to.

                                     So the entries are chips here: tapping one
                                     re-opens the sheet on it. A tick on a chip
                                     is that entry already cleared, so an
                                     official can see at a glance which half of
                                     the work is left. Rendered only when there
                                     is more than one — a single entry needs no
                                     chooser and the sheet is unchanged. --}}
                                <template x-if="(current?.entries || []).length > 1">
                                    <div class="relative mt-3">
                                        <p class="text-[10px] font-bold uppercase tracking-wide text-white/70">{{ __('personal.event_verify_entries') }}</p>
                                        <div class="mt-1.5 flex flex-wrap gap-1.5">
                                            <template x-for="en in current.entries" :key="en.reg">
                                                <button type="button" @click="openPerson(en.reg)"
                                                        class="m-press inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold border transition-colors"
                                                        :class="String(en.reg) === String(sel)
                                                            ? 'bg-white text-foreground border-white'
                                                            : 'bg-white/15 text-white border-white/30 hover:bg-white/25'">
                                                    <i class="bi text-[10px]"
                                                       :class="isReady(en.reg) ? 'bi-check-circle-fill' : 'bi-circle'"></i>
                                                    <span x-text="en.label"></span>
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">

                                {{-- ── Gate 1 · weigh-in ───────────────────────────
                                     The WHOLE section is for the weigh-in role (or
                                     the organiser, who holds every role). A payments
                                     official has no business reading a child's body
                                     weight, so they do not get the section at all —
                                     not a version of it with the button removed.
                                     `canWeigh` is EventAccess::canVerifyWeighIn, the
                                     same check the endpoint enforces.

                                     The official is standing at the scale reading a
                                     number off it, so the field is the section — no
                                     extra tap to reveal it. --}}
                                @if($canWeigh)
                                    <div class="rounded-2xl border border-gray-200 p-4">
                                        <div class="flex items-center gap-2.5">
                                            <i class="bi text-lg shrink-0"
                                               :class="current?.weigh_verified ? 'bi-check-circle-fill text-green-600' : 'bi-circle text-muted-foreground'"></i>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-bold text-foreground">{{ __('personal.event_verify_weigh_in') }}</p>
                                                <p class="text-[11px] text-muted-foreground"
                                                   x-text="current?.weight
                                                            ? current.weight + ' kg' + (current.weigh_verified ? '' : ' · ' + @js(__('personal.event_verify_self_declared')))
                                                            : @js(__('personal.event_verify_no_weight'))"></p>
                                            </div>
                                        </div>

                                        <div class="mt-3 flex items-center gap-2">
                                            <div class="relative flex-1">
                                                <input id="sheet-weight" type="number" inputmode="decimal" step="0.1" min="10" max="250"
                                                       x-model="draft" @keydown.enter.prevent="weigh(sel)"
                                                       class="w-full h-11 ps-3 pe-10 rounded-xl border-2 border-gray-200 text-sm font-bold text-foreground
                                                              focus:outline-none focus:border-current"
                                                       style="caret-color: {{ $e['color'] }};"
                                                       placeholder="{{ __('personal.event_verify_enter_weight') }}">
                                                <span class="absolute inset-y-0 end-3 flex items-center text-[11px] font-black text-muted-foreground">kg</span>
                                            </div>
                                            <button type="button" @click="weigh(sel)" :disabled="busy === sel"
                                                    class="m-press h-11 px-4 rounded-xl text-white text-xs font-black disabled:opacity-60 shrink-0"
                                                    style="background: {{ $e['color'] }};"
                                                    x-text="current?.weigh_verified ? @js(__('personal.event_verify_reweigh')) : @js(__('personal.event_verify_mark_weighed'))"></button>
                                        </div>

                                        {{-- ── Belt, recorded at the same desk ──────────
                                             Rank is announced on the arena screen, and for
                                             most athletes the system already knows it from
                                             a certification — so this shows what it will
                                             announce and the official only touches it when
                                             it is wrong or missing. The scale is where an
                                             athlete is physically in front of an official,
                                             which makes it the one moment rank can actually
                                             be checked rather than taken on trust.

                                             Chips, not a dropdown: this sheet body scrolls,
                                             and an absolutely-positioned panel would be
                                             clipped by it (Mobile Pattern Language §3). A
                                             colour is also the thing you recognise fastest
                                             by eye, which is the whole point of a belt. --}}
                                        <div class="mt-3 pt-3 border-t border-gray-100">
                                            <div class="flex items-center justify-between gap-2">
                                                <p class="text-[11px] font-bold text-muted-foreground uppercase tracking-wide">{{ __('personal.event_verify_belt') }}</p>
                                                <p class="text-[11px] text-muted-foreground truncate"
                                                   x-show="current?.belt && current.belt.source !== 'weigh_in'"
                                                   x-text="@js(__('personal.event_verify_belt_on_file')) + ': ' + (current?.belt?.label || '')"></p>
                                            </div>

                                            <div class="mt-2 flex flex-wrap gap-1.5">
                                                <template x-for="c in beltColours" :key="c.value">
                                                    <button type="button" @click="beltColour = (beltColour === c.value ? '' : c.value)"
                                                            class="m-press h-8 px-3 rounded-lg text-[11px] font-black border-2 transition-colors"
                                                            :class="beltColour === c.value ? 'border-current' : 'border-transparent opacity-70'"
                                                            :style="`background:${c.bg}; color:${c.fg};`"
                                                            x-text="c.label"></button>
                                                </template>
                                            </div>

                                            <input type="text" x-model="beltGrade" maxlength="40"
                                                   @keydown.enter.prevent="weigh(sel)"
                                                   class="mt-2 w-full h-10 px-3 rounded-xl border-2 border-gray-200 text-sm font-bold text-foreground
                                                          focus:outline-none focus:border-current"
                                                   style="caret-color: {{ $e['color'] }};"
                                                   placeholder="{{ __('personal.event_verify_belt_grade_hint') }}">
                                        </div>
                                    </div>
                                @endif

                                {{-- ── Gate 2 · payment ────────────────────────────
                                     Same rule as the weigh-in above: the whole
                                     section belongs to the payments role (or the
                                     organiser). A weigh-in official never sees
                                     another family's money — and, since the
                                     receipt lives in here, never sees the bank
                                     transfer either. `canPay` is
                                     EventAccess::canVerifyPayments, the check the
                                     endpoint and the proof stream both enforce.

                                     Two different questions, so two different
                                     answers. A receipt on file means they paid
                                     online: the job is to LOOK at it, so it is
                                     shown, not linked. No receipt means cash at the
                                     club: nothing to inspect, just a fact to
                                     confirm. --}}
                                @if($canPay)
                                    <div class="rounded-2xl border border-gray-200 p-4">
                                        <div class="flex items-center gap-2.5">
                                            <i class="bi text-lg shrink-0"
                                               :class="current?.pay_verified ? 'bi-check-circle-fill text-green-600' : 'bi-circle text-muted-foreground'"></i>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-bold text-foreground">{{ __('personal.event_verify_payment') }}</p>
                                                <p class="text-[11px] text-muted-foreground"
                                                   x-text="current?.pay_verified ? @js(__('personal.event_verify_approved'))
                                                           : (current?.has_proof ? @js(__('personal.event_verify_paid_online')) : @js(__('personal.event_verify_paying_cash')))"></p>
                                            </div>
                                        </div>

                                        {{-- Paid online: the transaction file itself. --}}
                                        <template x-if="current?.proof_url">
                                            <div class="mt-3">
                                                <p class="text-[11px] text-muted-foreground mb-1.5">
                                                    {{ __('personal.event_verify_check_against') }}
                                                    <span class="font-bold text-foreground">{{ $payment['bank']['iban'] ?? ($payment['club'] ?? '') }}</span>
                                                </p>
                                                <div class="rounded-xl overflow-hidden border border-gray-200 bg-muted/30">
                                                    <img :src="current.proof_url" alt="{{ __('personal.event_verify_view_proof') }}" class="w-full object-contain max-h-72">
                                                </div>
                                            </div>
                                        </template>

                                        {{-- Paying cash: nothing to inspect. --}}
                                        <template x-if="! current?.has_proof && ! current?.pay_verified">
                                            <p class="mt-3 text-[11px] text-muted-foreground flex items-start gap-1.5">
                                                <i class="bi bi-cash-stack mt-0.5"></i>
                                                <span>{{ __('personal.event_verify_cash_hint') }}</span>
                                            </p>
                                        </template>

                                        {{-- ── What they are paying FOR ─────────────
                                             The event's own price list, ticked by
                                             the official. Selection cards, never a
                                             dropdown, inside a scrolling sheet
                                             (Mobile Pattern Language).

                                             It matters beyond tidiness: an entry
                                             taken at the door carries no fee line
                                             at all, so the event reports revenue
                                             it cannot attribute and values that
                                             entry at a guess. Ticking a type here
                                             writes the real record — the SERVER
                                             prices it from its own rows, so
                                             nothing here can invent an amount. --}}
                                        <template x-if="feeTypes.length">
                                            <div class="mt-3 pt-3 border-t border-gray-100">
                                                <p class="text-[11px] font-bold text-foreground mb-2">{{ __('personal.event_verify_fees_title') }}</p>

                                                <div class="space-y-1.5">
                                                    <template x-for="t in feeTypes" :key="t.key">
                                                        <button type="button" @click="toggleFee(t.key)"
                                                                class="m-press w-full flex items-center gap-2.5 px-3 py-2.5 rounded-xl border-2 text-start transition-colors"
                                                                :class="feeDraft.includes(t.key) ? 'border-primary bg-primary/5' : 'border-gray-200'">
                                                            <span class="w-5 h-5 rounded-md border-2 grid place-items-center flex-shrink-0"
                                                                  :class="feeDraft.includes(t.key) ? 'border-primary bg-primary text-white' : 'border-gray-300'">
                                                                <i class="bi bi-check text-[11px]" x-show="feeDraft.includes(t.key)"></i>
                                                            </span>
                                                            <span class="min-w-0 flex-1 text-[13px] font-bold text-foreground truncate" x-text="t.label"></span>
                                                            <span class="text-[12px] font-black flex-shrink-0" style="color: {{ $e['color'] }};" x-text="t.label_amount"></span>
                                                        </button>
                                                    </template>
                                                </div>

                                                <div class="flex items-center justify-between mt-2.5">
                                                    <span class="text-[11px] text-muted-foreground"
                                                          x-text="current?.fee_recorded
                                                                    ? @js(__('personal.event_verify_fees_on_file'))
                                                                    : @js(__('personal.event_verify_fees_none'))"></span>
                                                    <span class="text-[13px] font-black text-foreground" x-text="feeMoney(feeDraftTotal)"></span>
                                                </div>

                                                <button type="button" @click="saveFees()" x-show="feeDirty" x-cloak
                                                        :disabled="feeSaving"
                                                        class="m-press w-full h-10 mt-2 rounded-xl text-white text-xs font-black disabled:opacity-60"
                                                        style="background: {{ $e['color'] }};">
                                                    {{ __('personal.event_verify_fees_save') }}
                                                </button>
                                            </div>
                                        </template>

                                        <div class="mt-3 flex items-center gap-2">
                                            {{-- Approved already → the only remaining
                                                 move is to take it back. --}}
                                            <button type="button" x-show="current?.pay_verified" x-cloak
                                                    @click="pay(sel, false)" :disabled="busy === sel"
                                                    class="m-press flex-1 h-11 rounded-xl border border-gray-200 text-red-600 text-xs font-black disabled:opacity-60">
                                                {{ __('personal.event_verify_revoke') }}
                                            </button>

                                            <template x-if="! current?.pay_verified">
                                                <div class="flex-1 flex items-center gap-2">
                                                    <button type="button" x-show="current?.has_proof"
                                                            @click="pay(sel, false)" :disabled="busy === sel"
                                                            class="m-press flex-1 h-11 rounded-xl border border-gray-200 text-red-600 text-xs font-black disabled:opacity-60">
                                                        {{ __('personal.event_verify_reject') }}
                                                    </button>
                                                    <button type="button" @click="pay(sel, true)" :disabled="busy === sel"
                                                            class="m-press flex-1 h-11 rounded-xl text-white text-xs font-black disabled:opacity-60"
                                                            style="background: {{ $e['color'] }};"
                                                            x-text="current?.has_proof ? @js(__('personal.event_verify_approve')) : @js(__('personal.event_verify_confirm_cash'))"></button>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                @endif

                                @if($canManage ?? false)
                                    {{-- ── Moderation ──────────────────────────────
                                         What the three-dots menu on the row used to
                                         hold. Last in the sheet and visually
                                         separated: these end someone's participation
                                         and should never sit under the thumb next to
                                         "confirm cash". Each still confirms through
                                         the shared dialog. --}}
                                    <div class="pt-1">
                                        <p class="text-[10px] font-black uppercase tracking-[0.14em] text-muted-foreground mb-2">
                                            {{ __('personal.event_show_manage') }}
                                        </p>
                                        <div class="rounded-2xl border border-gray-200 divide-y divide-gray-100 overflow-hidden">
                                            <button type="button" @click="moderateFromSheet('remove')"
                                                    class="w-full px-4 py-3 text-xs font-bold text-foreground hover:bg-muted flex items-center gap-2.5">
                                                <i class="bi bi-person-dash"></i>{{ __('personal.event_show_remove_btn_long') }}
                                            </button>
                                            <button type="button" @click="moderateFromSheet('block')"
                                                    class="w-full px-4 py-3 text-xs font-bold text-amber-600 hover:bg-amber-50 flex items-center gap-2.5">
                                                <i class="bi bi-slash-circle"></i>{{ __('personal.event_show_block_btn_long') }}
                                            </button>
                                            <button type="button" @click="moderateFromSheet('blacklist')"
                                                    class="w-full px-4 py-3 text-xs font-bold text-red-600 hover:bg-red-50 flex items-center gap-2.5">
                                                <i class="bi bi-ban"></i>{{ __('personal.event_show_blacklist_btn_long') }}
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div class="flex-shrink-0 px-5 pt-3 border-t border-gray-100"
                                 style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                                <button type="button" @click="closePerson()"
                                        class="w-full py-3 rounded-xl border border-gray-200 text-foreground font-bold text-sm">
                                    {{ __('personal.event_show_done') }}
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            @endif
        </div>
