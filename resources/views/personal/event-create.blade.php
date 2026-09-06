{{-- `$shell` is shared ONLY on the sealed event routes (/e/{uuid}/admin/…), so with
     nothing shared this is the member shell exactly as before. See entry/shell. --}}
@extends($shell ?? 'layouts.personal-mobile')

@section('title', ($mode ?? 'create') === 'edit' ? __('personal.personal_event_create_page_title_edit') : __('personal.personal_event_create_page_title_new'))

{{--
    Create / edit an event — SCHEMA-DRIVEN (config/event_schema.php).
    The selected event TYPE decides which sections show; the SPORT adapts the
    terminology (weight categories vs draws vs groups…) and structures
    (divisions for combat/athletics/racquet, teams+fixtures for leagues, belts
    for belt tests). POSTs to me.events.store / PUTs to me.events.update.
--}}
@php
    $mode   = $mode ?? 'create';
    $isEdit = $mode === 'edit';
    $ev = $event ?? null;
    $sportsByFamily = collect($schema['sports'])->groupBy('family');
    $initTenant  = $ev?->tenant_id ?? (!empty($clubs) ? $clubs[0]['id'] : 'null');
    $initStart   = $ev?->start_time ? \Carbon\Carbon::parse($ev->start_time)->format('H:i') : '';
    $initEnd     = $ev?->end_time ? \Carbon\Carbon::parse($ev->end_time)->format('H:i') : '';
    // A NEW event is paid by default — an organiser opts INTO free, rather than
    // opting out of charging. Editing always reflects what the event actually is.
    $partFree    = $ev ? empty($ev->participant_fee) : false;
    // Same idea for spectators: tickets are on by default on a new event.
    $specOn      = $ev ? (bool) $ev->spectator_enabled : true;
    // uuid, not id: these routes bind {event:uuid}. A GET by id happens to be
    // redirected to the uuid form, which hid this — but PUT by id 404s, so
    // "Save changes" was silently failing.
    /*
     * ⚠️ INSIDE THE SEALED EVENT APP, THE EVENT ROOT NEEDS ITS OWN ADDRESS.
     *
     * PublicEventSkin::rewriteBody() maps a bare, quoted `/me/events/{uuid}` to
     * the public POSTER (`/e/{uuid}`), because inside the app the event page IS
     * the poster. That is right for a LINK and wrong for an ENDPOINT: the
     * poster route only answers GET, so this form's PUT was rewritten onto it
     * and came back 404 — "Save changes" reported Not found every time
     * (reported 2026-09-04). Anything DEEPER (`/register`, `/expenses`, …) is
     * rewritten into `/admin/…` correctly and is unaffected.
     *
     * So in the sealed shell the endpoint is named directly, as the mirrored
     * admin root — the same route, the same controller, the same middleware
     * stack (App\Events\Support\SealedEventRoutes). `$shell` is shared ONLY on
     * the mirrored routes, so the platform form is untouched.
     */
    $submitUrl   = $isEdit
        ? (isset($shell) ? url('/e/'.$ev->uuid.'/admin') : route('me.events.update', $ev->uuid))
        : route('me.events.store');
    $submitMethod = $isEdit ? 'PUT' : 'POST';
    // belt range parsed out of `level` ("White → Brown")
    $beltFrom = ''; $beltTo = '';
    if ($ev && $ev->level && str_contains($ev->level, '→')) {
        [$beltFrom, $beltTo] = array_map('trim', explode('→', $ev->level, 2));
    }
    $initDivisions = $divisions ?? [];
    $initLeague = $ev?->league ?? ['teams' => [], 'fixtures' => []];
    // The stated amounts. Read from the columns — the old scrape out of the
    // display string is what Phase 2 of the entry/billing work removed.
    $trim = fn ($n) => $n === null ? '' : rtrim(rtrim(number_format((float) $n, 3, '.', ''), '0'), '.');
    $pAmt = $ev ? $trim(\App\Events\Support\EventFee::amount($ev, 'participant')) : '';
    $sAmt = $ev ? $trim(\App\Events\Support\EventFee::amount($ev, 'spectator')) : '';

    /* The priced extras an entrant may add on top of the base fee, and the
       penalty for entering late (multi-pricing, 2026-09-06).

       Seeded from the ACTIVE options only: a retired one is kept in the table so
       an old charge can still say what it was for, but it is not something the
       organiser is still editing. Sent back with its uuid so saving edits the
       row rather than retiring it and creating a stranger with the same price. */
    $rows = fn ($role) => $ev
        ? \App\Events\Support\EventFee::options($ev, $role)
            ->map(fn ($o) => ['uuid' => $o->uuid, 'label' => $o->label, 'amount' => $trim($o->amount)])
            ->values()->all()
        : [];

    $initFeeOptions = $rows('participant');
    $initSpectatorOptions = $rows('spectator');

    /* An event priced BEFORE the fee list existed carries its number in
       `*_fee_amount` and has no options at all. Seed that number as a row so the
       organiser opens the form and sees the price they set, rather than an empty
       list that reads as "this event is free".

       Saving then turns it into a real option and clears the old column — the
       same total, expressed the one way the model now understands. Nothing is
       converted until they actually save. */
    if ($ev && ! $initFeeOptions && ($legacy = \App\Events\Support\EventFee::amount($ev, 'participant')) > 0) {
        $initFeeOptions = [['uuid' => null, 'label' => __('events.fee_line_entry'), 'amount' => $trim($legacy)]];
    }

    if ($ev && ! $initSpectatorOptions && ($legacyS = \App\Events\Support\EventFee::amount($ev, 'spectator')) > 0) {
        $initSpectatorOptions = [['uuid' => null, 'label' => __('events.fee_line_ticket'), 'amount' => $trim($legacyS)]];
    }
    $lateAmt = $ev ? $trim($ev->late_fee_amount) : '';
    $lateFrom = $ev?->late_fee_from ? $ev->late_fee_from->format('Y-m-d') : '';
    $lateTime = $ev?->late_fee_from ? $ev->late_fee_from->format('H:i') : '23:59';
