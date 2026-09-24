@props([
    /* The event these officials are for. Required: every request names it. */
    'event',
    /* What this competition's sport is CALLED, for the one line that tells an
       organiser who the search is reaching ("People jiu-jitsu knows"). Blank is
       fine — the sentence then just reads a little plainer. */
    'sportLabel' => '',
    /* The event's own colour, for the photo flow's camera and cropper. */
    'color' => '#7c3aed',
    /* Only somebody who may MANAGE the event sees any of this. The page that
       hosts it is readable by anyone who can see the event, so the appointment
       half is gated here rather than by where it sits. */
    'canManage' => false,
])

{{-- Appointing the people who run a competition.

     A round control in the page HEADER, and a sheet behind it. It began life as
     a tall card inside the event create/edit form -- the wrong place twice over:
     appointing needs an event to appoint to, and a form is not where you manage
     one. It then spent a moment as a tall card on this page, which was better
     and still meant scrolling past an appointment desk to read a team sheet.

     Now the page is the sheet of who is officiating, and appointing is one tap
     away in the header, the way every other action on this platform is.

     Standalone by the Component-First contract: it owns its state, its requests
     and its sheet, and needs nothing from the page but the event's uuid. Drop
     it in a hero control row and it works.

     NB: single quotes only inside the x-data attribute below, comments
     included. One double quote closes the attribute early and the rest of the
     component renders on the page as visible text. --}}
@php
    // One stable id for the fields inside this panel — the pickers below need
    // ids of their own, and two officials panels on one page must not collide.
    $uid = 'evoff'.substr(md5((string) $event), 0, 6);

    // The photo flow paints itself in the event's colour; it validates its own
    // input, and this is what the rest of this page is already using.
    $photoColor = preg_match('/^#[0-9A-Fa-f]{6}$/', (string) $color) ? $color : '#7c3aed';
@endphp

