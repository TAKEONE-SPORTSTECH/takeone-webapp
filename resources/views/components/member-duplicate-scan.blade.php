{{--
    <x-member-duplicate-scan /> — "Scan for duplicates" (super-admin).

    Looks across every live account for pairs that seem to be ONE person
    (same email, same name, same phone, same birthday + first name —
    App\Members\Support\AccountMerger::scan()), strongest first, and lays each
    pair out side by side to be examined. Nothing is merged from here:
    "Review & merge" opens <x-member-merge-sheet> on that pair (combined view,
    conflicts to pick, type MERGE), and "Not the same person" records the pair
    so the scan stops offering it.

    Opened with:  window.dispatchEvent(new CustomEvent('open-member-scan'))
    Re-scans itself after `member-merged` / `member-merge-undone`.
    Needs <x-member-merge-sheet> on the page (the member popup includes it).

    Standalone: own state, teleported to <body>, Design Rule #8 band. Every
    server value is drawn with x-text / :src — never innerHTML. The two
    routes re-check super-admin on every call; this sheet decides nothing.
--}}
@php
    $scanUrls = [
        'scan' => route('admin.platform.members.duplicates'),
        'dismiss' => route('admin.platform.members.duplicates.dismiss'),
    ];
@endphp
<div x-data="{
        urls: @js($scanUrls),
        open: false, loading: false, err: '', pairs: [], filter: 'all', busyKey: null, scanned: false,
        get shown() { return this.filter === 'strong' ? this.pairs.filter(p => p.strong) : this.pairs; },
        get strongCount() { return this.pairs.filter(p => p.strong).length; },
        headers(json) { const h = {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || ''}; if (json) h['Content-Type'] = 'application/json'; return h; },
        start() { this.open = true; this.filter = 'all'; this.scan(); },
        async scan() {
            this.loading = true; this.err = '';
            try {
                const r = await fetch(this.urls.scan, {credentials: 'same-origin', headers: this.headers(false)});
                const j = await r.json().catch(() => ({}));
                if (!r.ok || !j.success) throw new Error(r.status === 429 ? '{{ __('Too many scans — wait a minute.') }}' : (j.message || '{{ __('The scan did not run. Try again.') }}'));
                this.pairs = j.pairs; this.scanned = true;
            } catch (e) { this.err = e.message; }
            this.loading = false;
        },
        review(p) {
            window.dispatchEvent(new CustomEvent('open-member-merge', {detail: {id: p.a.id, name: p.a.name, self: p.a, other: p.b}}));
        },
        async notSame(p) {
            if (this.busyKey) return;
            if (window.confirmAction) {
                const ok = await window.confirmAction({title: '{{ __('Not the same person?') }}', message: p.a.name + ' {{ __('and') }} ' + p.b.name + ' {{ __('will no longer be offered as a duplicate.') }}', type: 'warning', confirmText: '{{ __('Not the same person') }}'});
                if (!ok) return;
            }
            this.busyKey = p.key; this.err = '';
            try {
                const r = await fetch(this.urls.dismiss, {method: 'POST', credentials: 'same-origin', headers: this.headers(true), body: JSON.stringify({a: p.a.id, b: p.b.id})});
                const j = await r.json().catch(() => ({}));
                if (!r.ok || !j.success) throw new Error(j.message || '{{ __('That did not save. Try again.') }}');
                this.pairs = this.pairs.filter(x => x.key !== p.key);
                window.showToast && window.showToast('success', j.message);
            } catch (e) { this.err = e.message; }
            this.busyKey = null;
        },
        line(u) { return [u.email, u.mobile, u.birthdate].filter(Boolean).join(' · ') || '{{ __('No contact details') }}'; }
     }"
     @open-member-scan.window="start()"
     @member-merged.window="if (open) scan()"
     @member-merge-undone.window="if (open) scan()"
