{{--
    <x-club-membership-sheet action="…/__ID__/club-membership" /> — "Add to a club".

    Makes a person a member of a club WITHOUT enrolling them in a package: for
    somebody new to the platform who has trained at the club, in a sport, since
    long before it existed. Picks the club, the sport (that club's own
    activities) and "member since" (month + year), then POSTs to `action`
    (PlatformController::addClubMembership), which creates the membership and
    records the history as a verified club affiliation. No subscription, no
    invoice.

    Opened from anywhere with:
        window.dispatchEvent(new CustomEvent('open-club-membership', {detail: {id: <user id>, name: '<name>'}}))
    Dispatches `member-club-membership-added` {id, membership} on success.

    Standalone: its own state, teleported to <body>, Design Rule #8 band.
    Super-admin surfaces only — the endpoint re-checks.
--}}
@props(['action'])
@php
    $clubs = \App\Clubs\Models\Tenant::query()->orderBy('club_name')->limit(500)
        ->with(['activities' => fn ($q) => $q->orderBy('name')])
        ->get()
        ->map(fn ($t) => [
            'slug' => $t->slug,
            'name' => $t->club_name,
            'logo' => $t->logo ? file_url($t->logo) : null,
            'activities' => $t->activities->map(fn ($a) => ['id' => $a->id, 'name' => $a->name])->values()->all(),
        ])->values()->all();
    $months = collect(range(1, 12))->map(fn ($m) => ['value' => str_pad((string) $m, 2, '0', STR_PAD_LEFT), 'label' => \Carbon\Carbon::create(2000, $m, 1)->translatedFormat('F')])->all();
    $years = collect(range((int) now()->year, 1960))->map(fn ($y) => ['value' => (string) $y, 'label' => (string) $y])->all();
