@props([
    /* The event these officials are for. Required: every request names it. */
    'event',
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
@if($canManage)
<div x-data="{

        officials: [], candidates: [], roles: [], role: 'jury', roleChosen: false, q: '', open: false, panelOpen: false, busy: false, loaded: false,
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
        choose(c) { this.pick = c; this.comp = 'volunteer'; this.fee = ''; this.open = false; this.sheet = true; },
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
            } catch (e) { window.showToast('error', e.message); }
        },
        async add() {
            if (! this.canAppoint) return; this.busy = true;
            try {
                const body = { user_id: this.pick.id, role: this.role, compensation: this.comp };
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
                this.q = ''; this.sheet = false; this.pick = null; await this.load();
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
     x-init="load()">

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
                            <p class="text-[12px] text-white/85 mt-0.5">{{ __('personal.personal_event_officials_intro') }}</p>
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

        <p x-show="loaded && !officials.length" x-cloak
           class="text-[11px] text-muted-foreground text-center py-2">
            {{ __('personal.personal_event_officials_none') }}
        </p>
    </div>

    {{-- Appointing. Nothing is on screen until it is asked for: one + Add
         button, and only when it is pressed does the picker open — a search
         box with the JOB it appoints to sitting right beside it, because the
         person you tap is appointed to whatever that button says. The role
         list itself expands in normal flow (never absolutely), since this
         panel lives inside a scrolling page and an absolute one is clipped. --}}
    <div class="mt-4" x-data="{ adding: false, pickerOpen: false }">
        <button type="button" x-show="! adding"
                @click="adding = true; pickerOpen = false; $nextTick(() => $refs.search?.focus())"
                class="w-full flex items-center justify-center gap-2 px-3 py-2.5 rounded-xl border border-dashed border-primary/40 text-primary text-sm font-bold hover:bg-primary/5 transition-colors">
            <i class="bi bi-plus-lg"></i>
            {{ __('personal.personal_event_officials_appoint') }}
        </button>

        <div x-show="adding" x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0">

            <div class="flex items-center gap-2">
                {{-- Search, and its results. Absolute inside this wrapper only —
                     a short list over the row below, not a page reflow. --}}
                <div class="relative flex-1 min-w-0" @click.outside="open = false">
                    <input type="text" x-ref="search" x-model="q" @focus="open = true" @input.debounce.250ms="load()"
                           placeholder="{{ __('personal.personal_event_officials_search') }}"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent outline-none">

                    {{-- Opens UPWARD. The search box sits near the bottom of a
                         long edit page, so a list dropping down was half
                         off-screen and covered the role picker under it. --}}
                    <div x-show="open" x-cloak x-transition.opacity.duration.120ms
                         class="absolute inset-x-0 bottom-full mb-2 max-h-64 overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-xl z-40 py-1">
                        <template x-for="c in candidates" :key="c.id">
                            <button type="button" @click="choose(c)" :disabled="busy || c.roles.includes(role)"
                                    class="w-full flex items-center gap-2.5 px-3 py-2 text-start hover:bg-muted/60 transition-colors disabled:opacity-50">
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

                        <p x-show="! candidates.length" x-cloak class="text-[11px] text-muted-foreground text-center py-3"
                           x-text="roleMeta.group === 'mat' && ! q
                                ? @js(__('personal.personal_event_officials_type_to_search'))
                                : @js(__('personal.personal_event_officials_no_matches'))"></p>
                    </div>
                </div>

                {{-- The job, beside the search rather than above it: it is what
                     the next tap appoints to, so it must never scroll away. --}}
                <div class="relative flex-shrink-0 max-w-[46%]">
                    <button type="button" @click="pickerOpen = ! pickerOpen"
                            class="w-full flex items-center gap-1.5 px-2.5 py-2.5 rounded-xl border bg-white transition-colors"
                            :class="pickerOpen ? 'ring-2 ring-purple-500 border-transparent' : 'border-gray-200'">
                        <i class="bi flex-shrink-0"
                           :class="roleMeta.group === 'platform' ? 'bi-shield-lock text-amber-600' : 'bi-person-arms-up text-primary'"></i>
                        <span class="text-[12px] font-bold text-foreground truncate" x-text="roleLabel(role)"></span>
                        <i class="bi bi-chevron-down text-muted-foreground text-[11px] transition-transform flex-shrink-0"
                           :class="pickerOpen && 'rotate-180'"></i>
                    </button>

                    {{-- The thirteen jobs a Karate tournament has, grouped so the
                         ones that carry ACCESS are visibly apart from those that
                         run a mat. Opens UPWARD, like the candidate list: this row
                         sits low on a long edit page, and thirteen rows dropping
                         down landed off-screen. --}}
                    <div x-show="pickerOpen" x-cloak @click.outside="pickerOpen = false"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         class="absolute end-0 bottom-full mb-2 w-64 max-w-[80vw] max-h-72 overflow-y-auto rounded-xl border border-gray-200 bg-white shadow-xl z-50">
                        <template x-for="g in ['mat', 'platform']" :key="g">
                            <div x-show="roles.some(r => r.group === g)">
                                <p class="px-3 pt-2.5 pb-1 text-[10px] font-extrabold uppercase tracking-[0.12em] text-muted-foreground"
                                   x-text="g === 'platform' ? @js(__('personal.personal_event_officials_group_platform')) : @js(__('personal.personal_event_officials_group_mat'))"></p>
                                <template x-for="r in roles.filter(x => x.group === g)" :key="r.value">
                                    <button type="button" @click="setRole(r.value); pickerOpen = false; $nextTick(() => $refs.search?.focus())"
                                            class="w-full flex items-start gap-3 px-3 py-2.5 text-start transition-colors"
                                            :class="role === r.value ? 'bg-primary/5' : 'hover:bg-muted/60'">
                                        <span class="w-4 h-4 mt-0.5 rounded-full border-2 grid place-items-center flex-shrink-0"
                                              :class="role === r.value ? 'border-primary' : 'border-gray-300'">
                                            <span x-show="role === r.value" class="w-2 h-2 rounded-full bg-primary"></span>
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-[13px] font-bold leading-tight"
                                                  :class="role === r.value ? 'text-primary' : 'text-foreground'" x-text="r.label"></span>
                                            <span x-show="r.hint" class="block text-[10px] text-muted-foreground leading-snug mt-0.5" x-text="r.hint"></span>
                                        </span>
                                    </button>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                <button type="button" @click="adding = false; pickerOpen = false; open = false; q = ''"
                        class="w-9 h-9 rounded-lg grid place-items-center text-muted-foreground hover:bg-muted/60 transition-colors flex-shrink-0"
                        title="{{ __('personal.personal_event_officials_remove') }}">
                    <i class="bi bi-x-lg text-[13px]"></i>
                </button>
            </div>

            {{-- Who the search reaches, and what this job carries. --}}
            <p class="text-[11px] text-muted-foreground mt-1.5"
               x-text="(roleMeta.group === 'mat'
                    ? @js(__('personal.personal_event_officials_pool_wide'))
                    : @js(__('personal.personal_event_officials_pool_club'))) + ' · ' + roleMeta.hint"></p>

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
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight truncate" x-text="pick ? pick.name : ''"></h3>
                            <p class="text-[12px] text-white/85 mt-0.5" x-text="roleLabel(role)"></p>
                        </div>
                        <button type="button" @click="sheet = false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="flex-1 min-h-0 overflow-y-auto px-5 pb-2">
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
                                    <span class="block text-[11px] text-muted-foreground" x-text="c.hint"></span>
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
                    <button type="button" @click="add()" :disabled="!canAppoint"
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
