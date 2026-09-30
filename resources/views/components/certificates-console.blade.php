@props([
    'context' => 'club_event',   // a CertificateContext alias
    'subject',                   // the context's public key (an event's uuid)
    'color' => '#7c3aed',
    'title' => '',
])

{{--
    Certificates — one row of a console, opening a sheet (App\Certificates).

    What the organiser does here: choose what is issued (participation, podium)
    and when drafts are made, draft what has been earned, look at any draft,
    approve them (one, or all — sent in batches, because each approval draws
    and stores a PDF), revoke an issued one with a reason, and open the design
    room.

    Standalone per the component contract: its own Alpine state, requests,
    sheet and viewer. It reads its state on first paint and decides nothing —
    every endpoint re-authorises (the context's managers only) and every value
    from the server is set with x-text / :src, never innerHTML. Re-reads itself
    on `realtime:certificates` {action:'refresh'}, which is sent when another
    organiser changes something.

    Dispatches `certificates-changed` {context, key, counts} after every write.
--}}

@php
    $c = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $color) ? $color : '#7c3aed';
    $alias = preg_match('/^[a-z_]{2,40}$/', (string) $context) ? $context : 'club_event';
    $p = ['context' => $alias, 'key' => $subject];
    $urls = [
        'state' => route('me.certificates.manage', $p),
        'settings' => route('me.certificates.settings', $p),
        'generate' => route('me.certificates.generate', $p),
        'approve' => route('me.certificates.approve', $p),
        'revoke' => route('me.certificates.revoke', ['cert' => '00000000-0000-0000-0000-000000000000']),
        'destroy' => route('me.certificates.destroy', ['cert' => '00000000-0000-0000-0000-000000000000']),
    ];
    $viewer = 'open-certificate-view';
@endphp

@once
    <x-media-lightbox :event-name="$viewer" />
@endonce