>
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-end sm:items-center justify-center">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-gray-900/50" @click="open = false"></div>
            <div x-show="open"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-6" x-transition:enter-end="opacity-100 translate-y-0"
                 class="relative w-full sm:max-w-2xl max-h-[92vh] flex flex-col bg-white rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden"
                 role="dialog" aria-modal="true" aria-label="{{ __('Scan for duplicates') }}">

                {{-- Band (Design Rule #8) --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, #7c6bf5, #7c6bf5b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3 sm:hidden"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0"><i class="bi bi-people text-xl"></i></span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('Scan for duplicates') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5"
                               x-text="loading && !scanned ? '{{ __('Looking across every account…') }}' : (pairs.length ? pairs.length + ' {{ __('possible duplicates — examine each before merging') }}' : '{{ __('Accounts that look like the same person') }}')"></p>
                        </div>
                        <button type="button" @click="scan()" :disabled="loading" aria-label="{{ __('Scan again') }}" title="{{ __('Scan again') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-arrow-clockwise" :class="loading && 'animate-spin'"></i>
                        </button>
                        <button type="button" @click="open = false" aria-label="{{ __('Close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div x-show="pairs.length" class="relative mt-3 flex flex-wrap gap-1.5">
                        <button type="button" @click="filter = 'all'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold transition-colors"
                                :class="filter === 'all' ? 'bg-white text-primary' : 'bg-white/20 text-white'">{{ __('All') }} · <span x-text="pairs.length"></span></button>
                        <button type="button" @click="filter = 'strong'" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-bold transition-colors"
                                :class="filter === 'strong' ? 'bg-white text-primary' : 'bg-white/20 text-white'"><i class="bi bi-lightning-charge-fill"></i> {{ __('Strong matches') }} · <span x-text="strongCount"></span></button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-3" style="padding-bottom: calc(1rem + env(safe-area-inset-bottom));">
                    <div x-show="loading && !scanned" class="py-14 grid place-items-center gap-3">
                        <div class="w-8 h-8 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
                        <p class="text-xs text-muted-foreground">{{ __('Comparing names, emails, phones and birthdays…') }}</p>
                    </div>

                    <p x-show="err" x-cloak class="rounded-xl bg-red-50 border border-red-100 px-3 py-2 text-sm text-red-700" x-text="err"></p>

                    <div x-show="scanned && !loading && !pairs.length && !err" class="py-12 text-center">
                        <span class="mx-auto w-16 h-16 rounded-full bg-green-50 text-green-600 grid place-items-center m-float"><i class="bi bi-person-check text-3xl"></i></span>
                        <p class="text-base font-black text-gray-900 mt-3">{{ __('No duplicates found') }}</p>
                        <p class="text-xs text-muted-foreground mt-1">{{ __('Every account looks like a different person.') }}</p>
                    </div>

                    <template x-for="p in shown" :key="p.key">
                        <div class="rounded-2xl border p-3 transition-opacity" :class="[p.strong ? 'border-primary/30 bg-primary/5' : 'border-gray-100 bg-white', busyKey === p.key && 'opacity-50']">
                            <div class="flex flex-wrap gap-1 mb-2.5">
                                <span x-show="p.strong" class="px-2 py-0.5 rounded-full bg-primary text-white text-[10px] font-bold"><i class="bi bi-lightning-charge-fill"></i> {{ __('Strong') }}</span>
                                <template x-for="why in p.reasons" :key="why"><span class="px-2 py-0.5 rounded-full bg-amber-50 text-amber-700 text-[10px] font-bold" x-text="why"></span></template>
                            </div>

                            <div class="grid items-center gap-2" style="grid-template-columns: minmax(0,1fr) auto minmax(0,1fr);">
                                <template x-for="(u, n) in [p.a, p.b]" :key="p.key + '-' + u.id">
                                    <div class="contents">
                                        <span x-show="n === 1" class="w-7 h-7 rounded-full bg-white border border-gray-200 text-primary grid place-items-center text-xs"><i class="bi bi-arrow-left-right"></i></span>
                                        <div class="flex gap-2.5 min-w-0" :style="n === 1 ? 'flex-direction: row-reverse; text-align: end' : ''">
                                            <span class="w-12 h-16 rounded-xl overflow-hidden bg-muted flex-shrink-0"><img :src="u.photo" alt="" class="w-full h-full object-cover object-top" loading="lazy"></span>
                                            <span class="min-w-0">
                                                <span class="block text-sm font-black text-gray-900 leading-tight break-words" x-text="u.name"></span>
                                                <span class="block text-[11px] text-muted-foreground break-words mt-0.5" x-text="line(u)"></span>
                                                <span class="block text-[10px] text-muted-foreground mt-0.5" x-text="(u.clubs.join(', ') || '{{ __('No club') }}') + ' · ' + '{{ __('Joined') }} ' + (u.joined || '—')"></span>
                                                <span x-show="u.signs_in" class="inline-block mt-1 px-2 py-0.5 rounded-full bg-green-50 text-green-700 text-[10px] font-bold"><i class="bi bi-key-fill"></i> {{ __('Signs in') }}</span>
                                            </span>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <div class="flex gap-2 mt-3">
                                <button type="button" @click="notSame(p)" :disabled="busyKey === p.key"
                                        class="flex-1 border border-gray-200 text-gray-600 bg-white px-3 py-2 rounded-xl text-xs font-semibold hover:border-red-200 hover:text-red-600 transition-colors inline-flex items-center justify-center gap-1.5">
                                    <i class="bi bi-person-x"></i>{{ __('Not the same person') }}
                                </button>
                                <button type="button" @click="review(p)"
                                        class="m-press flex-1 bg-primary text-white px-3 py-2 rounded-xl text-xs font-semibold hover:bg-primary/90 transition-colors inline-flex items-center justify-center gap-1.5">
                                    <i class="bi bi-union"></i>{{ __('Review & merge') }}
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>