@if($canManage)
<div x-data="{

        officials: [], candidates: [], roles: [], role: 'jury', roleChosen: false, q: '', open: false, panelOpen: false, busy: false, loaded: false,
        /*
         * TWO DOORS, the same pair the entry desk has (asked for 2026-09-14):
         * find somebody already on the platform, or write down the referee the
         * federation sent, who has never opened this app.
         *
         * 'find' | 'new' | null — null is the chooser itself.
         */
        how: null,
        fresh: { full_name: '', gender: '', birthdate: '', phone: '', phone_code: '+973', nationality: '' },
        /*
         * WHOSE referee this is — asked on BOTH doors, because it is a fact
         * about the appointment rather than about the person: the same coach
         * takes a corner for his own club one weekend and for the federation
         * the next.
         *
         * '' is INDEPENDENT, and it is the default — plenty of officials come
         * with nobody, and that is a real answer rather than a blank. The list
         * is the clubs already at this event plus the host, in the same two key
         * shapes an entry uses (a tenant slug, or an event club's uuid for a
         * visiting team with no account).
         */
        clubs: [], club: '', clubOpen: false,
        /* The mat job, as a dropdown — see partials/event-official-role. */
        roleOpen: false,
        clubChosen() { return this.clubs.find(c => c.slug === this.club) || null; },
        /* The staged crop, as a data URI. THE photo flow (camera · gallery ·
           the one cropper) owns taking it; this only asks for it, holds it
           until the appointment is committed, and shows it meanwhile — the same
           procedure the entry desk uses for a competitor. */
        photo: null,
        photoId: @js($uid.'Photo'),
        pickPhoto() {
            window.dispatchEvent(new CustomEvent('photo-capture:open', { detail: { id: this.photoId } }));
        },
        dropPhoto() { this.photo = null; },
        get canCreate() { return ! this.busy && this.fresh.full_name.trim().length > 1; },
        /*
         * Appointing takes one more decision than a tap.
         *
         * storeOfficial() requires `compensation` — a paid appointment is a
         * line in the event's P&L — and the form never sent it, so EVERY
         * appointment failed validation with 'The compensation field is
         * required'. Tapping a candidate therefore opens this sheet instead
         * of posting immediately.
         *
         * NB: single quotes on purpose. This whole object lives inside an
         * x-data attribute, so a double quote anywhere in it — comments
         * included — closes the attribute early and the rest of the
         * component is rendered on the page as visible text.
         *
         * Nationality rides along because this is the only moment anyone is
         * looking at an official's details: an official is listed by country
         * on every officiating sheet, and a referee with none shows no flag.
         * Asked ONLY when the member has no country on file — it is their
         * data, and the form fills a blank rather than correcting it.
         */
        compensations: [], currency: '',
        sheet: false, pick: null, comp: 'volunteer', fee: '',
        get canAppoint() {
            if (this.busy || ! this.pick) return false;
            if (this.comp === 'paid' && ! (parseFloat(this.fee) > 0)) return false;
            return true;
        },
        setRole(v) { this.role = v; this.load(); },
        choose(c) { this.pick = c; this.comp = 'volunteer'; this.fee = ''; this.club = ''; this.clubOpen = false; this.open = false; this.sheet = true; },
        get roleMeta() { return this.roles.find(r => r.value === this.role) || {}; },
        roleLabel(v) { return (this.roles.find(r => r.value === v) || {}).label || v; },
        byRole(v) { return this.officials.filter(o => o.role === v); },
        async load() {
            try {
                // The role goes with the query: a MAT role searches the whole
                // platform (referees are rarely members of the host club), a
                // permission role searches the club only. Without it the server
                // always assumed the narrow pool, so a federation referee could
                // never be found here at all.
                const res = await fetch(`{{ route('me.events.officials', $event) }}?q=${encodeURIComponent(this.q)}&role=${encodeURIComponent(this.role)}`, {
                    headers: { 'Accept': 'application/json' }, credentials: 'same-origin',
                });
                const d = await res.json();
                if (!res.ok || !d.success) throw new Error(d.message || 'Could not load');
                this.officials = d.officials; this.candidates = d.candidates;
                this.roles = d.roles; this.loaded = true;
                /*
                 * Land on a MAT role, not the hardcoded 'jury'.
                 *
                 * 'jury' is a PERMISSION role, and those are appointable only
                 * from the host club — so the picker opened on a pool of the
                 * club's membership rows (one, on a club whose members joined
                 * some other way) and read as 'there is nobody to appoint'.
                 * The sport's own first job is both the commonest appointment
                 * and the one that searches the whole platform.
                 */
                if (! this.roleChosen) {
                    this.roleChosen = true;
                    const mat = d.roles.find(r => r.group === 'mat');
                    if (mat && mat.value !== this.role) { this.role = mat.value; return this.load(); }
                }
                this.compensations = d.compensations || []; this.currency = d.currency || '';
                this.clubs = d.clubs || [];
            } catch (e) { window.showToast('error', e.message); }
        },
        async add() {
            if (! this.canAppoint) return; this.busy = true;
            try {
                const body = { user_id: this.pick.id, role: this.role, compensation: this.comp, club: this.club };
                if (this.comp === 'paid') body.fee = parseFloat(this.fee);

                const res = await fetch('{{ route('me.events.officials.store', $event) }}', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json',
                               'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                    credentials: 'same-origin',
                    body: JSON.stringify(body),
                });
                const d = await res.json().catch(() => ({}));
                if (!res.ok || !d.success) throw new Error(d.message || 'Could not appoint');
                window.showToast('success', d.message);
                this.q = ''; this.sheet = false; this.pick = null; this.club = ''; await this.load();
            } catch (e) { window.showToast('error', e.message); }
            finally { this.busy = false; }
        },
        /* The second door: a person the platform has never met, appointed to a
           mat job. Same sheet, same role, same compensation question — only the
           payload differs, because there is no id to send. */
        async create() {
            if (! this.canCreate) return;
            this.busy = true;
            try {
                const body = {
                    full_name: this.fresh.full_name.trim(),
                    role: this.role,
                    compensation: this.comp,
                    gender: this.fresh.gender || null,
                    birthdate: this.fresh.birthdate || null,
                    phone: this.fresh.phone.trim() || null,
                    phone_code: this.fresh.phone_code || null,
                    photo: this.photo,
                    nationality: (this.fresh.nationality || '').trim().toUpperCase() || null,
                    club: this.club,
                };
                if (this.comp === 'paid') body.fee = parseFloat(this.fee);

                const res = await fetch('{{ route('me.events.officials.person', $event) }}', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json',
                               'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                    credentials: 'same-origin',
                    body: JSON.stringify(body),
                });
                const d = await res.json().catch(() => ({}));
                if (!res.ok || !d.success) throw new Error(d.message || 'Could not appoint');
                window.showToast('success', d.message);
                this.fresh = { full_name: '', gender: '', birthdate: '', phone: '', phone_code: '+973', nationality: '' };
                this.photo = null;
                this.club = ''; this.clubOpen = false;
                this.sheet = false; this.how = null;
                await this.load();
            } catch (e) { window.showToast('error', e.message); }
            finally { this.busy = false; }
        },
        async remove(id) {
            if (this.busy) return; this.busy = true;
            try {
                const res = await fetch(`{{ url('me/events/'.$event.'/officials') }}/${id}`, {
                    method: 'DELETE',
                    headers: { 'Accept': 'application/json',
                               'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                    credentials: 'same-origin',
                });
                const d = await res.json().catch(() => ({}));
                if (!res.ok || !d.success) throw new Error(d.message || 'Could not remove');
                window.showToast('success', d.message);
                await this.load();
            } catch (e) { window.showToast('error', e.message); }
            finally { this.busy = false; }
        },
        initials(n) { return (n || '').split(' ').map(p => p[0]).slice(0, 2).join(''); },
     }"
     x-init="
        load();
        if (window.__officialsCropped) window.removeEventListener('photo-capture:cropped', window.__officialsCropped);
        window.__officialsCropped = (ev) => {
            if (ev.detail && ev.detail.id === photoId) photo = ev.detail.base64 || null;
        };
        window.addEventListener('photo-capture:cropped', window.__officialsCropped);
     ">

    {{-- THE photo flow — the sheet (camera tile · gallery tile), the camera
         screen and the one cropper, exactly as a competitor's picture gets
         them. It saves nothing: the crop comes back on `photo-capture:cropped`
         and the panel stages it until the appointment is committed.

         Mounted HERE, outside the teleported panel below, so one teleport is
         never nested inside another. Stacked above the panel (z-[65]) and below
         the cropper's own editor, the same way the entry desk stacks it. --}}
    <x-photo-capture :id="$uid.'Photo'"
                     :color="$photoColor"
                     :title="__('events::events.add_person_photo')"
                     :hint="__('events::events.add_person_photo_sheet_hint')"
                     :z="74" :camera-z="76" />

    {{-- The control. Round 40px, matching the back button opposite it
         (Design Rule #6). --}}
    <button type="button" @click="panelOpen = true"
            class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur text-white grid place-items-center"
            aria-label="{{ __('personal.personal_event_officials_add') }}"
            title="{{ __('personal.personal_event_officials_add') }}">
        <i class="bi bi-person-plus"></i>
    </button>

    {{-- The sheet. Teleported to <body> so `bottom-0` and `max-h-[92vh]`
         resolve against the viewport rather than the mobile shell's
         transformed wrapper. --}}
    <template x-teleport="body">
        <div x-show="panelOpen" x-cloak class="fixed inset-0" style="z-index:65"
             @keydown.escape.window="panelOpen = false">
            <div x-show="panelOpen" x-transition.opacity
                 class="absolute inset-0 bg-black/50" @click="panelOpen = false"></div>

            <div x-show="panelOpen"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 class="absolute inset-x-0 bottom-0 flex flex-col bg-background rounded-t-3xl shadow-2xl"
                 style="max-height:92vh">

                {{-- Design Rule #8: every sheet opens with the gradient band. --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, #7c6bf5, #7c6bf5b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-person-badge text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('personal.event_manage_officials') }}</h3>
                            {{-- One line. A band is a heading, not a paragraph: three lines of
                                 explanation pushed the whole sheet down and read as
                                 terms and conditions. --}}
                            <p class="text-[12px] text-white/85 mt-0.5 truncate">{{ __('personal.personal_event_officials_intro') }}</p>
                        </div>
                        <button type="button" @click="panelOpen = false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                {{-- Body scrolls; the band above and the safe area below do not. --}}
                <div class="flex-1 min-h-0 overflow-y-auto px-5 py-4"
                     style="padding-bottom: calc(1rem + env(safe-area-inset-bottom));">


    {{-- Appointed, grouped by the job — an organiser checks "is anyone
         doing the weigh-in?", not "who is on the list?". --}}
    <div class="space-y-3">
        <template x-for="r in roles" :key="r.value">
            <div x-show="byRole(r.value).length" x-cloak>
                <p class="text-[10px] font-extrabold uppercase tracking-[0.12em] text-muted-foreground mb-1.5" x-text="r.label"></p>
                <div class="space-y-2">
                    <template x-for="o in byRole(r.value)" :key="o.id">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full grid place-items-center text-white text-[11px] font-bold flex-shrink-0 bg-primary overflow-hidden">
                                <template x-if="o.avatar"><img :src="o.avatar" alt="" class="w-full h-full object-cover"></template>
                                <template x-if="!o.avatar"><span x-text="initials(o.name)"></span></template>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-foreground truncate" x-text="o.name"></p>
                                <p class="text-[10px] text-muted-foreground truncate flex items-center gap-1.5">
                                    {{-- The flag the bout page will show, visible here so a
                                         missing country is noticed before the event, not after. --}}
                                    <template x-if="o.nationality">
                                        <span :class="'fi fi-' + o.nationality.toLowerCase()"
                                              style="width:14px;height:11px;background-size:cover;border-radius:2px;flex:none"></span>
                                    </template>
                                    <span x-text="o.email"></span><template x-if="o.phone"><span> · <span x-text="o.phone"></span></span></template>
                                    {{-- Whose referee this is. Independent is
                                         said out loud rather than left blank —
                                         a missing club reads as unrecorded. --}}
                                    <span> · </span><span class="font-semibold"
                                          x-text="o.club_name || @js(__('personal.personal_event_officials_club_none'))"></span>
                                </p>
                            </div>
                            <button type="button" @click="remove(o.id)" :disabled="busy"
                                    class="w-8 h-8 rounded-lg grid place-items-center text-red-500 hover:bg-red-50 transition-colors flex-shrink-0 disabled:opacity-50"
                                    title="{{ __('personal.personal_event_officials_remove') }}">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>

    {{-- Appointing. Nothing is on screen until it is asked for: one + Add
         button, and only when it is pressed does the picker open — a search
         box with the JOB it appoints to sitting right beside it, because the
         person you tap is appointed to whatever that button says. The role
         list itself expands in normal flow (never absolutely), since this
         panel lives inside a scrolling page and an absolute one is clipped. --}}
    {{-- ═════════ APPOINTING ═════════

         TWO DOORS, the same pair the entry desk has: find somebody already on
         the platform, or write down the referee the federation sent who has
         never opened this app. Asked for on 2026-09-14; before it there was
         only the search, so the second of those two could not be put on the
         sheet at all.

         ⚠️ NOTHING HERE IS ABSOLUTELY POSITIONED. This block lives inside the
         panel's own scrolling body, and an absolute dropdown in a scrolling
         container is clipped by it — the candidate list opened UPWARD and
         disappeared under the sheet's own header, with only its bottom edge
         showing. The results and the role list expand in normal flow instead
         (CLAUDE.md → Mobile Pattern Language: "an absolutely-positioned
         dropdown inside a scrolling sheet" is a listed anti-pattern). --}}
    <div class="mt-4">

        {{-- The chooser. Selection cards, not a dropdown: two known options. --}}
        <div x-show="how === null" x-cloak class="space-y-2">
            <button type="button"
                    @click="how = 'find'; open = true; $nextTick(() => $refs.search?.focus()); load()"
                    class="m-press w-full flex items-start gap-3 p-3 rounded-xl border-2 border-gray-200 bg-white text-start transition-colors hover:border-primary/40">
                <span class="w-10 h-10 rounded-xl bg-primary/10 text-primary grid place-items-center flex-shrink-0">
                    <i class="bi bi-search"></i>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-bold text-foreground">{{ __('personal.personal_event_officials_find') }}</span>
                    <span class="block text-[11px] text-muted-foreground mt-0.5 leading-snug">{{ __('personal.personal_event_officials_find_hint') }}</span>
                </span>
                <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground flex-shrink-0 mt-1"></i>
            </button>

            <button type="button" @click="how = 'new'; $nextTick(() => $refs.freshName?.focus())"
                    class="m-press w-full flex items-start gap-3 p-3 rounded-xl border-2 border-gray-200 bg-white text-start transition-colors hover:border-primary/40">
                <span class="w-10 h-10 rounded-xl bg-primary/10 text-primary grid place-items-center flex-shrink-0">
                    <i class="bi bi-person-plus"></i>
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-sm font-bold text-foreground">{{ __('personal.personal_event_officials_new') }}</span>
                    <span class="block text-[11px] text-muted-foreground mt-0.5 leading-snug">{{ __('personal.personal_event_officials_new_hint') }}</span>
                </span>
                <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground flex-shrink-0 mt-1"></i>
            </button>
        </div>

        {{-- ───────── The job ─────────
             Shown for BOTH doors, because an official without a role is not an
             appointment. It expands in flow; the chosen job is the one the next
             tap (or the Add button) appoints to. --}}
        <div x-show="how !== null" x-cloak class="space-y-3">
            <div class="flex items-center gap-2">
                <button type="button" @click="how = null; open = false; q = ''"
                        class="w-9 h-9 rounded-lg grid place-items-center text-muted-foreground hover:bg-muted/60 transition-colors flex-shrink-0"
                        aria-label="{{ __('shared.back') }}">
                    <i class="bi bi-chevron-left rtl:rotate-180 text-[13px]"></i>
                </button>
                <p class="text-[12px] font-bold text-foreground flex-1 min-w-0 truncate"
                   x-text="how === 'new' ? @js(__('personal.personal_event_officials_new')) : @js(__('personal.personal_event_officials_find'))"></p>
            </div>

            {{-- For the FIND door the job comes first: it decides which pool is
                 searched (a mat role searches the sport, a permission role the
                 club), so it cannot be asked afterwards. The NEW door asks it
                 last, under the person's own details. --}}
            <div x-show="how === 'find'" x-cloak>
                {{-- "Role", not "On the mat": this door offers BOTH kinds of
                     job and the panel heads each group itself, so the eyebrow
                     names the question rather than one of its answers. --}}
                <p class="text-[10px] font-extrabold uppercase tracking-[0.12em] text-muted-foreground mb-1.5">
                    {{ __('personal.personal_event_officials_role') }}
                </p>
                @include('events::partials.event-official-role', ['roleGroups' => ['mat', 'platform'], 'ref' => 'roleFind'])
            </div>

            {{-- ───────── Door 1: find somebody ───────── --}}
            <div x-show="how === 'find'" x-cloak class="space-y-2">
                <input type="text" x-ref="search" x-model="q" @input.debounce.250ms="load()"
                       placeholder="{{ __('personal.personal_event_officials_search') }}"
                       class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">

                {{-- Who the search is reaching right now. A MAT job searches the
                     people this SPORT knows — anyone who has officiated it,
                     competed in it, or belongs to a club that runs it — and a
                     PHONE NUMBER drops that narrowing, because somebody typing
                     a number already knows exactly who they mean. --}}
                <p class="text-[11px] text-muted-foreground"
                   x-text="(roleMeta.group !== 'mat'
                        ? @js(__('personal.personal_event_officials_pool_club'))
                        : (/^[\d\s+()\-.]{4,}$/.test(q)
                            ? @js(__('personal.personal_event_officials_phone_pool'))
                            : @js(__('personal.personal_event_officials_sport_pool')).replace(':sport', @js($sportLabel ?? '')))) "></p>

                {{-- The results, in the flow. --}}
                <div class="rounded-xl border border-gray-100 divide-y divide-gray-50 overflow-hidden" x-show="candidates.length" x-cloak>
                    <template x-for="c in candidates" :key="c.id">
                        <button type="button" @click="choose(c)" :disabled="busy || c.roles.includes(role)"
                                class="w-full flex items-center gap-2.5 px-3 py-2.5 text-start hover:bg-muted/60 transition-colors disabled:opacity-50">
                            <div class="w-7 h-7 rounded-full grid place-items-center bg-muted text-[10px] font-bold text-muted-foreground flex-shrink-0 overflow-hidden">
                                <template x-if="c.avatar"><img :src="c.avatar" alt="" class="w-full h-full object-cover"></template>
                                <template x-if="!c.avatar"><span x-text="initials(c.name)"></span></template>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-foreground truncate" x-text="c.name"></p>
                                <p class="text-[10px] text-muted-foreground truncate">
                                    <template x-if="c.roles.length">
                                        {{-- What they already do here matters more than their email. --}}
                                        <span class="text-primary font-bold" x-text="c.roles.map(r => roleLabel(r)).join(' · ')"></span>
                                    </template>
                                    <template x-if="! c.roles.length">
                                        <span><span x-text="c.email"></span><template x-if="c.phone"><span> · <span x-text="c.phone"></span></span></template></span>
                                    </template>
                                </p>
                            </div>
                            <span class="text-[10px] font-bold flex-shrink-0"
                                  :class="c.roles.includes(role) ? 'text-muted-foreground' : 'text-primary'"
                                  x-text="c.roles.includes(role) ? '{{ __('personal.personal_event_officials_appointed') }}' : '{{ __('personal.personal_event_officials_add') }}'"></span>
                        </button>
                    </template>
                </div>

                <p x-show="! candidates.length" x-cloak class="text-[11px] text-muted-foreground text-center py-3"
                   x-text="roleMeta.group === 'mat' && ! q
                        ? @js(__('personal.personal_event_officials_type_to_search'))
                        : @js(__('personal.personal_event_officials_no_matches'))"></p>
            </div>

            {{-- ───────── Door 2: somebody new ───────── --}}
            <div x-show="how === 'new'" x-cloak class="space-y-2">

                {{-- THE photograph — the same flow, the same cropper and the
                     same 3:4 portrait a competitor's picture goes through. An
                     officiating sheet is read by face as much as by name, and a
                     referee the federation sent has no profile picture to
                     borrow. Tapping the tile opens the camera/gallery sheet;
                     the crop comes back on `photo-capture:cropped` and is held
                     here until the appointment is committed. --}}
                <div class="flex items-start gap-3">
                    <button type="button" @click="pickPhoto()"
                            class="m-press w-24 h-32 rounded-2xl overflow-hidden border-2 border-gray-200 bg-muted grid place-items-center flex-shrink-0 relative"
                            :class="photo ? '' : 'border-dashed'"
                            aria-label="{{ __('events::events.add_person_photo_add') }}">
                        <template x-if="photo">
                            <img :src="photo" alt="" class="absolute inset-0 w-full h-full object-cover">
                        </template>
                        <template x-if="! photo">
                            <span class="text-muted-foreground text-center">
                                <i class="bi bi-person-bounding-box text-2xl"></i>
                            </span>
                        </template>
                    </button>

                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-bold text-foreground">{{ __('events::events.add_person_photo') }}</p>
                        <p class="text-[11.5px] text-muted-foreground mt-0.5 truncate"
                           title="{{ __('events::events.add_person_photo_hint') }}">{{ __('events::events.add_person_photo_hint') }}</p>
                        <div class="flex items-center gap-2 mt-2">
                            <button type="button" @click="pickPhoto()"
                                    class="m-press h-9 px-3 rounded-lg bg-white border border-gray-200 text-[12px] font-semibold text-foreground">
                                <i class="bi bi-camera-fill me-1"></i><span x-text="photo ? @js(__('events::events.add_person_photo_change')) : @js(__('events::events.add_person_photo_add'))"></span>
                            </button>
                            <button type="button" @click="dropPhoto()" x-show="photo" x-cloak
                                    class="m-press h-9 px-3 rounded-lg border border-red-200 text-red-600 text-[12px] font-semibold">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- The NAME — the one thing demanded. Everything under it is
                     asked for and never required: staff writing somebody else
                     down rarely have the rest, and an invented birthdate is
                     worse than a blank (CLAUDE.md → Who Fills The Form Decides
                     What It Demands). --}}
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('personal.personal_event_officials_new_name') }}</label>
                    <input type="text" x-ref="freshName" x-model="fresh.full_name"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                </div>

                {{-- GENDER, on the platform's own two-button control. --}}
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('members::member.gender_label') }}</label>
                    {{-- The labels are Alpine EXPRESSIONS, not text — the
                         component evaluates them — so they are handed over as
                         JS string literals. `<x-fields.gender-toggle model="…" />` on
                         its own defaults to 'Male'/'Female', which is what the
                         claim form uses; these are the translated ones. --}}
                    <x-fields.gender-toggle model="fresh.gender"
                                     :male-label="json_encode(__('members::member.templates_member_show_gender_male'))"
                                     :female-label="json_encode(__('members::member.templates_member_show_gender_female'))" />
                </div>

                {{-- DATE OF BIRTH, on the day/month/year picker with its live age
                     badge. `model` + `:inline` for the same reason as the
                     pickers below: this panel is teleported, and a picker whose
                     state comes from a script tag would arrive dead. --}}
                <x-fields.date-of-birth
                    :name="$uid.'_dob'" :id="$uid.'Dob'"
                    model="fresh.birthdate" :inline="true" :float="true"
                    :label="__('members::member.family_create_birthdate')"
                    :min-age="12" :max-age="100" />

                {{-- The PHONE, on the platform's own control: the searchable
                     dial-code picker with the number beside it in one group, so
                     what is stored is one dialable string. `model` mirrors the
                     code into this sheet's Alpine scope — create() joins the
                     two. Its default `tf-input-group` styling is deliberate:
                     the focus-within utilities a hand-rolled group would need
                     are NOT in the prebuilt Tailwind bundle and would silently
                     do nothing. --}}
                <div>
                    <label class="block text-xs font-medium text-gray-700 mb-1">{{ __('personal.personal_event_officials_new_phone') }}</label>
                    <x-fields.phone-code
                        :name="$uid.'_code'" :id="$uid.'Dial'"
                        model="fresh.phone_code" value="+973" :inline="true" :float="true">
                        <input type="tel" x-model="fresh.phone" maxlength="32" autocomplete="tel" inputmode="tel"
                               class="w-full px-3 py-2.5 bg-transparent text-sm border-0 outline-none focus:ring-0">
                    </x-fields.phone-code>
                </div>

                {{-- The COUNTRY, on the searchable country picker every other
                     form uses — an official is printed with a flag on every
                     officiating sheet, and a two-letter box asks a person to
                     know ISO-3166 by heart.

                     `:inline` is not optional here: this panel is teleported to
                     <body>, and the picker's named-function state would not be
                     defined in time inside a teleported template. Inline puts
                     the whole state in the x-data attribute, which survives it
                     (CLAUDE.md → Alpine state must be registered, not inline in
                     a script). --}}
                <x-fields.country
                    :name="$uid.'_nationality'" :id="$uid.'Nat'"
                    model="fresh.nationality" :inline="true"
                    :geo="true" :float="true"
                    :label="__('personal.personal_event_officials_new_country')"
                    wrapper-class="" />

                {{-- WHOSE referee this is — the club they came with, or
                     nobody's. Part of "everything else about the person", so it
                     sits above the job and the money. --}}
                @include('events::partials.event-official-club', ['ref' => 'clubNew'])

                {{-- ───────── THE JOB, and then the MONEY — last, in this order
                     (asked for 2026-09-14): the sheet asks WHO this person is
                     first, and the two decisions that commit the club to
                     something — what they will be doing, and whether they are
                     being paid for it — sit under the reader's eye at the
                     moment they press Add. --}}
                <div class="pt-1">
                    <p class="text-[10px] font-extrabold uppercase tracking-[0.12em] text-muted-foreground mb-1.5">
                        {{ __('personal.personal_event_officials_group_mat') }}
                    </p>
                    @include('events::partials.event-official-role', ['ref' => 'roleNew'])
                    <p class="text-[10.5px] text-muted-foreground mt-1.5 truncate"
                       title="{{ __('personal.personal_event_officials_new_mat_only') }}">
                        {{ __('personal.personal_event_officials_new_mat_only') }}
                    </p>
                </div>

                {{-- Volunteered or paid. A PAID appointment becomes a line in
                     this event's expenses the moment it is saved, kept in step
                     by the appointment itself (EventOfficial::syncExpense) and
                     refused to anyone trying to delete it from the books —
                     change the appointment instead. --}}
                <div>
                    <p class="text-[10px] font-extrabold uppercase tracking-[0.12em] text-muted-foreground mb-1.5">
                        {{ __('personal.event_officials_compensation') }}
                    </p>
                    <div class="grid grid-cols-2 gap-2">
                        <template x-for="c in compensations" :key="c.value">
                            <button type="button" @click="comp = c.value; if (c.value !== 'paid') fee = ''"
                                    class="m-press flex items-center gap-2 px-3 py-2.5 rounded-xl border-2 text-start transition-colors"
                                    :class="comp === c.value ? 'border-primary bg-primary/5' : 'border-gray-200 bg-white'">
                                <i class="bi flex-shrink-0"
                                   :class="c.value === 'paid' ? 'bi-cash-coin text-amber-600' : 'bi-heart text-primary'"></i>
                                <span class="text-[12.5px] font-bold text-foreground truncate" x-text="c.label"></span>
                            </button>
                        </template>
                    </div>

                    <div x-show="comp === 'paid'" x-cloak class="mt-2">
                        <label class="block text-xs font-medium text-gray-700 mb-1"
                               x-text="@js(__('personal.personal_event_officials_fee')) + (currency ? ' (' + currency + ')' : '')"></label>
                        <input type="number" x-model="fee" min="0" step="0.001" inputmode="decimal"
                               class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                        <p class="text-[10.5px] text-muted-foreground mt-1 truncate"
                           title="{{ __('personal.personal_event_officials_fee_becomes_expense') }}">{{ __('personal.personal_event_officials_fee_becomes_expense') }}</p>
                    </div>
                </div>

                <button type="button" @click="create()"
                        :disabled="! canCreate || (comp === 'paid' && ! (parseFloat(fee) > 0))"
                        :class="(! canCreate || (comp === 'paid' && ! (parseFloat(fee) > 0))) && 'opacity-50'"
                        class="m-press w-full py-3 rounded-xl bg-primary text-white text-sm font-bold">
                    <span x-show="! busy">{{ __('personal.personal_event_officials_add') }}</span>
                    <span x-show="busy" x-cloak><i class="bi bi-arrow-repeat animate-spin"></i></span>
                </button>
            </div>
        </div>
    </div>

    {{-- Appointment sheet. Teleported to <body> so the mobile shell's
         transformed wrapper cannot become its containing block and clip it;
         scrollable body, safe-area footer. --}}
    <template x-teleport="body">
        <div x-show="sheet" x-cloak class="fixed inset-0" style="z-index:70" @keydown.escape.window="sheet = false">
            <div x-show="sheet" x-transition.opacity class="absolute inset-0 bg-black/50" @click="sheet = false"></div>
            <div x-show="sheet"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 class="absolute inset-x-0 bottom-0 flex flex-col bg-background rounded-t-3xl shadow-2xl" style="max-height:92vh">

                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, #7c6bf5, #7c6bf5b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0 overflow-hidden text-xs font-bold">
                            <template x-if="pick && pick.avatar"><img :src="pick.avatar" alt="" class="w-full h-full object-cover"></template>
                            <template x-if="pick && !pick.avatar"><span x-text="initials(pick.name)"></span></template>
                            {{-- Nobody picked means the OTHER door: a person
                                 being written down, who has no record yet. --}}
                            <template x-if="! pick"><span x-text="initials(fresh.full_name)"></span></template>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight truncate" x-text="pick ? pick.name : fresh.full_name"></h3>
                            <p class="text-[12px] text-white/85 mt-0.5" x-text="roleLabel(role)"></p>
                        </div>
                        <button type="button" @click="sheet = false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="flex-1 min-h-0 overflow-y-auto px-5 pb-2">
                    {{-- Whose referee this is. Asked of a person the platform
                         already knows too: the club belongs to the APPOINTMENT,
                         and their profile cannot answer it — a member of one
                         club regularly officiates for the federation. --}}
                    <div class="mt-3">
                        @include('events::partials.event-official-club', ['ref' => 'clubPick'])
                    </div>

                    {{-- Volunteer or paid. Required by the endpoint, and a paid
                         appointment becomes an expense on the event. --}}
                    <p class="text-[10px] font-extrabold uppercase tracking-[0.12em] text-muted-foreground mt-3 mb-1.5">
                        {{ __('personal.personal_event_officials_pay') }}
                    </p>
                    <div class="space-y-2">
                        <template x-for="c in compensations" :key="c.value">
                            <button type="button" @click="comp = c.value"
                                    class="w-full flex items-start gap-3 p-3 rounded-xl border text-start transition-colors"
                                    :class="comp === c.value ? 'border-primary bg-primary/5' : 'border-gray-200 bg-white'">
                                <span class="w-5 h-5 rounded-full border-2 grid place-items-center flex-shrink-0 mt-0.5"
                                      :class="comp === c.value ? 'border-primary' : 'border-gray-300'">
                                    <span x-show="comp === c.value" class="w-2.5 h-2.5 rounded-full bg-primary"></span>
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-bold text-foreground" x-text="c.label"></span>
                                    <span class="block text-[11px] text-muted-foreground truncate" :title="c.hint" x-text="c.hint"></span>
                                </span>
                            </button>
                        </template>
                    </div>

                    <div x-show="comp === 'paid'" x-cloak class="mt-3">
                        <label class="block text-[11px] font-bold text-muted-foreground mb-1">
                            {{ __('personal.personal_event_officials_fee') }} <span x-text="currency"></span>
                        </label>
                        <input type="number" step="0.001" min="0.001" x-model="fee" inputmode="decimal"
                               class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">
                    </div>

                </div>

                <div class="flex-shrink-0 flex gap-2 px-5 pt-2" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="sheet = false"
                            class="flex-1 h-12 rounded-xl border border-gray-200 bg-white text-sm font-bold text-muted-foreground">
                        {{ __('shared.cancel') }}
                    </button>
                    {{-- One button, two doors: appoint the person who was
                         picked, or create the one who was written down. --}}
                    <button type="button" @click="pick ? add() : create()" :disabled="pick ? ! canAppoint : ! canCreate"
                            class="h-12 rounded-xl bg-primary text-white text-sm font-bold disabled:opacity-50" style="flex:1.4">
                        {{ __('personal.personal_event_officials_add') }}
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
                </div>
            </div>
        </div>
    </template>
</div>
@endif
