{{--
    <x-club-removal /> — the super-admin's "Remove club" (the Platform module's ClubRemoval service).

    Include ONCE on a page; open it from anywhere with
        window.dispatchEvent(new CustomEvent('open-club-removal', {detail: {id: 12, name: 'Club name'}}))

    A review first, then the removal: it reads what removing the club would do
    (who is deleted, who is only unlinked, which events lose entries, the
    federation records, what blocks it), asks for the club's name to be typed,
    and posts back the fingerprint of exactly what was reviewed — the server
    refuses if anything changed in between. On success it dispatches
    `club-removed` {id} so the host can take the club off its list.

    Draws nothing and answers nothing while CLUB_REMOVAL_TOOL is off. Every
    server value is rendered with x-text, never as HTML.
--}}
@if(config('features.club_removal_tool'))

<div x-data="{
        urlTemplate: @js(route('admin.platform.clubs.removal', '__CLUB__')), open: false, state: 'idle', club: null, plan: null, error: '', typed: '', done: null,
        showDelete: false, showKeep: false,
        url() { return this.urlTemplate.replace('__CLUB__', encodeURIComponent(this.club.id)); },
        start(detail) {
            if (!detail || !detail.id) return;
            this.club = {id: detail.id, name: detail.name || ''};
            this.open = true; this.state = 'loading'; this.plan = null; this.error = ''; this.typed = ''; this.done = null;
            this.showDelete = false; this.showKeep = false;
            this.load();
        },
        load() {
            this.state = 'loading';
            fetch(this.url(), {credentials: 'same-origin', headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}})
                .then(r => r.json().then(j => ({ok: r.ok, j})))
                .then(({ok, j}) => {
                    if (!ok || !j.success) throw new Error(j.message || 'Could not read this club.');
                    this.plan = j.plan; this.club.name = j.plan.club.name; this.state = 'review';
                })
                .catch(e => { this.error = e.message || 'Could not read this club.'; this.state = 'error'; });
        },
        get matches() { return this.plan && this.typed.trim() === this.plan.club.name.trim(); },
        get blocked() { return !!(this.plan && this.plan.blockers.length); },
        get canRemove() { return this.state === 'review' && this.matches && !this.blocked; },
        remove() {
            if (!this.canRemove) return;
            this.state = 'removing'; this.error = '';
            const csrf = document.querySelector('meta[name=csrf-token]');
            fetch(this.url(), {method: 'POST', credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf ? csrf.content : ''},
                body: JSON.stringify({confirm_name: this.typed, fingerprint: this.plan.fingerprint})})
                .then(r => r.json().catch(() => ({})).then(j => ({status: r.status, j})))
                .then(({status, j}) => {
                    if (j.success) {
                        this.done = j; this.state = 'done';
                        window.dispatchEvent(new CustomEvent('club-removed', {detail: {id: this.club.id}}));
                        if (window.showToast) window.showToast('success', j.message);
                        return;
                    }
                    if (status === 409) { this.typed = ''; this.load(); if (window.showToast) window.showToast('warning', j.message); return; }
                    this.error = j.message || 'The club could not be removed.'; this.state = 'review';
                })
                .catch(() => { this.error = 'Could not reach the server. Nothing was changed.'; this.state = 'review'; });
        },
        close() { if (this.state !== 'removing') this.open = false; },
    }"
     @open-club-removal.window="start($event.detail)"
     @keydown.escape.window="open && close()">
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 flex items-end sm:items-center justify-center sm:p-6" style="z-index:70" role="dialog" aria-modal="true" aria-labelledby="club-removal-title">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="close()"></div>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-6" x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0 translate-y-6"
                 class="relative w-full sm:max-w-lg bg-background rounded-t-3xl sm:rounded-3xl shadow-2xl flex flex-col overflow-hidden" style="max-height:92vh">

                {{-- Header band (Design Rule #8): the destructive colour, because this one does not come back. --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, #dc2626, #dc2626b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3 sm:hidden"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-building-x text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 id="club-removal-title" class="text-lg font-black leading-tight">
                                {{ __('Remove') }} <span x-text="club ? club.name : ''"></span>
                            </h3>
                            <p class="text-[12px] text-white/85 mt-0.5">{{ __('The club and everything that is only the club’s') }}</p>
                        </div>
                        <button type="button" @click="close()" aria-label="{{ __('shared.close') }}" :disabled="state === 'removing'"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                {{-- Body --}}
                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">

                    <template x-if="state === 'loading'">
                        <div class="space-y-3">
                            <div class="grid grid-cols-2 gap-3">
                                <div class="h-20 rounded-2xl bg-muted animate-pulse"></div><div class="h-20 rounded-2xl bg-muted animate-pulse"></div>
                                <div class="h-20 rounded-2xl bg-muted animate-pulse"></div><div class="h-20 rounded-2xl bg-muted animate-pulse"></div>
                            </div>
                            <p class="text-xs text-muted-foreground text-center">{{ __('Working out everything that belongs to this club…') }}</p>
                        </div>
                    </template>

                    <template x-if="state === 'error'">
                        <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700 flex gap-3">
                            <i class="bi bi-exclamation-octagon text-lg"></i>
                            <div class="flex-1"><p x-text="error"></p>
                                <button type="button" @click="load()" class="mt-2 text-xs font-semibold underline">{{ __('Try again') }}</button></div>
                        </div>
                    </template>

                    <template x-if="plan && (state === 'review' || state === 'removing')">
                        <div class="space-y-4">

                            {{-- What stops it --}}
                            <template x-if="blocked">
                                <div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                                    <p class="font-bold flex items-center gap-2"><i class="bi bi-slash-circle"></i>{{ __('This club cannot be removed right now') }}</p>
                                    <ul class="mt-2 space-y-1 list-disc ps-5"><template x-for="b in plan.blockers" :key="b"><li x-text="b"></li></template></ul>
                                </div>
                            </template>

                            {{-- The four numbers that matter --}}
                            <div class="grid grid-cols-2 gap-3">
                                <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-3.5">
                                    <p class="text-2xl font-black text-red-600 tabular-nums" x-text="plan.delete.length"></p>
                                    <p class="text-[11px] font-semibold text-foreground mt-0.5">{{ __('Athletes deleted') }}</p>
                                    <p class="text-[10px] text-muted-foreground"><span x-text="plan.photos"></span> {{ __('with photos') }}</p>
                                </div>
                                <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-3.5">
                                    <p class="text-2xl font-black text-foreground tabular-nums" x-text="plan.keep.length"></p>
                                    <p class="text-[11px] font-semibold text-foreground mt-0.5">{{ __('People kept') }}</p>
                                    <p class="text-[10px] text-muted-foreground">{{ __('only unlinked from the club') }}</p>
                                </div>
                                <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-3.5">
                                    <p class="text-2xl font-black text-foreground tabular-nums" x-text="plan.events.reduce((n, e) => n + e.removed, 0)"></p>
                                    <p class="text-[11px] font-semibold text-foreground mt-0.5">{{ __('Event entries removed') }}</p>
                                    <p class="text-[10px] text-muted-foreground"><span x-text="plan.events.length"></span> {{ __('events') }}</p>
                                </div>
                                <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-3.5">
                                    <p class="text-2xl font-black text-foreground tabular-nums" x-text="plan.federation.memberships + plan.federation.applications + plan.federation.entries"></p>
                                    <p class="text-[11px] font-semibold text-foreground mt-0.5">{{ __('Federation records') }}</p>
                                    <p class="text-[10px] text-muted-foreground"><span x-text="plan.officials"></span> {{ __('officials appointments') }}</p>
                                </div>
                            </div>

                            {{-- Who is deleted (closed by default, count readable) --}}
                            <div class="rounded-2xl bg-white border border-gray-100 shadow-sm overflow-hidden" x-show="plan.delete.length">
                                <button type="button" @click="showDelete = !showDelete" class="w-full flex items-center gap-3 px-4 py-3 text-start">
                                    <span class="w-9 h-9 rounded-xl bg-red-50 text-red-600 grid place-items-center flex-shrink-0"><i class="bi bi-person-x"></i></span>
                                    <span class="flex-1 min-w-0"><span class="block text-sm font-bold text-foreground">{{ __('Deleted permanently') }}</span>
                                        <span class="block text-[11px] text-muted-foreground">{{ __('Created by the club, no account of their own, no other ties') }}</span></span>
                                    <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-red-50 text-red-700" x-text="plan.delete.length"></span>
                                    <i class="bi bi-chevron-down text-muted-foreground transition-transform" :class="showDelete && 'rotate-180'"></i>
                                </button>
                                <div x-show="showDelete" x-transition class="border-t border-gray-100 max-h-60 overflow-y-auto">
                                    <template x-for="p in plan.delete" :key="p.id">
                                        <div class="flex items-center gap-2 px-4 py-2 text-sm border-b border-gray-50 last:border-0">
                                            <i class="bi text-muted-foreground" :class="p.photo ? 'bi-image' : 'bi-person'"></i>
                                            <span class="flex-1 text-foreground" x-text="p.name"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            {{-- Who is kept --}}
                            <div class="rounded-2xl bg-white border border-gray-100 shadow-sm overflow-hidden" x-show="plan.keep.length">
                                <button type="button" @click="showKeep = !showKeep" class="w-full flex items-center gap-3 px-4 py-3 text-start">
                                    <span class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-700 grid place-items-center flex-shrink-0"><i class="bi bi-person-check"></i></span>
                                    <span class="flex-1 min-w-0"><span class="block text-sm font-bold text-foreground">{{ __('Kept — only unlinked') }}</span>
                                        <span class="block text-[11px] text-muted-foreground">{{ __('Their accounts stay; they simply leave this club') }}</span></span>
                                    <span class="px-2 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700" x-text="plan.keep.length"></span>
                                    <i class="bi bi-chevron-down text-muted-foreground transition-transform" :class="showKeep && 'rotate-180'"></i>
                                </button>
                                <div x-show="showKeep" x-transition class="border-t border-gray-100 max-h-60 overflow-y-auto">
                                    <template x-for="p in plan.keep" :key="p.id">
                                        <div class="flex items-center gap-2 px-4 py-2 text-sm border-b border-gray-50 last:border-0">
                                            <span class="flex-1 text-foreground" x-text="p.name"></span>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-muted text-muted-foreground" x-text="p.why"></span>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            {{-- Events --}}
                            <div class="rounded-2xl bg-white border border-gray-100 shadow-sm p-4 space-y-2" x-show="plan.events.length">
                                <p class="text-xs font-semibold text-muted-foreground uppercase tracking-wide">{{ __('Events') }}</p>
                                <template x-for="e in plan.events" :key="e.title">
                                    <div class="flex items-start gap-3 text-sm">
                                        <i class="bi bi-trophy text-amber-700 mt-0.5"></i>
                                        <div class="flex-1 min-w-0">
                                            <p class="font-semibold text-foreground" x-text="e.title"></p>
                                            <p class="text-[11px] text-muted-foreground">
                                                <span x-text="e.removed"></span> {{ __('removed — the draw re-cuts itself') }}<template x-if="e.unlinked"><span> · <span x-text="e.unlinked"></span> {{ __('stay, no longer under the club') }}</span></template>
                                            </p>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            {{-- Everything else --}}
                            <div class="rounded-2xl bg-white border border-gray-100 shadow-sm divide-y divide-gray-50 text-sm">
                                <div class="flex justify-between px-4 py-2.5"><span class="text-muted-foreground">{{ __('Federation memberships') }}</span><span class="font-semibold tabular-nums" x-text="plan.federation.memberships + plan.federation.applications"></span></div>
                                <div class="flex justify-between px-4 py-2.5"><span class="text-muted-foreground">{{ __('Federation event entries') }}</span><span class="font-semibold tabular-nums" x-text="plan.federation.entries"></span></div>
                                <div class="flex justify-between px-4 py-2.5"><span class="text-muted-foreground">{{ __('Officials it appointed to events') }}</span><span class="font-semibold tabular-nums" x-text="plan.officials"></span></div>
                                <div class="flex justify-between px-4 py-2.5"><span class="text-muted-foreground">{{ __('Listings on event club lists') }}</span><span class="font-semibold tabular-nums" x-text="plan.listings"></span></div>
                            </div>

                            <div class="rounded-2xl bg-amber-50 p-3.5 text-[12px] text-amber-700 flex gap-2.5">
                                <i class="bi bi-shield-check text-base"></i>
                                <p>{{ __('Before anything is removed, the database is backed up and the photos are archived. The club itself is archived and can be restored; the deleted athletes cannot be brought back from here.') }}</p>
                            </div>

                            <div x-show="!blocked">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    {{ __('Type the club’s name to confirm') }}: <span class="font-bold text-foreground" x-text="plan.club.name"></span>
                                </label>
                                <input type="text" x-model="typed" autocomplete="off" spellcheck="false" :disabled="state === 'removing'"
                                       class="w-full px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                                       :class="typed && !matches ? 'border-red-200' : ''">
                            </div>

                            <template x-if="error">
                                <p class="text-sm text-red-700 flex items-center gap-2"><i class="bi bi-exclamation-circle"></i><span x-text="error"></span></p>
                            </template>
                        </div>
                    </template>

                    <template x-if="state === 'done' && done">
                        <div class="text-center py-6 space-y-3">
                            <span class="mx-auto w-16 h-16 rounded-full bg-emerald-50 text-emerald-700 grid place-items-center"><i class="bi bi-check2-circle text-3xl"></i></span>
                            <p class="text-base font-bold text-foreground" x-text="done.message"></p>
                            <p class="text-xs text-muted-foreground">
                                <span x-text="done.removed.entries"></span> {{ __('entries') }} ·
                                <span x-text="done.removed.photos"></span> {{ __('photos') }} ·
                                <span x-text="done.removed.federation"></span> {{ __('federation records') }} ·
                                <span x-text="done.removed.kept"></span> {{ __('people kept') }}
                            </p>
                            <template x-if="done.removed.archive">
                                <p class="text-[11px] text-muted-foreground">{{ __('Photos archived as') }} <span class="font-mono" x-text="done.removed.archive"></span></p>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- Footer: the one action --}}
                <div class="flex-shrink-0 px-5 pt-3 border-t border-gray-100 bg-white" style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <template x-if="state !== 'done'">
                        <button type="button" @click="remove()" :disabled="!canRemove"
                                class="w-full h-12 rounded-xl font-bold text-white flex items-center justify-center gap-2 transition-opacity"
                                :class="canRemove ? 'bg-destructive' : 'bg-destructive opacity-40 cursor-not-allowed'">
                            <template x-if="state === 'removing'"><span class="flex items-center gap-2"><i class="bi bi-arrow-repeat animate-spin"></i>{{ __('Backing up, then removing…') }}</span></template>
                            <template x-if="state !== 'removing'"><span class="flex items-center gap-2"><i class="bi bi-trash3"></i>{{ __('Remove club') }}</span></template>
                        </button>
                    </template>
                    <template x-if="state === 'done'">
                        <button type="button" @click="close()" class="w-full h-12 rounded-xl font-bold bg-primary text-white">{{ __('Done') }}</button>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>
@endif