@endphp
<div x-data="{
        open: false, busy: false, err: '', userId: null, name: '',
        clubs: @js($clubs), q: '', club: null, activity: null,
        month: '', year: '',
        get shown() { const q = this.q.trim().toLowerCase(); return this.clubs.filter(c => !q || c.name.toLowerCase().includes(q)); },
        get chosen() { return this.clubs.find(c => c.slug === this.club) || null; },
        get ready() { return !!(this.club && this.month && this.year) && !this.busy; },
        openFor(d) { this.userId = d.id; this.name = d.name || ''; this.q = ''; this.club = null; this.activity = null;
                     const n = new Date(); this.month = ''; this.year = ''; this.err = ''; this.busy = false; this.open = true; },
        pick(c) { this.club = c.slug; this.activity = c.activities.length === 1 ? c.activities[0].id : null; this.err = ''; },
        async save() {
            if (!this.ready) { this.err = !this.club ? '{{ __('Choose the club.') }}' : '{{ __('Choose when they started — month and year.') }}'; return; }
            const since = this.year + '-' + this.month, n = new Date();
            if (since > n.getFullYear() + '-' + String(n.getMonth() + 1).padStart(2, '0')) { this.err = '{{ __('Member since cannot be in the future.') }}'; return; }
            this.busy = true; this.err = '';
            try {
                const r = await fetch(@js($action).replace('__ID__', this.userId), {
                    method: 'POST', credentials: 'same-origin',
                    headers: {'Content-Type': 'application/json', 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest',
                              'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''},
                    body: JSON.stringify({club: this.club, activity: this.activity, since})
                });
                const j = await r.json().catch(() => ({}));
                if (!r.ok || !j.success) { this.err = j.message || (j.errors ? Object.values(j.errors)[0][0] : '{{ __('That did not work. Try again.') }}'); this.busy = false; return; }
                window.showToast && window.showToast('success', j.message);
                window.dispatchEvent(new CustomEvent('member-club-membership-added', {detail: {id: this.userId, membership: j.membership}}));
                this.busy = false; this.open = false;
            } catch (e) { this.err = '{{ __('Could not reach the server. Try again.') }}'; this.busy = false; }
        }
     }"
     @open-club-membership.window="openFor($event.detail || {})"
     @keydown.escape.window="open = false">
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[80] flex items-end sm:items-center justify-center">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-gray-900/50" @click="if (!busy) open = false"></div>
            <div x-show="open"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-6" x-transition:enter-end="opacity-100 translate-y-0"
                 class="relative w-full sm:max-w-lg max-h-[92vh] flex flex-col bg-white rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden"
                 role="dialog" aria-modal="true" aria-label="{{ __('Add to a club') }}">

                {{-- Band (Design Rule #8) --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, #7c6bf5, #7c6bf5b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3 sm:hidden"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0"><i class="bi bi-person-plus-fill text-xl"></i></span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('Add to a club') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5" x-text="name ? name + ' — {{ __('no package needed') }}' : '{{ __('No package needed') }}'"></p>
                        </div>
                        <button type="button" @click="open = false" aria-label="{{ __('Close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-5">
                    <p class="text-xs text-muted-foreground leading-relaxed">{{ __('For someone who has trained at the club since before TakeOne — they become an active member straight away. No package, subscription or invoice is created.') }}</p>

                    {{-- Club --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('Club') }}</label>
                        <div class="relative mb-2">
                            <i class="bi bi-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                            <input type="text" x-model="q" placeholder="{{ __('Search clubs') }}"
                                   class="w-full pl-9 pr-3 py-2.5 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                        </div>
                        <div class="space-y-2 max-h-56 overflow-y-auto">
                            <template x-for="c in shown" :key="c.slug">
                                <button type="button" @click="pick(c)"
                                        class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl border text-start transition-colors"
                                        :class="club === c.slug ? 'border-primary bg-primary/5' : 'border-gray-200 hover:bg-muted/60'">
                                    <span class="w-9 h-9 flex-shrink-0 grid place-items-center">
                                        <template x-if="c.logo"><img :src="c.logo" alt="" class="w-full h-full object-contain"></template>
                                        <template x-if="!c.logo"><i class="bi bi-building text-lg text-muted-foreground"></i></template>
                                    </span>
                                    <span class="flex-1 min-w-0 text-sm font-semibold text-gray-800 truncate" x-text="c.name"></span>
                                    <span class="w-5 h-5 rounded-full border-2 grid place-items-center flex-shrink-0"
                                          :class="club === c.slug ? 'border-primary bg-primary text-white' : 'border-gray-300'"><i class="bi bi-check text-xs" x-show="club === c.slug"></i></span>
                                </button>
                            </template>
                            <p x-show="!shown.length" class="text-xs text-muted-foreground py-3 text-center">{{ __('No club matches.') }}</p>
                        </div>
                    </div>

                    {{-- Sport, from the chosen club's own activities --}}
                    <div x-show="chosen" x-transition>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('Sport they practise there') }} <span class="text-muted-foreground font-normal">{{ __('(optional)') }}</span></label>
                        <div class="flex flex-wrap gap-2">
                            <template x-for="a in (chosen ? chosen.activities : [])" :key="a.id">
                                <button type="button" @click="activity = (activity === a.id ? null : a.id)"
                                        class="px-3 py-2 rounded-full border text-xs font-semibold transition-colors"
                                        :class="activity === a.id ? 'border-primary bg-primary text-white' : 'border-gray-200 text-gray-700 hover:bg-muted/60'"
                                        x-text="a.name"></button>
                            </template>
                            <p x-show="chosen && !chosen.activities.length" class="text-xs text-muted-foreground">{{ __('This club has no activities set up yet.') }}</p>
                        </div>
                    </div>

                    {{-- Member since --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ __('Member since') }}</label>
                        <div class="grid grid-cols-2 gap-2">
                            <x-fields.select model="month" :options="$months" :placeholder="__('Month')" />
                            <x-fields.select model="year" :options="$years" :placeholder="__('Year')" />
                        </div>
                    </div>

                    <p x-show="err" x-cloak class="text-sm text-red-600" x-text="err"></p>
                </div>

                <div class="flex-shrink-0 px-5 pt-3 border-t border-gray-100 bg-white flex gap-2" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="open = false" class="flex-1 border border-gray-200 text-gray-700 px-4 py-2.5 rounded-xl text-sm font-medium hover:bg-muted/60">{{ __('Cancel') }}</button>
                    <button type="button" @click="save()" :disabled="busy"
                            class="flex-1 bg-primary text-white px-4 py-2.5 rounded-xl text-sm font-semibold hover:bg-primary/90 transition-colors disabled:opacity-60"
                            :class="!ready && !busy ? 'opacity-60' : ''">
                        <span x-show="!busy">{{ __('Add to club') }}</span><span x-show="busy">{{ __('Adding…') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