@endphp
@section('personal-content')
<div x-data="{
        sending: false,
        isEdit: {{ $isEdit ? 'true' : 'false' }},
        schema: @js($schema),
        step: {{ $isEdit ? 2 : 1 }},
        mode: @js($ev ? ($ev->sport ? 'sport' : 'generic') : null),
        picked: @js($ev?->sport ?? ($isEdit ? 'general' : null)),
        sportSearch2: '',
        tenant_id: {{ $initTenant }},
        type: @js($ev?->event_type ?? 'class'),
        scope: @js($ev?->scope ?? 'internal'),
        sport: @js($ev?->sport ?? ''),
        title: @js($ev?->title ?? ''),
        date: @js($ev?->date?->format('Y-m-d') ?? ''),
        end_date: @js($ev?->end_date?->format('Y-m-d') ?? ''),
        start_time: @js($initStart),
        end_time: @js($initEnd),
        weigh_in_at: @js($ev?->weigh_in_at ? \Carbon\Carbon::parse($ev->weigh_in_at)->format('Y-m-d\TH:i') : ''),
        enrollment_starts: @js($ev?->enrollment_starts_at?->format('Y-m-d') ?? ($isEdit ? '' : now()->format('Y-m-d'))),
        enrollment_ends: @js($ev?->enrollment_ends_at?->format('Y-m-d') ?? ''),
        {{-- Weight tables come from the Taekwondo package's own catalogue. This
             division picker is the last type-specific block left in the shared
             create form; Phase 2 moves it into the package's own screen. --}}
        /* Every package's weight table, keyed by package. The form used to be
           handed `taekwondo_tournament` and nothing else, so a jiu-jitsu event
           offered Taekwondo Senior classes -- Fin, Fly, Bantam -- for a sport
           that grades by belt and calls its groups Adult and Master. Each
           package publishes its own via formCatalog(); the form now asks for
           the one belonging to THIS event. */
        catalogs: @js(collect($catalogs ?? [])->map(fn ($c) => $c['weight_divisions'] ?? [])->filter()->all()),
        presetsOpen: false,
        tkdAge: 'Senior',
        tkdGender: 'male',
        tkdChecked: {},
        location: @js($ev?->location ?? ''),
        location_url: @js($ev?->location_url ?? ''),
        gps_lat: @js($ev?->gps_lat ? (string) $ev->gps_lat : ''),
        gps_long: @js($ev?->gps_long ? (string) $ev->gps_long : ''),
        locMode: @js($ev?->location_url ? 'url' : 'map'),
        break_enabled: {{ ($ev && ($ev->break_start || $ev->break_end)) ? 'true' : 'false' }},
        break_start: @js($ev?->break_start ? \Carbon\Carbon::parse($ev->break_start)->format('H:i') : ''),
        break_end: @js($ev?->break_end ? \Carbon\Carbon::parse($ev->break_end)->format('H:i') : ''),
        courts: @js((string) ($ev?->courts ?? '')),
        sportOpen: false,
        sportSearch: '',
        level: @js($ev?->level ?? ''),
        description: @js($ev?->description ?? ''),
        clubs: @js($clubs),
        participant_free: {{ $partFree ? 'true' : 'false' }},
        participant_amount: @js($pAmt),
        fee_options: @js($initFeeOptions),
        spectator_options: @js($initSpectatorOptions),
        late_amount: @js($lateAmt),
        late_from: @js($lateFrom),
        late_time: @js($lateTime),
        spectator_enabled: {{ $specOn ? 'true' : 'false' }},
        spectator_amount: @js($sAmt),
        max_capacity: @js((string) ($ev?->max_capacity ?? '')),
        prize: @js($ev?->prize ?? ''),
        agenda: @js($ev?->agenda ?? []),
        requirements: @js($ev?->requirements ?? []),
        tagsText: @js($ev ? implode(', ', $ev->tags ?? []) : ''),
        phases: @js($ev?->phases ?? []),
        divisions: @js($initDivisions),
        league: @js($initLeague),
        beltFrom: @js($beltFrom),
        beltTo: @js($beltTo),

        get typeMeta() { return this.schema.types[this.type] || {}; },
        get sections() { return this.typeMeta.sections || []; },
        has(s) { return this.sections.includes(s); },
        get sportMeta() { return this.schema.sports[this.sport] || {}; },
        get divisionLabel() { return this.sportMeta.division_label || 'Category'; },
        get hasBelts() { return !!this.sportMeta.belts; },
        get color() { return this.typeMeta.color || '#7c3aed'; },

        // ----- Day × phase scheduling (combat brackets) -----
        phaseDefs: [{ key: 'preliminary', label: 'Prelim' }, { key: 'quarterfinals', label: 'Quarters' }, { key: 'finals', label: 'Finals' }],
        get dayCount() {
            if (!this.date || !this.end_date) return 1;
            const a = new Date(this.date), b = new Date(this.end_date);
            return Math.max(1, Math.round((b - a) / 86400000) + 1);
        },
        get days() { return Array.from({ length: this.dayCount }, (_, i) => i + 1); },
        get isCombat() { return this.sportMeta.family === 'Combat'; },
        get isChampionship() { return ['championship', 'tournament'].includes(this.type); },
        get currency() { const c = (this.clubs || []).find(c => c.id === this.tenant_id); return (c && c.currency) ? c.currency : 'BHD'; },
        // ----- Step 1: generic vs sport, then the sport filter -----
        sportCards() {
            const q = (this.sportSearch2 || '').toLowerCase().trim();
            const cards = [];
            Object.entries(this.schema.sports).forEach(([key, sp]) => cards.push({ key, label: sp.label, icon: sp.icon || 'bi-trophy', family: sp.family }));
            return q ? cards.filter(c => c.label.toLowerCase().includes(q) || (c.family || '').toLowerCase().includes(q)) : cards;
        },
        // A door is not a selection to confirm — clicking it goes through.
        // Generic has nothing left to ask, so it lands on the form directly;
        // Sport still needs the sport, so it opens the list and the sport card
        // is what goes through.
        pickGeneric() { this.mode = 'generic'; this.picked = 'general'; this.goNext(); },
        pickSportMode() { this.mode = this.mode === 'sport' ? null : 'sport'; if (this.picked === 'general') this.picked = null; },
        pickSport(key) { this.mode = 'sport'; this.picked = key; this.goNext(); },
        get canNext() {
            if (this.mode === 'generic') return true;
            if (this.mode === 'sport') return !!this.picked && this.picked !== 'general';
            return false;
        },
        goNext() {
            if (!this.canNext) return;
            if (this.mode === 'generic') { this.sport = ''; this.type = 'class'; }
            else { this.sport = this.picked; this.type = 'championship'; }
            this.normalizeDivisions();
            this.step = 2;
        },
        get pickedLabel() {
            if (this.mode === 'generic' || this.picked === 'general') return 'General event';
            return this.schema.sports[this.picked]?.label || 'Event';
        },
        // Searchable sport list grouped by family (uses correct slug keys — fixes the old groupBy bug).
        sportGroups() {
            const q = (this.sportSearch || '').toLowerCase();
            const groups = {};
            Object.entries(this.schema.sports).forEach(([key, sp]) => {
                if (q && !sp.label.toLowerCase().includes(q)) return;
                (groups[sp.family] = groups[sp.family] || []).push([key, sp]);
            });
            return Object.entries(groups);
        },
        get weighInDate() { return this.weigh_in_at ? this.weigh_in_at.slice(0, 10) : ''; },
        // Forward cascade — enrollment start drives everything after it:
        // enrollment_start → enrollment_end → weigh-in → start → end (each must be ≥ the one before).
        clampDates() {
            if (this.enrollment_starts && this.enrollment_ends && this.enrollment_ends < this.enrollment_starts) this.enrollment_ends = this.enrollment_starts;
            const eEnd = this.enrollment_ends || this.enrollment_starts;
            if (eEnd && this.weighInDate && this.weighInDate < eEnd) this.weigh_in_at = eEnd + 'T09:00';
            const beforeStart = this.weighInDate || eEnd;
            if (beforeStart && this.date && this.date < beforeStart) this.date = beforeStart;
            if (this.date && this.end_date && this.end_date < this.date) this.end_date = this.date;
        },
        get hasPhaseSchedule() { return this.sportMeta.family === 'Combat' && this.has('divisions'); },
        normalizeDivisions() {
            this.divisions.forEach(d => { if (!d.schedule) d.schedule = { preliminary: 1, quarterfinals: 1, finals: 1 }; });
        },
        // A later phase can't be on an earlier day than an earlier phase.
        clampDivSchedule(d) {
            if (!d.schedule) return;
            d.schedule.quarterfinals = Math.max(d.schedule.quarterfinals, d.schedule.preliminary);
            d.schedule.finals = Math.max(d.schedule.finals, d.schedule.quarterfinals);
        },

        addAgenda() { this.agenda.push({ t: '', d: '' }); },
        removeAgenda(i) { this.agenda.splice(i, 1); },
        // Schedule items pick a date+time inside the event window.
        schedMin() { return this.date ? (this.date + 'T' + (this.start_time || '00:00')) : ''; },
        schedMax() { const d = this.end_date || this.date; return d ? (d + 'T' + (this.end_time || '23:59')) : ''; },
        fixAgenda() {
            const lo = this.schedMin(), hi = this.schedMax();
            for (const a of this.agenda) {
                if (!a.t) continue;
                if (lo && a.t < lo) a.t = lo;
                if (hi && a.t > hi) a.t = hi;
            }
        },
        addReq() { this.requirements.push(''); },
        removeReq(i) { this.requirements.splice(i, 1); },
        addPhase() { this.phases.push({ label: '', date: '', note: '' }); },
        removePhase(i) { this.phases.splice(i, 1); },
        // Keep phase dates within the event window and never earlier than the previous stage.
        fixPhaseDates() {
            const lo = this.date || '';
            const hi = this.end_date || this.date || '';
            for (let i = 0; i < this.phases.length; i++) {
                let d = this.phases[i].date;
                if (!d) continue;
                if (lo && d < lo) d = lo;
                if (hi && d > hi) d = hi;
                if (i > 0 && this.phases[i-1].date && d < this.phases[i-1].date) d = this.phases[i-1].date;
                this.phases[i].date = d;
            }
        },
        // Status is CALCULATED from the date vs today — not editable.
        phaseStatus(p) {
            if (!p.date) return 'upcoming';
            const t = new Date().toISOString().slice(0, 10);
            return p.date < t ? 'done' : (p.date === t ? 'active' : 'upcoming');
        },
        phaseStatusLabel(p) { return { done: 'Done', active: 'Now', upcoming: 'Upcoming' }[this.phaseStatus(p)]; },
        phaseStatusClass(p) { return { done: 'bg-green-50 text-green-600', active: 'bg-amber-50 text-amber-600', upcoming: 'bg-muted text-muted-foreground' }[this.phaseStatus(p)]; },
        newSchedule() { return { preliminary: 1, quarterfinals: 1, finals: 1 }; },
        // ----- Combat weight-class picker -----
        genderWord(g) { return g === 'female' ? 'Women' : 'Men'; },
        divName(age, gender, label) { return age + ' ' + this.genderWord(gender) + ' ' + label + ' kg'; },
        /* The package whose table this event should use: the one whose key
           starts with the chosen sport. No match -- boxing, padel, chess --
           means no preset table, and the only way in is the manual sheet. */
        get presetKey() {
            if (! this.sport) return null;
            return Object.keys(this.catalogs).find(k => k.startsWith(this.sport + '_')) || null;
        },
        get tkdConfig() { return this.presetKey ? (this.catalogs[this.presetKey] || {}) : {}; },
        get hasPresets() { return Object.keys(this.tkdConfig).length > 0; },
        get presetGroups() { return Object.keys(this.tkdConfig); },
        /* A group from a DIFFERENT sport cannot survive a sport change -- BJJ has
           no Senior, Taekwondo has no Juvenile -- so the age group follows the
           table rather than the other way round. */
        syncPresetGroup() {
            const groups = this.presetGroups;
            if (! groups.includes(this.tkdAge)) this.tkdAge = groups[0] || '';
            this.tkdChecked = {};
        },
        openPresets() { this.syncPresetGroup(); this.presetsOpen = true; },
        tkdClassesFor() { return (this.tkdConfig[this.tkdAge] || {})[this.tkdGender] || []; },
        get anyTkdChecked() { return Object.values(this.tkdChecked).some(Boolean); },
        addTkdClasses() {
            this.tkdClassesFor().forEach(c => {
                if (!this.tkdChecked[c.label]) return;
                const name = this.divName(this.tkdAge, this.tkdGender, c.label);
                if (this.divisions.some(d => d.name === name)) return;
                this.divisions.push({ name, capacity: '', schedule: this.newSchedule() });
            });
            this.tkdChecked = {};
            this.presetsOpen = false;
        },
        addDivision() { this.divisions.push({ name: '', capacity: 8, schedule: this.newSchedule() }); },
        removeDivision(i) { this.divisions.splice(i, 1); },
        suggestDivisions() {
            const s = this.sportMeta.sample || [];
            if (!s.length) { window.showToast('info', '{{ __("personal.personal_event_create_no_suggestions") }}'); return; }
            this.divisions = s.map(n => ({ name: n, capacity: 8, schedule: this.newSchedule() }));
        },
        addTeam() { this.league.teams.push(''); },
        removeTeam(i) { this.league.teams.splice(i, 1); },
        addFixture() { this.league.fixtures.push({ home: '', away: '', date: '', home_score: '', away_score: '' }); },
        removeFixture(i) { this.league.fixtures.splice(i, 1); },

        // What is still missing, by name — so the form can SAY it rather than
        // just sitting there dimmed.
        missing() {
            const m = [];
            if (!this.tenant_id) m.push('{{ __("personal.personal_event_create_club") }}');
            if (this.title.trim().length < 2) m.push('{{ __("personal.personal_event_create_title_label") }}');
            if (!this.date) m.push('{{ __("personal.personal_event_create_start_date") }}');
            if (!this.start_time) m.push('{{ __("personal.personal_event_create_start_time") }}');
            return m;
        },
        canSave() { return this.missing().length === 0; },
        async save() {
            const missing = this.missing();
            if (missing.length) {
                window.showToast('warning', '{{ __("personal.personal_event_create_still_needed") }} ' + missing.join(', '));
                return;
            }
            if (this.sending) return;
            this.sending = true;
            try {
                let level = this.level || null;
                if (this.has('belt_levels') && this.beltFrom && this.beltTo) level = this.beltFrom + ' → ' + this.beltTo;
                // Location: map mode reads the picker's address + coords; URL mode uses the link.
                // Title is the explicit text box; in map mode fall back to the picker's resolved address.
                const mapAddr = document.getElementById('eventLocMapAddress');
                const locName = (this.location || '').trim() || (this.locMode === 'map' && mapAddr ? mapAddr.value : '') || null;
                const breakOn = this.isChampionship && this.break_enabled;
                const payload = {
                    tenant_id: this.tenant_id, title: this.title.trim(), event_type: this.type, scope: this.scope, sport: this.sport || null,
                    date: this.date, end_date: this.end_date || null,
                    start_time: this.start_time, end_time: this.end_time || null,
                    weigh_in_at: (this.isCombat && this.weigh_in_at) ? this.weigh_in_at : null,
                    enrollment_starts_at: this.enrollment_starts || null,
                    enrollment_ends_at: this.enrollment_ends || null,
                    location: locName,
                    location_url: this.locMode === 'url' ? (this.location_url || null) : null,
                    gps_lat: this.locMode === 'map' && this.gps_lat ? parseFloat(this.gps_lat) : null,
                    gps_long: this.locMode === 'map' && this.gps_long ? parseFloat(this.gps_long) : null,
                    break_start: breakOn ? (this.break_start || null) : null,
                    break_end: breakOn ? (this.break_end || null) : null,
                    courts: (this.isChampionship && this.courts) ? parseInt(this.courts, 10) : null,
                    level: this.isCombat ? null : level, description: this.description || null,
                    participant_free: this.participant_free,
                    // The amount is what the server prices from; the string is
                    // only what the page shows, and the server composes it
                    // from the amount anyway. Sending both keeps older readers
                    // of this payload working.
                    // Always ZERO (or null when free). The price lives in the
                    // fee list below, and leaving a number here as well would
                    // charge it ON TOP of every row. The column stays only for
                    // events priced before the fee list existed.
                    //
                    // NB: single quotes only, in this comment and every other
                    // one in this object. It all lives inside an x-data
                    // ATTRIBUTE, so one double quote anywhere — a comment
                    // included — closes the attribute early and the whole rest
                    // of the component is rendered on the page as visible text.
                    participant_fee: this.participant_free ? null : 'Free',
                    participant_fee_amount: this.participant_free ? null : 0,
                    spectator_enabled: this.spectator_enabled,
                    spectator_fee: this.spectator_enabled ? 'Free' : null,
                    spectator_fee_amount: this.spectator_enabled ? 0 : null,
                    // The priced extras, both roles in one list — the server
                    // tells them apart by `role`. Blank rows are dropped here
                    // rather than refused: a half-typed row somebody abandoned
                    // is not an error worth stopping a save for.
                    // Both roles, ALWAYS, regardless of the free/tickets
                    // toggles: those switches decide what is CHARGED, not what
                    // the organiser has typed. Sending only the enabled half
                    // used to retire the other half's options for good.
                    fee_option_roles: ['participant', 'spectator'],
                    fee_options: [
                        ...this.fee_options,
                        ...this.spectator_options.map(o => ({...o, role: 'spectator'})),
                    ].filter(o => (o.label || '').trim()).map(o => ({
                        uuid: o.uuid || null,
                        role: o.role || 'participant',
                        label: o.label.trim(),
                        amount: (o.amount === '' || o.amount == null) ? 0 : parseFloat(o.amount),
                    })),
                    // Both halves or neither: an amount with no moment to start
                    // from charges nobody, and a moment with no amount is a rule
                    // that does nothing. The server enforces the same pairing.
                    late_fee_amount: (!this.participant_free && this.late_amount !== '' && this.late_from) ? parseFloat(this.late_amount) : null,
                    late_fee_from: (!this.participant_free && this.late_amount !== '' && this.late_from) ? (this.late_from + ' ' + (this.late_time || '23:59') + ':00') : null,
                    max_capacity: (this.isCombat || !this.max_capacity) ? null : parseInt(this.max_capacity, 10),
                    prize: (this.has('prize') && !this.isCombat) ? (this.prize || null) : null,
                    agenda: (this.has('schedule') && !this.isCombat) ? this.agenda.filter(a => (a.t||'').trim() || (a.d||'').trim()) : [],
                    requirements: this.has('requirements') ? this.requirements.map(r => (r||'').trim()).filter(Boolean) : [],
                    tags: this.tagsText.split(',').map(s => s.trim().replace(/^#/, '')).filter(Boolean),
                    phases: (this.has('phases') && !this.isCombat) ? this.phases.filter(p => (p.label||'').trim()).map(p => ({ label: p.label.trim(), date: p.date || null, note: (p.note||'').trim() })) : [],
                    divisions: this.has('divisions') ? this.divisions.filter(d => (d.name||'').trim()).map(d => ({ name: d.name, capacity: (d.capacity === '' || d.capacity == null) ? null : parseInt(d.capacity, 10), schedule: d.schedule })) : [],
                    league: this.has('league') ? {
                        teams: this.league.teams.map(t => (t||'').trim()).filter(Boolean),
                        fixtures: this.league.fixtures.filter(f => (f.home||'').trim() && (f.away||'').trim()),
                    } : null,
                };
                const res = await fetch('{{ $submitUrl }}', {
                    method: '{{ $submitMethod }}',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                    credentials: 'same-origin', body: JSON.stringify(payload),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) throw new Error(data.message || (data.errors ? Object.values(data.errors)[0][0] : '{{ __("personal.personal_event_create_could_not_save") }}'));
                window.showToast('success', data.message || (this.isEdit ? '{{ __("personal.personal_event_create_event_updated") }}' : '{{ __("personal.personal_event_create_event_created") }}'));
                setTimeout(() => { window.location.href = data.redirect || '{{ route('me.events') }}'; }, 600);
            } catch (e) { window.showToast('error', e.message); }
            finally { this.sending = false; }
        }
     }"
     x-init="normalizeDivisions()"
     class="-mx-4 -mt-4 pb-6">

    {{-- ===== Header ===== --}}
    {{-- Design Rule #6: the gradient is the subject's own colour LIGHTENED —
         `colour → colour+b0` — never faded to charcoal. It was
         `${color}, #1f2937`, which turned every event's colour into the same
         dark grey halfway across the band. --}}
    <header class="m-hero px-5 pt-5 pb-10 text-white relative overflow-hidden" :style="`background: linear-gradient(150deg, ${color}, ${color}b0)`">
        <div class="absolute -end-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="flex items-center gap-3 relative z-10">
{{-- ⚠️ AN ADDRESS, NOT A GESTURE.

                 `history.back()` was here, and on a surface people reach from a
                 shared link it is never safe: a link opened from WhatsApp has an
                 EMPTY history, and the `history.length > 1` guard does not save
                 it — a redirect earlier in the session makes the length pass and
                 the gesture then falls into whatever the browser remembers,
                 which inside the sealed event app was sometimes the platform.
                 Back now names where it goes (Design Rule #6) and gets there by
                 address. --}}
            @php
                $backHref = $isEdit ? route('me.events.show', $ev->uuid) : route('me.events');
                $backLabel = $isEdit ? __('events.public_enrol_back_event') : __('personal.event_show_events');
            @endphp
            <a href="{{ $backHref }}"
               class="m-press inline-flex items-center w-10 h-10 justify-center rounded-full bg-white/15 border border-white/25 backdrop-blur text-white text-sm font-semibold no-underline"
           aria-label="{{ $backLabel }}" title="{{ $backLabel }}">
                <i class="bi bi-chevron-left"></i>
            </a>
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70">{{ $isEdit ? __('personal.personal_event_create_eyebrow_edit') : __('personal.personal_event_create_eyebrow_new') }}</p>
                <h1 class="text-xl font-black">{{ $isEdit ? __('personal.personal_event_create_eyebrow_edit') : __('personal.personal_event_create_heading_new') }}</h1>
            </div>
        </div>
    </header>

    @if(empty($clubs))
        <div class="px-4 mt-6">
            <div class="m-card rounded-2xl p-6 text-center">
                <i class="bi bi-buildings text-3xl text-gray-300"></i>
                <p class="text-sm font-bold text-foreground mt-2">{{ __('personal.personal_event_create_join_club_first') }}</p>
                <p class="text-xs text-muted-foreground mt-1">{{ __('personal.personal_event_create_join_club_desc') }}</p>
            </div>
        </div>
    @else
    <div class="px-4 -mt-5 relative z-10 space-y-4">

        {{-- ===== Step 1 · Generic vs Sport, then the sport filter ===== --}}
        <div x-show="step === 1" class="space-y-4">
            {{-- The three doors stand on the page itself, not inside a card:
                 Generic, Sport and Open Mat are one list of things you can
                 start, so they are one stack of rows with nothing boxing two of
                 them off from the third. --}}
            <p class="text-sm font-bold text-foreground px-1">{{ __('personal.personal_event_create_what_creating') }}</p>
            <div class="space-y-2">
                <button type="button" @click="pickGeneric()"
                        class="m-press w-full rounded-2xl p-3.5 border-2 border-gray-100 bg-white shadow-sm flex items-center gap-3 text-start transition-colors hover:border-primary/40">
                    <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 bg-emerald-100 text-emerald-600">
                        <i class="bi bi-calendar-event text-lg"></i>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-foreground">{{ __('personal.personal_event_create_generic_event') }}</span>
                        <span class="block text-[11px] text-muted-foreground mt-0.5">{{ __('personal.personal_event_create_generic_event_sub') }}</span>
                    </span>
                    <i class="bi bi-chevron-right text-muted-foreground/50 text-xs flex-shrink-0"></i>
                </button>
                <button type="button" @click="pickSportMode()"
                        class="m-press w-full rounded-2xl p-3.5 border-2 shadow-sm flex items-center gap-3 text-start transition-colors"
                        :class="mode === 'sport' ? 'border-primary bg-accent' : 'border-gray-100 bg-white hover:border-primary/40'">
                    <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 bg-red-100 text-red-500">
                        <i class="bi bi-trophy-fill text-lg"></i>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-foreground">{{ __('personal.personal_event_create_sport') }}</span>
                        <span class="block text-[11px] text-muted-foreground mt-0.5">{{ __('personal.personal_event_create_sport_sub') }}</span>
                    </span>
                    <i class="bi bi-chevron-right text-muted-foreground/50 text-xs flex-shrink-0 transition-transform"
                       :class="mode === 'sport' && 'rotate-90'"></i>
                </button>
                {{-- Sport filter — only when "Sport" is chosen --}}
                <div x-show="mode === 'sport'" x-cloak class="m-card rounded-2xl p-4">
                    <p class="text-[11px] text-muted-foreground mb-2">{{ __('personal.personal_event_create_pick_sport_hint') }}</p>
                    <div class="relative mb-3">
                        <i class="bi bi-search absolute start-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                        <input x-model="sportSearch2" type="text" placeholder="{{ __('personal.personal_event_create_search_sport_ph') }}"
                               class="w-full ps-10 pe-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 overflow-y-auto -mx-1 px-1" style="max-height:44vh">
                        <template x-for="c in sportCards()" :key="c.key">
                            <button type="button" @click="pickSport(c.key)"
                                    class="m-press rounded-2xl p-3 border-2 flex flex-col items-center justify-center gap-1.5 text-center transition-colors min-h-[88px]"
                                    :class="picked === c.key ? 'border-primary bg-accent' : 'border-gray-100 bg-white'">
                                <i class="bi text-2xl leading-none" :class="c.icon" :style="picked === c.key ? 'color: hsl(250 65% 65%)' : 'color:#9ca3af'"></i>
                                <span class="text-xs font-bold text-foreground leading-tight" x-text="c.label"></span>
                                <span class="text-[9px] text-muted-foreground uppercase tracking-wide" x-text="c.family"></span>
                            </button>
                        </template>
                        <p x-show="!sportCards().length" class="col-span-2 sm:col-span-3 text-center text-sm text-muted-foreground py-6">{{ __('personal.personal_event_create_no_sport_matches') }} “<span x-text="sportSearch2"></span>”.</p>
                    </div>
                </div>
                {{-- ===== Open a mat — the one thing here that is not a form =====
                     An open mat has no dates, no fees and nothing to enrol in: it is
                     two corners and a scoreboard, opened in the thirty seconds after
                     two people agree to fight. So it belongs in the "what are you
                     creating?" step rather than on the events list, but it skips the
                     form entirely — /openmat resolves the club, sport and mat itself. --}}
                <a href="{{ route('openmat') }}"
                   class="m-card m-press rounded-2xl p-3.5 flex items-center gap-3 border border-orange-100">
                    <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 bg-orange-100 text-orange-600">
                        <i class="bi bi-fire text-lg"></i>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-bold text-foreground">{{ __('nav.open_mat') }}</span>
                        <span class="block text-[11px] text-muted-foreground mt-0.5">{{ __('nav.open_mat_sub') }}</span>
                    </span>
                    <i class="bi bi-chevron-right text-muted-foreground/50 text-xs flex-shrink-0"></i>
                </a>
            </div>
        </div>

        {{-- ===== Step 2 · The chosen sport's form ===== --}}
        <div x-show="step === 2" x-cloak class="space-y-4">

        {{-- chosen sport header --}}
        <div class="m-card rounded-2xl p-3 flex items-center justify-between">
            <div class="flex items-center gap-2 min-w-0">
                <i class="bi text-lg text-primary" :class="picked === 'general' ? 'bi-calendar-event' : (sportMeta.icon || 'bi-trophy')"></i>
                <span class="text-sm font-bold text-foreground truncate" x-text="pickedLabel"></span>
            </div>
            <button type="button" @click="step = 1" class="m-press text-[11px] font-bold text-primary px-2 py-1 rounded-lg bg-accent"><i class="bi bi-chevron-left"></i> {{ __('personal.personal_event_create_change') }}</button>
        </div>

        {{-- Details --}}
        <div class="m-card rounded-2xl p-4 space-y-4">
            <p class="text-sm font-bold text-foreground"><span class="text-primary">3.</span> {{ __('personal.personal_event_create_details') }}</p>

            @if(count($clubs) > 1)
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('personal.personal_event_create_club') }}</label>
                    <x-select-menu model="tenant_id" :options="collect($clubs)->map(fn ($c) => ['value' => $c['id'], 'label' => $c['name']])->all()" />
                </div>
            @endif

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('personal.personal_event_create_title_label') }}</label>
                <input x-model="title" type="text" placeholder="{{ __('personal.personal_event_create_title_ph') }}"
                       class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
            </div>

            {{-- Enrollment window — drives the whole date chain --}}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('personal.personal_event_create_enrollment_opens') }}</label>
                    <input x-model="enrollment_starts" type="date" @change="clampDates()"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('personal.personal_event_create_last_day_join') }} <span class="text-muted-foreground font-normal">{{ __('personal.personal_event_create_opt') }}</span></label>
                    <input x-model="enrollment_ends" type="date" :min="enrollment_starts || ''" @change="clampDates()"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                </div>
            </div>

            {{-- Weigh-in (championship only) --}}
            <div x-show="isCombat" x-cloak>
                <label class="block text-xs font-medium text-gray-600 mb-1"><i class="bi bi-clipboard-data text-primary"></i> {{ __('personal.personal_event_create_weigh_in') }} <span class="text-muted-foreground font-normal">{{ __('personal.personal_event_create_opt') }}</span></label>
                <input x-model="weigh_in_at" type="datetime-local"
                       :min="(enrollment_ends || enrollment_starts) ? ((enrollment_ends || enrollment_starts) + 'T00:00') : ''" @change="clampDates()"
                       class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
            </div>

            {{-- Start / end date --}}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('personal.personal_event_create_start_date') }}</label>
                    <input x-model="date" type="date" :min="weighInDate || enrollment_ends || enrollment_starts || ''" @change="clampDates()" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('personal.personal_event_create_end_date') }} <span class="text-muted-foreground font-normal">{{ __('personal.personal_event_create_opt') }}</span></label>
                    <input x-model="end_date" type="date" :min="date || weighInDate || enrollment_ends || enrollment_starts || ''" @change="clampDates()" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                </div>
            </div>

            {{-- Daily times --}}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1"><span x-text="isCombat ? 'Daily start' : 'Start time'">{{ __('personal.personal_event_create_start_time') }}</span></label>
                    <input x-model="start_time" type="time" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1"><span x-text="isCombat ? 'Daily end' : 'End time'">{{ __('personal.personal_event_create_end_time') }}</span> <span class="text-muted-foreground font-normal">{{ __('personal.personal_event_create_opt') }}</span></label>
                    <input x-model="end_time" type="time" class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                </div>
            </div>

            {{-- Break time (championship only) --}}
            <div x-show="isChampionship" x-cloak class="rounded-xl bg-muted/40 p-3 space-y-3">
                <div>
                    <label class="block text-sm font-bold text-foreground mb-1">{{ __('personal.personal_event_create_num_courts') }} <span class="text-muted-foreground font-normal text-[11px]">{{ __('personal.personal_event_create_mats_rings') }}</span></label>
                    <input x-model="courts" type="number" min="1" max="50" placeholder="{{ __('personal.personal_event_create_courts_ph') }}"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                    <p class="text-[11px] text-muted-foreground mt-1">{{ __('personal.personal_event_create_courts_hint') }}</p>
                </div>
                <div class="flex items-center justify-between border-t border-border/60 pt-3">
                    <div>
                        <p class="text-sm font-bold text-foreground">{{ __('personal.personal_event_create_break_time') }}</p>
                        <p class="text-[11px] text-muted-foreground">{{ __('personal.personal_event_create_break_desc') }}</p>
                    </div>
                    <button type="button" @click="break_enabled = !break_enabled"
                            class="m-press shrink-0 w-12 h-7 rounded-full transition-colors relative" :class="break_enabled ? 'bg-primary' : 'bg-gray-300'">
                        <span class="absolute top-0.5 left-0.5 w-6 h-6 rounded-full bg-white shadow transition-transform" :class="break_enabled ? 'translate-x-5' : 'translate-x-0'"></span>
                    </button>
                </div>
                <div x-show="break_enabled" x-cloak class="grid grid-cols-2 gap-3 mt-3">
                    <div>
                        <label class="block text-[11px] font-medium text-gray-600 mb-1">{{ __('personal.personal_event_create_from') }}</label>
                        <input x-model="break_start" type="time" :min="start_time || ''" :max="end_time || ''"
                               class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                    </div>
                    <div>
                        <label class="block text-[11px] font-medium text-gray-600 mb-1">{{ __('personal.personal_event_create_to') }}</label>
                        <input x-model="break_end" type="time" :min="break_start || start_time || ''" :max="end_time || ''"
                               class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                    </div>
                </div>
            </div>

            {{-- Location — pin on map or paste a Maps link --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('personal.personal_event_create_location') }}</label>
                <div class="flex gap-2 mb-2">
                    <button type="button" @click="locMode='map'"
                            class="m-press flex-1 py-1.5 rounded-lg text-xs font-bold border-2 transition-colors" :class="locMode==='map' ? 'border-primary bg-accent text-primary' : 'border-gray-200 text-muted-foreground'"><i class="bi bi-geo-alt"></i> {{ __('personal.personal_event_create_pin_map') }}</button>
                    <button type="button" @click="locMode='url'"
                            class="m-press flex-1 py-1.5 rounded-lg text-xs font-bold border-2 transition-colors" :class="locMode==='url' ? 'border-primary bg-accent text-primary' : 'border-gray-200 text-muted-foreground'"><i class="bi bi-link-45deg"></i> {{ __('personal.personal_event_create_maps_link') }}</button>
                </div>
                <div x-show="locMode==='map'" x-cloak class="space-y-2" @location-changed="gps_lat = $event.detail.lat; gps_long = $event.detail.lng">
                    <input x-model="location" type="text" placeholder="{{ __('personal.personal_event_create_loc_title_ph') }}"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                    <x-location-map id="eventLocMap" :lat="$ev?->gps_lat" :lng="$ev?->gps_long" :address="$ev?->location" height="10rem" :zoom="13" :show-labels="false" />
                </div>
                <div x-show="locMode==='url'" x-cloak class="space-y-2">
                    <input x-model="location_url" type="url" placeholder="{{ __('personal.personal_event_create_maps_url_ph') }}"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                    <input x-model="location" type="text" placeholder="{{ __('personal.personal_event_create_place_name_ph') }}"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                </div>
            </div>

            {{-- Level + event-wide capacity: not for combat (capacity is per weight division) --}}
            <div class="grid grid-cols-2 gap-3" x-show="!isCombat">
                <div x-show="!has('belt_levels')">
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('personal.personal_event_create_level') }} <span class="text-muted-foreground font-normal">{{ __('personal.personal_event_create_opt') }}</span></label>
                    <input x-model="level" type="text" placeholder="{{ __('personal.personal_event_create_level_ph') }}"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                </div>
                <div :class="has('belt_levels') ? 'col-span-2' : ''">
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('personal.personal_event_create_capacity') }} <span class="text-muted-foreground font-normal">{{ __('personal.personal_event_create_opt') }}</span></label>
                    <input x-model="max_capacity" type="number" min="1" placeholder="{{ __('personal.personal_event_create_capacity_ph') }}"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('personal.personal_event_create_about') }} <span class="text-muted-foreground font-normal">{{ __('personal.personal_event_create_opt') }}</span></label>
                <textarea x-model="description" rows="3" placeholder="{{ __('personal.personal_event_create_about_ph') }}"
                          class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none resize-none"></textarea>
            </div>

            <div x-show="has('prize') && !isCombat" x-cloak>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('personal.personal_event_create_prize') }} <span class="text-muted-foreground font-normal">{{ __('personal.personal_event_create_opt') }}</span></label>
                <input x-model="prize" type="text" placeholder="{{ __('personal.personal_event_create_prize_ph') }}"
                       class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
            </div>
        </div>

        {{-- Map init (works on in-shell navigation too) --}}
        <script>
            (function () {
                var id = 'eventLocMap', tries = 0;
                (function go() {
                    if (window.LocationMap) { window.LocationMap.create({ id: id, draggable: true, zoom: 13 }); }
                    else if (tries++ < 60) { setTimeout(go, 100); }
                })();
            })();
        </script>

        {{-- Reach — who can join (scope) --}}
        <div class="m-card rounded-2xl p-4">
            <p class="text-sm font-bold text-foreground mb-1"><i class="bi bi-broadcast text-primary"></i> {{ __('personal.personal_event_create_who_join') }}</p>
            <p class="text-[11px] text-muted-foreground mb-3">{{ __('personal.personal_event_create_who_join_desc') }}</p>
            <div class="space-y-2">
                <template x-for="[key, sc] in Object.entries(schema.scopes || {})" :key="key">
                    <button type="button" @click="scope = key"
                            class="m-press w-full flex items-center gap-3 p-3 rounded-xl border-2 text-start transition-colors"
                            :class="scope === key ? 'border-primary bg-accent' : 'border-gray-100 bg-white'">
                        <span class="w-9 h-9 rounded-lg grid place-items-center flex-shrink-0"
                              :class="scope === key ? 'bg-primary text-white' : 'bg-muted text-muted-foreground'">
                            <i class="bi text-base" :class="sc.icon"></i>
                        </span>
                        <span class="min-w-0">
                            <span class="block text-sm font-bold text-foreground" x-text="sc.label"></span>
                            <span class="block text-[11px] text-muted-foreground leading-tight" x-text="sc.desc"></span>
                        </span>
                        <i class="bi bi-check-circle-fill text-primary ms-auto flex-shrink-0" x-show="scope === key" x-cloak></i>
                    </button>
                </template>
            </div>
        </div>

        {{-- Belt levels (belt tests) --}}
        <div class="m-card rounded-2xl p-4" x-show="has('belt_levels')" x-cloak>
            <p class="text-sm font-bold text-foreground mb-1"><i class="bi bi-patch-check-fill text-amber-500"></i> {{ __('personal.personal_event_create_belt_grading') }}</p>
            <p class="text-[11px] text-muted-foreground mb-3">{{ __('personal.personal_event_create_belt_range_q') }}</p>
            <div class="flex items-center gap-2">
                <div class="flex-1">
                    <x-select-menu model="beltFrom" :options="$schema['belts']" :placeholder="__('personal.personal_event_create_from_belt')" />
                </div>
                <i class="bi bi-arrow-right text-muted-foreground"></i>
                <div class="flex-1">
                    <x-select-menu model="beltTo" :options="$schema['belts']" :placeholder="__('personal.personal_event_create_to_belt')" />
                </div>
            </div>
        </div>

        {{-- Divisions / categories (non-combat: free text) --}}
        <div class="m-card rounded-2xl p-4" x-show="has('divisions') && !isCombat" x-cloak>
            <div class="flex items-center justify-between mb-1">
                <p class="text-sm font-bold text-foreground"><i class="bi bi-diagram-3 bracket-icon text-primary"></i> <span x-text="divisionLabel + 's'">{{ __('personal.personal_event_create_category_fallback') }}</span></p>
                <button type="button" @click="suggestDivisions()" x-show="(sportMeta.sample||[]).length" class="m-press text-[11px] font-bold text-primary"><i class="bi bi-magic"></i> {{ __('personal.personal_event_create_suggest') }}</button>
            </div>
            <p class="text-[11px] text-muted-foreground mb-3">{{ __('personal.personal_event_create_each') }} <span x-text="divisionLabel.toLowerCase()">{{ __('personal.personal_event_create_category_lc') }}</span> {{ __('personal.personal_event_create_gets_own_bracket') }}</p>
            <div class="space-y-2">
                <template x-for="(d, i) in divisions" :key="i">
                    <div class="flex items-center gap-2">
                        <input x-model="d.name" type="text" :placeholder="divisionLabel + ' name'"
                               class="flex-1 min-w-0 px-2.5 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                        <input x-model.number="d.capacity" type="number" min="2" placeholder="{{ __('personal.personal_event_create_cap_ph') }}"
                               class="w-16 px-2 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                        <button type="button" @click="removeDivision(i)" class="m-press w-8 h-8 rounded-lg bg-muted grid place-items-center text-red-500 flex-shrink-0"><i class="bi bi-x-lg text-xs"></i></button>
                    </div>
                </template>
            </div>
            <button type="button" @click="addDivision()" class="m-press mt-3 w-full py-2.5 rounded-xl border-2 border-dashed border-gray-200 text-sm font-bold text-muted-foreground">
                <i class="bi bi-plus-lg"></i> {{ __('personal.personal_event_create_add') }} <span x-text="divisionLabel.toLowerCase()">{{ __('personal.personal_event_create_category_lc') }}</span>
            </button>
        </div>

        {{-- ===== Weight categories (combat) =====

             The bulk PICKER stays — an organiser adding nine weight classes at
             once should not have to type nine rows — but everything after it is
             now <x-event-divisions> in local mode: one short row per division,
             an Add button, and a single sheet for editing. What it replaces was
             a hundred-odd lines of inline capacity boxes and day selects
             REPEATED per division, which is exactly why it read as a wall.

             The same component in SERVER mode is the console's Divisions
             section, so the two cannot drift — and it carries the bracket /
             round-robin choice with it. --}}
        <div class="m-card rounded-2xl p-4" x-show="isCombat && has('divisions')" x-cloak>
            <div class="flex items-center justify-between mb-1">
                <p class="text-sm font-bold text-foreground"><i class="bi bi-diagram-3 bracket-icon text-primary"></i> {{ __('personal.personal_event_create_weight_categories') }}</p>
                <span class="text-[11px] text-muted-foreground" x-text="dayCount + (dayCount === 1 ? ' day' : ' days')"></span>
            </div>
            <p class="text-[11px] text-muted-foreground mb-3">{{ __('personal.personal_event_create_weight_cat_hint') }}</p>

            {{-- ===== The bulk picker, BEHIND a button =====

                 It used to sit open at the top of the card: two dropdowns and a
                 row of weight chips, before you had asked for any of it. Closed
                 by default, the card opens on the list of what you actually have.

                 The button itself lives in the divisions component's action row,
                 BESIDE Add division -- see the `actions` slot below. The two are
                 alternatives, not steps, and stacking them full-width read as a
                 sequence. --}}
            <div class="rounded-2xl border-2 border-dashed border-gray-200 p-3 space-y-2.5 mb-3" x-show="presetsOpen" x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0">
                {{-- The groups this SPORT grades by, not another sport's. --}}
                <div class="grid grid-cols-2 gap-2">
                    <select x-model="tkdAge" @change="tkdChecked = {}" class="app-select w-full px-2.5 py-2 border border-gray-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                        <template x-for="g in presetGroups" :key="g"><option :value="g" x-text="g"></option></template>
                    </select>
                    <x-gender-dropdown model="tkdGender" name="tkd_gender" />
                </div>
                <div class="flex flex-wrap gap-1.5">
                    <template x-for="c in tkdClassesFor()" :key="c.label">
                        <label class="m-press cursor-pointer">
                            <input type="checkbox" x-model="tkdChecked[c.label]" class="sr-only">
                            <span class="inline-block px-2.5 py-1 rounded-full text-[11px] font-bold border transition-colors"
                                  :class="tkdChecked[c.label] ? 'bg-primary text-white border-primary' : 'bg-white text-foreground border-gray-200'"
                                  x-text="c.label + ' kg'"></span>
                        </label>
                    </template>
                </div>
                <button type="button" @click="addTkdClasses()" :disabled="!anyTkdChecked"
                        class="m-press w-full py-2 rounded-xl text-white text-sm font-bold flex items-center justify-center gap-2 disabled:opacity-50"
                        :style="`background:${color}`">
                    <i class="bi bi-plus-lg"></i> {{ __('personal.personal_event_create_add_selected') }}
                </button>
            </div>

            {{-- The list, and the sheet that edits one. `model` binds the very
                 array this form already submits, so the payload is unchanged. --}}
            <div class="mt-3">
                <x-event-divisions model="divisions" :server="false"
                    :phases="[
                        ['key' => 'preliminary', 'label' => __('personal.event_phase_prelim')],
                        ['key' => 'quarterfinals', 'label' => __('personal.event_phase_quarters')],
                        ['key' => 'finals', 'label' => __('personal.event_phase_finals')],
                    ]">
                    {{-- Beside Add division, not under it. Only for a sport that
                         HAS a preset table: boxing, padel and chess have none,
                         and an empty picker is worse than no button. --}}
                    <x-slot:actions>
                        <button type="button" x-show="hasPresets" x-cloak
                                @click="presetsOpen ? presetsOpen = false : openPresets()"
                                class="m-press flex-1 py-2.5 rounded-xl border-2 border-dashed border-gray-200 text-sm font-bold text-muted-foreground flex items-center justify-center gap-2 transition-colors hover:bg-muted/60">
                            <i class="bi" :class="presetsOpen ? 'bi-chevron-up' : 'bi-lightning-charge'"></i>
                            <span x-text="presetsOpen ? '{{ __('personal.personal_event_create_close') }}' : '{{ __('events.divisions_add_preset') }}'"></span>
                        </button>
                    </x-slot:actions>
                </x-event-divisions>
            </div>

            <div x-show="dayCount < 2" x-cloak class="flex items-start gap-2 text-[11px] text-amber-600 bg-amber-50 rounded-xl p-2.5 mt-3">
                <i class="bi bi-info-circle mt-0.5"></i>
                <span>{{ __('personal.personal_event_create_single_day') }} <span class="font-semibold">{{ __('personal.personal_event_create_end_date_lc') }}</span> {{ __('personal.personal_event_create_split_sections') }}</span>
            </div>
        </div>

        {{-- League: teams + fixtures (standings auto-computed) --}}
        <div class="m-card rounded-2xl p-4 space-y-4" x-show="has('league')" x-cloak>
            <div>
                <p class="text-sm font-bold text-foreground mb-1"><i class="bi bi-people-fill text-primary"></i> {{ __('personal.personal_event_create_teams') }}</p>
                <p class="text-[11px] text-muted-foreground mb-2">{{ __('personal.personal_event_create_teams_hint') }}</p>
                <div class="space-y-2">
                    <template x-for="(t, i) in league.teams" :key="i">
                        <div class="flex items-center gap-2">
                            <input x-model="league.teams[i]" type="text" :placeholder="(sportMeta.team ? 'Team' : 'Player') + ' name'"
                                   class="flex-1 min-w-0 px-2.5 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                            <button type="button" @click="removeTeam(i)" class="m-press w-8 h-8 rounded-lg bg-muted grid place-items-center text-red-500 flex-shrink-0"><i class="bi bi-x-lg text-xs"></i></button>
                        </div>
                    </template>
                </div>
                <button type="button" @click="addTeam()" class="m-press mt-2 w-full py-2.5 rounded-xl border-2 border-dashed border-gray-200 text-sm font-bold text-muted-foreground">
                    <i class="bi bi-plus-lg"></i> {{ __('personal.personal_event_create_add') }} <span x-text="sportMeta.team ? 'team' : 'player'">{{ __('personal.personal_event_create_team_lc') }}</span>
                </button>
            </div>

            <div>
                <p class="text-sm font-bold text-foreground mb-2"><i class="bi bi-calendar2-week text-primary"></i> {{ __('personal.personal_event_create_fixtures') }}</p>
                <div class="space-y-3">
                    <template x-for="(f, i) in league.fixtures" :key="i">
                        <div class="rounded-2xl border border-gray-100 p-3 space-y-2">
                            <div class="flex items-center gap-2">
                                <input x-model="f.home" type="text" placeholder="{{ __('personal.personal_event_create_home_ph') }}"
                                       class="flex-1 min-w-0 px-2.5 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                                <input x-model.number="f.home_score" type="number" min="0" placeholder="–" class="w-12 px-1 py-2 text-center border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                                <span class="text-muted-foreground text-xs">{{ __('personal.personal_event_create_versus') }}</span>
                                <input x-model.number="f.away_score" type="number" min="0" placeholder="–" class="w-12 px-1 py-2 text-center border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                                <input x-model="f.away" type="text" placeholder="{{ __('personal.personal_event_create_away_ph') }}"
                                       class="flex-1 min-w-0 px-2.5 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                            </div>
                            <div class="flex items-center gap-2">
                                <input x-model="f.date" type="text" placeholder="{{ __('personal.personal_event_create_fixture_date_ph') }}"
                                       class="flex-1 px-2.5 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                                <button type="button" @click="removeFixture(i)" class="m-press w-8 h-8 rounded-lg bg-muted grid place-items-center text-red-500 flex-shrink-0"><i class="bi bi-trash text-xs"></i></button>
                            </div>
                        </div>
                    </template>
                </div>
                <button type="button" @click="addFixture()" class="m-press mt-2 w-full py-2.5 rounded-xl border-2 border-dashed border-gray-200 text-sm font-bold text-muted-foreground">
                    <i class="bi bi-plus-lg"></i> {{ __('personal.personal_event_create_add_fixture') }}
                </button>
            </div>
        </div>

        {{-- Pricing & tickets --}}
        <div class="m-card rounded-2xl p-4 space-y-3">
            <p class="text-sm font-bold text-foreground">{{ __('personal.personal_event_create_entry_tickets') }}</p>
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-bold text-foreground">{{ __('personal.personal_event_create_free_join') }}</p>
                    <p class="text-[11px] text-muted-foreground">{{ __('personal.personal_event_create_free_join_desc') }}</p>
                </div>
                <button type="button" @click="participant_free = !participant_free"
                        class="m-press shrink-0 w-12 h-7 rounded-full transition-colors relative"
                        :class="participant_free ? 'bg-green-500' : 'bg-gray-300'">
                    <span class="absolute top-0.5 left-0.5 w-6 h-6 rounded-full bg-white shadow transition-transform" :class="participant_free ? 'translate-x-5' : 'translate-x-0'"></span>
                </button>
            </div>
            <div x-show="!participant_free" x-cloak>
                {{-- ===== The participation fee IS this list =====

                     There is deliberately NO single amount box above this.
                     Asked for on 2026-09-06, and it is the right shape: a
                     competition does not have one price with decorations on it,
                     it sells named things — Gi, No-Gi, a T-shirt, a banquet
                     seat — and an entrant picks the ones they want. A separate
                     base fee sitting above the list only invited the question
                     "is that as well as, or instead of?".

                     One price is expressed as one row. That reads no worse than
                     a lone box did, and it means the model never has two places
                     a price can live.

                     An event created before this change keeps its base amount on
                     the row and is seeded here as a row (see $initFeeOptions),
                     so the organiser sees the price they set — and saving turns
                     it into an option and clears the old column, for the same
                     total. --}}
                <div>
                    <p class="text-sm font-bold text-foreground">{{ __('events.fee_options_title') }}</p>
                    <p class="text-[11px] text-muted-foreground mt-0.5">{{ __('events.fee_options_hint') }}</p>

                    <template x-for="(opt, i) in fee_options" :key="i">
                        <div class="flex items-center gap-2 mt-2">
                            <input x-model="opt.label" type="text" maxlength="80"
                                   placeholder="{{ __('events.fee_option_placeholder') }}"
                                   class="flex-1 min-w-0 px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                            <div class="relative w-32 shrink-0">
                                <span class="absolute start-3 top-1/2 -translate-y-1/2 text-[11px] font-bold text-muted-foreground pointer-events-none" x-text="currency">BHD</span>
                                <input x-model="opt.amount" type="number" min="0" step="0.001" inputmode="decimal" placeholder="0.000"
                                       class="w-full ps-12 pe-2 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                            </div>
                            <button type="button" @click="fee_options.splice(i, 1)"
                                    aria-label="{{ __('events.fee_option_remove') }}"
                                    class="m-press shrink-0 w-9 h-9 rounded-xl grid place-items-center text-red-600 border border-red-200 hover:bg-red-50 transition-colors">
                                <i class="bi bi-trash text-sm"></i>
                            </button>
                        </div>
                    </template>

                    <button type="button" @click="fee_options.push({ uuid: null, label: '', amount: '' })"
                            class="m-press mt-2 inline-flex items-center gap-2 h-9 px-3.5 rounded-full border text-[12px] font-bold"
                            style="border-color: #7c6bf559; color: #7c6bf5;">
                        <i class="bi bi-plus-lg"></i>{{ __('events.fee_option_add') }}
                    </button>

                    {{-- What an entrant will be asked to choose from, worked
                         out live — an organiser reads their own price list back
                         rather than trusting they typed it correctly. --}}
                    <p class="text-[11px] text-muted-foreground mt-2" x-show="fee_options.filter(o => (o.label||'').trim()).length" x-cloak>
                        <span x-text="fee_options.filter(o => (o.label||'').trim()).map(o => o.label.trim() + ' ' + currency + ' ' + ((parseFloat(o.amount || 0) || 0).toFixed(3).replace(/\.?0+$/, ''))).join('  ·  ')"></span>
                    </p>

                    {{-- An empty list means nobody can be charged anything, which
                         is a thing an organiser does by accident far more often
                         than on purpose — "free" is the switch above. --}}
                    <p class="text-[11px] mt-2 flex items-start gap-1.5" style="color:#92400e;"
                       x-show="!fee_options.filter(o => (o.label||'').trim()).length" x-cloak>
                        <i class="bi bi-exclamation-triangle mt-0.5"></i>
                        <span>{{ __('events.fee_options_empty_warning') }}</span>
                    </p>
                </div>

                {{-- ===== Late entry penalty =====

                     Flat, once, on top of whatever they selected — a penalty for
                     being late rather than a different product. Frozen onto the
                     entry when it is taken, so moving this date afterwards never
                     re-bills, or un-bills, anyone already in. --}}
                <div class="mt-3 pt-3 border-t border-gray-100">
                    <p class="text-sm font-bold text-foreground">{{ __('events.fee_late_title') }}</p>
                    <p class="text-[11px] text-muted-foreground mt-0.5">{{ __('events.fee_late_hint') }}</p>

                    <div class="flex items-center gap-2 mt-2">
                        <div class="relative w-32 shrink-0">
                            <span class="absolute start-3 top-1/2 -translate-y-1/2 text-[11px] font-bold text-muted-foreground pointer-events-none" x-text="currency">BHD</span>
                            <input x-model="late_amount" type="number" min="0" step="0.001" inputmode="decimal" placeholder="0.000"
                                   class="w-full ps-12 pe-2 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                        </div>
                        <div class="flex-1 min-w-0">
                            {{-- Design Rule #4: the project's own calendar, never
                                 a native date input. --}}
                            <x-date-picker model="late_from" placeholder="{{ __('events.fee_late_from') }}" />
                        </div>
                    </div>
                </div>
            </div>
            <div class="flex items-center justify-between pt-2 border-t border-gray-100">
                <div>
                    <p class="text-sm font-bold text-foreground">{{ __('personal.personal_event_create_spectator_tickets') }}</p>
                    <p class="text-[11px] text-muted-foreground">{{ __('personal.personal_event_create_spectator_desc') }}</p>
                </div>
                <button type="button" @click="spectator_enabled = !spectator_enabled"
                        class="m-press shrink-0 w-12 h-7 rounded-full transition-colors relative"
                        :class="spectator_enabled ? 'bg-primary' : 'bg-gray-300'">
                    <span class="absolute top-0.5 left-0.5 w-6 h-6 rounded-full bg-white shadow transition-transform" :class="spectator_enabled ? 'translate-x-5' : 'translate-x-0'"></span>
                </button>
            </div>
            <div x-show="spectator_enabled" x-cloak>
                {{-- Ticket types, the same shape as the participation fee above:
                     the list IS the price, one row per kind of ticket —
                     ringside, family pass, whatever this event sells. No single
                     amount box, and no late penalty here: somebody buying a
                     ticket on the day is the normal way anyone watches sport. --}}
                <div>
                    <template x-for="(opt, i) in spectator_options" :key="i">
                        <div class="flex items-center gap-2 mt-2">
                            <input x-model="opt.label" type="text" maxlength="80"
                                   placeholder="{{ __('events.fee_option_placeholder') }}"
                                   class="flex-1 min-w-0 px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                            <div class="relative w-32 shrink-0">
                                <span class="absolute start-3 top-1/2 -translate-y-1/2 text-[11px] font-bold text-muted-foreground pointer-events-none" x-text="currency">BHD</span>
                                <input x-model="opt.amount" type="number" min="0" step="0.001" inputmode="decimal" placeholder="0.000"
                                       class="w-full ps-12 pe-2 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                            </div>
                            <button type="button" @click="spectator_options.splice(i, 1)"
                                    aria-label="{{ __('events.fee_option_remove') }}"
                                    class="m-press shrink-0 w-9 h-9 rounded-xl grid place-items-center text-red-600 border border-red-200 hover:bg-red-50 transition-colors">
                                <i class="bi bi-trash text-sm"></i>
                            </button>
                        </div>
                    </template>

                    <button type="button" @click="spectator_options.push({ uuid: null, label: '', amount: '' })"
                            class="m-press mt-2 inline-flex items-center gap-2 h-9 px-3.5 rounded-full border text-[12px] font-bold"
                            style="border-color: #7c6bf559; color: #7c6bf5;">
                        <i class="bi bi-plus-lg"></i>{{ __('events.fee_option_add') }}
                    </button>
                </div>
            </div>
        </div>

        {{-- Schedule --}}
        <div class="m-card rounded-2xl p-4" x-show="has('schedule') && !isCombat" x-cloak>
            <div class="flex items-center justify-between mb-1">
                <p class="text-sm font-bold text-foreground"><i class="bi bi-list-check text-primary"></i> {{ __('personal.personal_event_create_schedule') }}</p>
                <span class="text-[11px] text-muted-foreground">{{ __('personal.personal_event_create_happens_when') }}</span>
            </div>
            <div class="space-y-2 mt-2">
                <template x-for="(a, i) in agenda" :key="i">
                    <div class="rounded-2xl border border-gray-100 p-3 space-y-2">
                        <div class="flex items-center gap-2">
                            <input x-model="a.t" type="datetime-local"
                                   :min="schedMin()" :max="schedMax()" @change="fixAgenda()"
                                   class="flex-1 min-w-0 px-2.5 py-2 border border-gray-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                            <button type="button" @click="removeAgenda(i)" class="m-press w-8 h-8 rounded-lg bg-muted grid place-items-center text-red-500 flex-shrink-0"><i class="bi bi-x-lg text-xs"></i></button>
                        </div>
                        <input x-model="a.d" type="text" placeholder="{{ __('personal.personal_event_create_whats_happening_ph') }}" class="w-full px-2.5 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                    </div>
                </template>
            </div>
            <button type="button" @click="addAgenda()" class="m-press mt-3 w-full py-2.5 rounded-xl border-2 border-dashed border-gray-200 text-sm font-bold text-muted-foreground"><i class="bi bi-plus-lg"></i> {{ __('personal.personal_event_create_add_schedule_item') }}</button>
        </div>

        {{-- Requirements --}}
        <div class="m-card rounded-2xl p-4" x-show="has('requirements')" x-cloak>
            <p class="text-sm font-bold text-foreground mb-2"><i class="bi bi-clipboard-check text-primary"></i> {{ __('personal.personal_event_create_requirements') }}</p>
            <div class="space-y-2">
                <template x-for="(r, i) in requirements" :key="i">
                    <div class="flex items-center gap-2">
                        <input x-model="requirements[i]" type="text" placeholder="{{ __('personal.personal_event_create_requirement_ph') }}" class="flex-1 min-w-0 px-2.5 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                        <button type="button" @click="removeReq(i)" class="m-press w-8 h-8 rounded-lg bg-muted grid place-items-center text-red-500 flex-shrink-0"><i class="bi bi-x-lg text-xs"></i></button>
                    </div>
                </template>
            </div>
            <button type="button" @click="addReq()" class="m-press mt-2 w-full py-2.5 rounded-xl border-2 border-dashed border-gray-200 text-sm font-bold text-muted-foreground"><i class="bi bi-plus-lg"></i> {{ __('personal.personal_event_create_add_requirement') }}</button>
        </div>

        {{-- Tags (always) --}}
        <div class="m-card rounded-2xl p-4">
            <label class="block text-xs font-medium text-gray-600 mb-1">{{ __('personal.personal_event_create_tags') }} <span class="text-muted-foreground font-normal">{{ __('personal.personal_event_create_comma_separated') }}</span></label>
            <input x-model="tagsText" type="text" placeholder="{{ __('personal.personal_event_create_tags_ph') }}"
                   class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
        </div>

        {{-- Phases (tournament timeline) --}}
        <div class="m-card rounded-2xl p-4" x-show="has('phases') && !isCombat" x-cloak>
            <div class="flex items-center justify-between mb-1">
                <p class="text-sm font-bold text-foreground"><i class="bi bi-signpost-split text-primary"></i> {{ __('personal.personal_event_create_timeline') }}</p>
                <span class="text-[11px] text-muted-foreground">{{ __('personal.personal_event_create_lifecycle') }}</span>
            </div>
            <p class="text-[11px] text-muted-foreground mb-3">{{ __('personal.personal_event_create_stages_hint') }}</p>
            <div class="space-y-3">
                <template x-for="(p, i) in phases" :key="i">
                    <div class="rounded-2xl border border-gray-100 p-3 space-y-2">
                        <div class="flex items-center gap-2">
                            <input x-model="p.label" type="text" placeholder="{{ __('personal.personal_event_create_stage_ph') }}" class="flex-1 min-w-0 px-2.5 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                            <button type="button" @click="removePhase(i)" class="m-press w-8 h-8 rounded-lg bg-muted grid place-items-center text-red-500 flex-shrink-0"><i class="bi bi-x-lg text-xs"></i></button>
                        </div>
                        <div class="flex items-center gap-2">
                            <input x-model="p.date" type="date"
                                   :min="i > 0 ? (phases[i-1].date || date) : date"
                                   :max="end_date || date"
                                   @change="fixPhaseDates()"
                                   class="flex-1 min-w-0 px-2.5 py-2 border border-gray-200 rounded-xl text-sm bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                            {{-- status is derived from the date, never entered --}}
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-bold flex-shrink-0" :class="phaseStatusClass(p)" x-text="phaseStatusLabel(p)"></span>
                        </div>
                        <input x-model="p.note" type="text" placeholder="{{ __('personal.personal_event_create_note_optional_ph') }}" class="w-full px-2.5 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                    </div>
                </template>
            </div>
            <button type="button" @click="addPhase()" class="m-press mt-3 w-full py-2.5 rounded-xl border-2 border-dashed border-gray-200 text-sm font-bold text-muted-foreground"><i class="bi bi-plus-lg"></i> {{ __('personal.personal_event_create_add_stage') }}</button>
        </div>

        {{-- Save — stays CLICKABLE when the form is incomplete (only `sending`
             disables it) so tapping it names what is missing. A disabled button
             that silently ignores you is indistinguishable from a broken one. --}}
        <button type="button" @click="save()" :disabled="sending"
                class="m-press w-full py-3.5 rounded-2xl text-white font-black text-sm flex items-center justify-center gap-2 transition-opacity"
                :class="(canSave() && !sending) ? '' : 'opacity-50'" :style="`background:${color}`">
            <i class="bi" :class="sending ? 'bi-arrow-repeat animate-spin' : (isEdit ? 'bi-check2' : 'bi-calendar-plus')"></i>
            <span x-text="sending ? 'Saving…' : (isEdit ? 'Save changes' : 'Publish event')"></span>
        </button>

        </div> {{-- /step 2 --}}
    </div>
    @endif

</div>
@endsection