<div x-data="{
        open: false,
        loaded: false,
        busy: false,
        tab: 'draft',
        progress: null,
        revoking: null,
        reason: '',
        s: { settings: { participation: true, podium: true, trigger: 'division' }, eligible: { participation: 0, podium: 0, decided: 0, pending: 0 }, counts: { draft: 0, approved: 0, revoked: 0 }, certificates: [], design_url: null, batch: 15 },
        urls: @js($urls),

        get rows() { return this.s.certificates.filter(r => r.status === this.tab); },
        get summary() {
            if (! this.loaded) return '';
            if (this.s.counts.draft) return @js(__('cert::messages.console_row_drafts')).replace(':count', this.s.counts.draft);
            if (this.s.counts.approved) return @js(__('cert::messages.console_row_issued')).replace(':count', this.s.counts.approved);
            return @js(__('cert::messages.console_row_none'));
        },
        certUrl(base, uuid) { return base.replace('00000000-0000-0000-0000-000000000000', uuid); },

        headers(json) {
            const h = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' };
            if (json) h['Content-Type'] = 'application/json';
            return h;
        },
        async call(method, url, body) {
            const res = await fetch(url, { method, headers: this.headers(body !== undefined), credentials: 'same-origin', body: body !== undefined ? JSON.stringify(body) : undefined });
            const d = await res.json().catch(() => ({}));
            if (! res.ok || d.success === false) throw new Error(d.message || (d.errors ? Object.values(d.errors)[0][0] : @js(__('cert::messages.failed'))));
            return d;
        },
        take(d) {
            ['settings', 'eligible', 'counts', 'certificates', 'design_url', 'batch'].forEach(k => { if (d[k] !== undefined) this.s[k] = d[k]; });
            this.loaded = true;
            window.dispatchEvent(new CustomEvent('certificates-changed', { detail: { context: @js($alias), key: @js($subject), counts: this.s.counts } }));
        },
        async load() {
            try { this.take(await this.call('GET', this.urls.state)); } catch (e) { this.loaded = true; }
        },
        async setting(patch) {
            const before = JSON.parse(JSON.stringify(this.s.settings));
            Object.assign(this.s.settings, patch);
            try { const d = await this.call('PUT', this.urls.settings, patch); this.take(d); }
            catch (e) { this.s.settings = before; window.showToast('error', e.message); }
        },
        async generate() {
            if (this.busy) return;
            this.busy = true;
            try { const d = await this.call('POST', this.urls.generate, {}); this.take(d); this.tab = 'draft'; window.showToast('success', d.message); }
            catch (e) { window.showToast('error', e.message); }
            finally { this.busy = false; }
        },
        async approve(uuids) {
            if (this.busy) return;
            this.busy = true;
            const total = uuids ? uuids.length : this.s.counts.draft;
            this.progress = { done: 0, total };
            let done = 0;
            try {
                // Each approval draws a PDF: send one batch at a time and show how far along it is.
                for (let guard = 0; guard < 400; guard++) {
                    const body = uuids ? { uuids: uuids.slice(done, done + this.s.batch) } : { all: true };
                    if (uuids && ! body.uuids.length) break;
                    const d = await this.call('POST', this.urls.approve, body);
                    this.take(d);
                    done += d.approved;
                    this.progress = { done: Math.min(done, total), total };
                    if (! d.approved || (! uuids && ! d.remaining)) break;
                }
                window.showToast('success', @js(__('cert::messages.approved_done')).replace(':count', done));
                if (! this.s.counts.draft) this.tab = 'approved';
            } catch (e) { window.showToast('error', e.message); }
            finally { this.busy = false; this.progress = null; }
        },
        async remove(row) {
            const ok = await window.confirmAction({ title: @js(__('cert::messages.delete_title')), message: @js(__('cert::messages.delete_message')), type: 'danger', confirmText: @js(__('cert::messages.delete')) });
            if (! ok) return;
            try {
                const d = await this.call('DELETE', this.certUrl(this.urls.destroy, row.uuid));
                this.s.certificates = this.s.certificates.filter(r => r.uuid !== row.uuid);
                this.s.counts.draft = Math.max(0, this.s.counts.draft - 1);
                window.showToast('success', d.message);
            } catch (e) { window.showToast('error', e.message); }
        },
        async revoke(row) {
            if (this.reason.trim().length < 3) { window.showToast('warning', @js(__('cert::messages.revoke_reason_needed'))); return; }
            try {
                const d = await this.call('POST', this.certUrl(this.urls.revoke, row.uuid), { reason: this.reason.trim() });
                const i = this.s.certificates.findIndex(r => r.uuid === row.uuid);
                if (i >= 0 && d.certificate) this.s.certificates.splice(i, 1, d.certificate);
                this.s.counts.approved = Math.max(0, this.s.counts.approved - 1);
                this.s.counts.revoked++;
                this.revoking = null; this.reason = '';
                window.showToast('success', d.message);
            } catch (e) { window.showToast('error', e.message); }
        },
        view(row) {
            window.dispatchEvent(new CustomEvent(@js($viewer), { detail: { src: row.image_url, label: row.number + ' · ' + (row.holder || ''), kind: 'image' } }));
        },
     }"
     x-init="load()"
     @realtime:certificates.window="if ($event.detail && $event.detail.action === 'refresh') load()">

    {{-- the row in the console --}}
    <button type="button" @click="open = true"
            class="m-card m-press w-full text-start bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3">
        <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 transition-colors"
              :class="s.counts.draft ? 'bg-amber-50 text-amber-600' : (s.counts.approved ? 'bg-emerald-50 text-emerald-600' : 'bg-accent text-primary')">
            <i class="bi bi-patch-check-fill text-lg"></i>
        </span>
        <span class="min-w-0 flex-1">
            <span class="block text-sm font-bold text-foreground">{{ __('cert::messages.console_title') }}</span>
            <span class="block text-[11px] mt-0.5 truncate" :class="s.counts.draft ? 'text-amber-600 font-semibold' : 'text-muted-foreground'" x-text="summary">&nbsp;</span>
        </span>
        <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground/50 text-xs flex-shrink-0"></i>
    </button>

    {{-- Teleported: the mobile shell leaves a transform on its children. --}}
    <template x-teleport="body" data-teleport-template="true">
        <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-end sm:items-center justify-center"
             @keydown.escape.window="open = false" style="display:none;">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/40" @click="open = false"></div>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full sm:opacity-0" x-transition:enter-end="translate-y-0 sm:opacity-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full sm:opacity-0"
                 class="relative w-full sm:max-w-lg max-h-[92vh] flex flex-col bg-background rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden">

                {{-- gradient header band (Design Rule #8) --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $c }}, {{ $c }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3 sm:hidden"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-patch-check-fill text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('cert::messages.console_title') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5 truncate">{{ $title }}</p>
                        </div>
                        <button type="button" @click="open = false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="relative mt-3 flex flex-wrap gap-1.5" x-show="loaded">
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/20 text-[11px] font-bold">
                            <i class="bi bi-person-check"></i><span x-text="@js(__('cert::messages.chip_competed')).replace(':count', s.eligible.participation)"></span>
                        </span>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/20 text-[11px] font-bold">
                            <i class="bi bi-award"></i><span x-text="@js(__('cert::messages.chip_medals')).replace(':count', s.eligible.podium)"></span>
                        </span>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-white/20 text-[11px] font-bold" x-show="s.eligible.pending">
                            <i class="bi bi-hourglass-split"></i><span x-text="@js(__('cert::messages.chip_running')).replace(':count', s.eligible.pending)"></span>
                        </span>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto px-4 py-4 space-y-5"
                     :style="s.counts.draft ? '' : 'padding-bottom: calc(1rem + env(safe-area-inset-bottom));'">

                    {{-- Where it stands --}}
                    <div class="grid grid-cols-3 gap-2">
                        @foreach(['draft' => ['text-primary', 'cert::messages.stat_drafts'], 'approved' => ['text-emerald-600', 'cert::messages.stat_issued'], 'revoked' => ['text-muted-foreground', 'cert::messages.stat_revoked']] as $k => [$ink, $label])
                            <button type="button" @click="tab = '{{ $k }}'"
                                    class="m-press bg-white rounded-2xl border px-3 py-2.5 text-start transition-colors"
                                    :class="tab === '{{ $k }}' ? 'border-primary shadow-sm' : 'border-gray-100'">
                                <span class="block text-xl font-black {{ $ink }}" x-text="s.counts.{{ $k }}">0</span>
                                <span class="block text-[11px] font-semibold text-muted-foreground">{{ __($label) }}</span>
                            </button>
                        @endforeach
                    </div>

                    {{-- What gets issued --}}
                    <section class="space-y-2">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground px-1">{{ __('cert::messages.what_issued') }}</p>
                        @foreach(['participation' => ['P', 'bg-accent text-primary', 'cert::messages.kind_participation_hint'], 'podium' => ['1', 'bg-amber-50 text-amber-700', 'cert::messages.kind_podium_hint']] as $kind => [$glyph, $tile, $hint])
                            <button type="button" @click="setting({ {{ $kind }}: ! s.settings.{{ $kind }} })" role="switch" :aria-checked="s.settings.{{ $kind }}"
                                    class="m-press w-full text-start bg-white rounded-2xl border-2 p-3 flex items-center gap-3 min-h-[60px] transition-colors"
                                    :class="s.settings.{{ $kind }} ? 'border-primary' : 'border-gray-100'">
                                <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0 font-black {{ $tile }}">{{ $glyph }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-bold text-foreground">{{ __('cert::messages.types.'.$kind) }}
                                        <span class="text-muted-foreground font-semibold" x-text="'· ' + s.eligible.{{ $kind }}"></span></span>
                                    <span class="block text-[12px] text-muted-foreground mt-0.5 leading-snug">{{ __($hint) }}</span>
                                </span>
                                <span class="relative w-11 h-6 rounded-full flex-shrink-0 transition-colors" :class="s.settings.{{ $kind }} ? 'bg-primary' : 'bg-gray-300'">
                                    <span class="absolute top-0.5 w-5 h-5 rounded-full bg-white shadow transition-all" :style="'inset-inline-start:' + (s.settings.{{ $kind }} ? '22px' : '2px')"></span>
                                </span>
                            </button>
                        @endforeach
                    </section>

                    {{-- When drafts are made --}}
                    <section class="space-y-2">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground px-1">{{ __('cert::messages.when_drafted') }}</p>
                        @foreach(['division', 'event', 'manual'] as $trigger)
                            <button type="button" @click="setting({ trigger: '{{ $trigger }}' })" role="radio" :aria-checked="s.settings.trigger === '{{ $trigger }}'"
                                    class="m-press w-full text-start rounded-2xl border-2 p-3 flex items-center gap-3 min-h-[60px] transition-colors"
                                    :class="s.settings.trigger === '{{ $trigger }}' ? 'border-primary bg-primary/5' : 'border-gray-100 bg-white'">
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-bold text-foreground">{{ __('cert::messages.trigger_'.$trigger) }}</span>
                                    <span class="block text-[12px] text-muted-foreground mt-0.5">{{ __('cert::messages.trigger_'.$trigger.'_hint') }}</span>
                                </span>
                                <span class="w-5 h-5 rounded-full border-2 grid place-items-center flex-shrink-0" :class="s.settings.trigger === '{{ $trigger }}' ? 'border-primary' : 'border-gray-300'">
                                    <span class="w-2.5 h-2.5 rounded-full" :class="s.settings.trigger === '{{ $trigger }}' ? 'bg-primary' : ''"></span>
                                </span>
                            </button>
                        @endforeach
                    </section>

                    {{-- Design and drafting --}}
                    <section class="bg-white rounded-2xl border border-gray-100 overflow-hidden divide-y divide-gray-100">
                        <a :href="s.design_url ? s.design_url + '?back=' + encodeURIComponent(window.location.pathname) : '#'" x-show="s.design_url"
                           class="flex items-center gap-3 p-3 min-h-[60px] hover:bg-muted/40 transition-colors">
                            <span class="w-9 h-9 rounded-xl bg-accent text-primary grid place-items-center flex-shrink-0"><i class="bi bi-palette-fill"></i></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold text-foreground">{{ __('cert::messages.design_row') }}</span>
                                <span class="block text-[12px] text-muted-foreground">{{ __('cert::messages.design_row_hint') }}</span>
                            </span>
                            <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground/50 text-xs"></i>
                        </a>
                        <button type="button" @click="generate()" :disabled="busy"
                                class="w-full text-start flex items-center gap-3 p-3 min-h-[60px] hover:bg-muted/40 transition-colors disabled:opacity-60">
                            <span class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 grid place-items-center flex-shrink-0"><i class="bi" :class="busy && ! progress ? 'bi-arrow-repeat animate-spin' : 'bi-magic'"></i></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-bold text-foreground">{{ __('cert::messages.generate') }}</span>
                                <span class="block text-[12px] text-muted-foreground">{{ __('cert::messages.generate_hint') }}</span>
                            </span>
                        </button>
                    </section>

                    {{-- The certificates themselves --}}
                    <section class="space-y-2">
                        <div class="flex items-center justify-between px-1">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground"
                               x-text="tab === 'draft' ? @js(__('cert::messages.list_drafts')) : (tab === 'approved' ? @js(__('cert::messages.list_issued')) : @js(__('cert::messages.list_revoked')))"></p>
                        </div>

                        <template x-if="loaded && ! rows.length">
                            <div class="rounded-2xl bg-white border border-dashed border-gray-200 p-6 text-center">
                                <i class="bi bi-patch-check text-3xl text-muted-foreground/40"></i>
                                <p class="text-sm text-muted-foreground mt-2"
                                   x-text="tab === 'draft' ? @js(__('cert::messages.empty_drafts')) : (tab === 'approved' ? @js(__('cert::messages.empty_issued')) : @js(__('cert::messages.empty_revoked')))"></p>
                            </div>
                        </template>

                        <template x-for="row in rows" :key="row.uuid">
                            <div class="bg-white rounded-2xl border border-gray-100 p-3 m-in">
                                <div class="flex items-center gap-3">
                                    <button type="button" @click="view(row)" :aria-label="@js(__('cert::messages.view'))"
                                            class="w-12 rounded-md border flex-shrink-0 grid place-items-center" style="height: 34px; background: #fbf8f2;"
                                            :style="'border-color:' + ({ 1: '#b8892b', 2: '#8a939c', 3: '#a0612e' }[row.place] || '#b4a8f5')">
                                        <i class="bi text-xs" :class="row.place ? 'bi-award-fill text-amber-600' : 'bi-patch-check text-primary'"></i>
                                    </button>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-bold text-foreground break-words" x-text="row.holder"></p>
                                        <p class="text-[12px] text-muted-foreground break-words" x-text="row.what"></p>
                                        <p class="text-[11px] text-muted-foreground/80 mt-0.5" x-text="row.type_label + ' · ' + row.number"></p>
                                    </div>
                                </div>
                                <p class="text-[12px] text-rose-600 mt-2" x-show="row.status === 'revoked' && row.revoke_reason" x-text="row.revoke_reason"></p>
                                <div class="flex flex-wrap gap-2 mt-2.5">
                                    <button type="button" @click="view(row)" class="m-press h-9 px-3 rounded-xl border border-gray-200 text-[12px] font-bold text-foreground inline-flex items-center gap-1.5"><i class="bi bi-eye"></i>{{ __('cert::messages.view') }}</button>
                                    <template x-if="row.status === 'draft'">
                                        <span class="contents">
                                            <button type="button" @click="approve([row.uuid])" :disabled="busy" class="m-press h-9 px-3 rounded-xl bg-primary text-white text-[12px] font-bold inline-flex items-center gap-1.5 disabled:opacity-50"><i class="bi bi-check2"></i>{{ __('cert::messages.approve') }}</button>
                                            <button type="button" @click="remove(row)" class="m-press h-9 px-3 rounded-xl border border-red-200 text-red-600 text-[12px] font-bold inline-flex items-center gap-1.5"><i class="bi bi-trash3"></i>{{ __('cert::messages.delete') }}</button>
                                        </span>
                                    </template>
                                    <template x-if="row.status !== 'draft'">
                                        <a :href="row.print_url" target="_blank" rel="noopener" class="m-press h-9 px-3 rounded-xl border border-gray-200 text-[12px] font-bold text-foreground inline-flex items-center gap-1.5"><i class="bi bi-printer"></i>{{ __('cert::messages.print') }}</a>
                                    </template>
                                    <template x-if="row.status === 'approved'">
                                        <button type="button" @click="revoking = revoking === row.uuid ? null : row.uuid; reason = ''" class="m-press h-9 px-3 rounded-xl border border-red-200 text-red-600 text-[12px] font-bold inline-flex items-center gap-1.5"><i class="bi bi-x-octagon"></i>{{ __('cert::messages.revoke') }}</button>
                                    </template>
                                </div>
                                <div x-show="revoking === row.uuid" x-transition class="mt-3 rounded-xl bg-rose-50 border border-red-100 p-3 space-y-2">
                                    <label :for="'rv-' + row.uuid" class="block text-[12px] font-bold text-rose-700">{{ __('cert::messages.revoke_reason') }}</label>
                                    <textarea :id="'rv-' + row.uuid" x-model="reason" rows="2" maxlength="300"
                                              class="w-full px-3 py-2 border border-rose-200 rounded-lg text-sm focus:ring-2 focus:ring-red-400 focus:border-transparent"
                                              placeholder="{{ __('cert::messages.revoke_placeholder') }}"></textarea>
                                    <button type="button" @click="revoke(row)" class="m-press w-full h-10 rounded-xl bg-red-600 text-white text-sm font-bold">{{ __('cert::messages.revoke_confirm') }}</button>
                                </div>
                            </div>
                        </template>
                    </section>

                    <p class="text-[12px] text-muted-foreground leading-relaxed px-1">{{ __('cert::messages.console_note') }}</p>
                </div>

                {{-- Footer only while there is something to approve (Design Rule #8). --}}
                <div x-show="s.counts.draft" class="flex-shrink-0 px-4 pt-3 border-t border-gray-100 bg-white"
                     style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <div x-show="progress" class="h-1.5 rounded-full bg-muted overflow-hidden mb-2">
                        <div class="h-full bg-primary transition-all duration-300" :style="progress ? 'width:' + Math.round(progress.done / Math.max(1, progress.total) * 100) + '%' : ''"></div>
                    </div>
                    <button type="button" @click="approve(null)" :disabled="busy"
                            class="m-press w-full py-3 rounded-xl bg-primary text-white text-sm font-bold flex items-center justify-center gap-2 disabled:opacity-60 transition-opacity">
                        <i class="bi" :class="progress ? 'bi-arrow-repeat animate-spin' : 'bi-check2-all'"></i>
                        <span x-text="progress
                            ? @js(__('cert::messages.approving')).replace(':done', progress.done).replace(':total', progress.total)
                            : @js(__('cert::messages.approve_all')).replace(':count', s.counts.draft)"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
