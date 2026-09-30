@props([
    'event',                  // event uuid
    'color' => '#7c3aed',
    'title' => '',
])

{{--
    Paid featuring — one row of the event console, opening a sheet.

    Asks the platform's flat fee, takes a picture or PDF of the receipt, and
    shows where the request stands (waiting · featured · not approved). The
    rules are App\Events\Support\EventFeaturing; this draws them and decides
    nothing — the endpoint re-checks who may ask, whether a fee is set and
    whether the event has ended.

    Standalone per the component contract: its own Alpine state, requests and
    sheet. It reads its state on first paint (me.events.feature), so it can be
    dropped into either console with no controller glue. Every server value is
    set with x-text, never innerHTML. Dispatches `event-feature-changed`
    {event, feature} after a send.
--}}

@php
    $c = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $color) ? $color : '#7c3aed';
    $rows = [
        'none' => __('events::events.feature_row_none'),
        'pending' => __('events::events.feature_row_pending'),
        'approved' => __('events::events.feature_row_approved'),
        'rejected' => __('events::events.feature_row_rejected'),
        'revoked' => __('events::events.feature_row_revoked'),
        'closed' => __('events::events.feature_row_closed'),
        'ended' => __('events::events.feature_row_ended'),
    ];
@endphp

<div x-data="{
        open: false,
        loaded: false,
        sending: false,
        f: { status: 'none', featured: false, fee: null, can_request: false, ended: false, reason: null, paid: null },
        file: null,
        fileName: '',
        rows: @js($rows),

        get rowKey() {
            if (this.f.featured) return 'approved';
            if (this.f.status === 'pending') return 'pending';
            if (this.f.ended) return 'ended';
            if (['rejected', 'revoked'].includes(this.f.status)) return this.f.status;
            return this.f.fee ? 'none' : 'closed';
        },

        async load() {
            try {
                const res = await fetch(@js(route('me.events.feature', $event)), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                const d = await res.json().catch(() => ({}));
                if (res.ok && d.feature) this.f = d.feature;
            } catch (e) { /* the row stays on its neutral state */ }
            this.loaded = true;
        },

        pick(ev) {
            const input = ev.target;
            this.file = input.files && input.files[0] ? input.files[0] : null;
            this.fileName = this.file ? this.file.name : '';
        },

        async send() {
            if (this.sending || ! this.file) return;
            this.sending = true;
            const body = new FormData();
            body.append('proof', this.file);
            try {
                const res = await fetch(@js(route('me.events.feature.store', $event)), {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                    credentials: 'same-origin',
                    body,
                });
                const d = await res.json().catch(() => ({}));
                if (d.feature) this.f = d.feature;
                if (! res.ok || ! d.success) throw new Error(d.message || (d.errors ? Object.values(d.errors)[0][0] : @js(__('personal.event_show_action_failed'))));
                this.file = null; this.fileName = '';
                if (this.$refs.proof) this.$refs.proof.value = '';
                window.showToast('success', d.message);
                window.dispatchEvent(new CustomEvent('event-feature-changed', { detail: { event: @js($event), feature: this.f } }));
            } catch (e) {
                window.showToast('error', e.message);
            } finally { this.sending = false; }
        },
     }" x-init="load()">

    {{-- the row in the console --}}
    <button type="button" @click="open = true"
            class="m-card m-press w-full text-start bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3">
        <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 transition-colors"
              :class="f.featured ? 'bg-amber-400 text-white' : (f.status === 'pending' ? 'bg-amber-50 text-amber-600' : 'bg-muted text-muted-foreground')">
            <i class="bi bi-stars text-lg"></i>
        </span>
        <span class="min-w-0 flex-1">
            <span class="block text-sm font-bold text-foreground">{{ __('events::events.feature_title') }}</span>
            <span class="block text-[11px] mt-0.5 truncate"
                  :class="f.featured ? 'text-amber-600 font-semibold' : (f.status === 'pending' ? 'text-amber-600' : 'text-muted-foreground')"
                  x-text="loaded ? rows[rowKey] : ''">&nbsp;</span>
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
                 class="relative w-full sm:max-w-lg max-h-[92vh] flex flex-col bg-white rounded-t-3xl sm:rounded-3xl shadow-2xl overflow-hidden">

                {{-- gradient header band (Design Rule #8) --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $c }}, {{ $c }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3 sm:hidden"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0">
                            <i class="bi bi-stars text-xl"></i>
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('events::events.feature_title') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5 truncate">{{ $title }}</p>
                        </div>
                        <button type="button" @click="open = false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <p class="relative mt-3 text-[11.5px] text-white/85 leading-snug flex items-start gap-1.5">
                        <i class="bi bi-info-circle mt-0.5 flex-shrink-0"></i>{{ __('events::events.feature_sheet_sub') }}
                    </p>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-3"
                     :style="f.can_request ? '' : 'padding-bottom: calc(1rem + env(safe-area-inset-bottom));'">

                    {{-- Where it stands --}}
                    <template x-if="f.featured">
                        <div class="rounded-2xl p-4 bg-amber-50 border border-amber-200 flex items-start gap-3 m-in">
                            <span class="w-10 h-10 rounded-xl bg-amber-400 text-white grid place-items-center flex-shrink-0 m-float"><i class="bi bi-stars text-lg"></i></span>
                            <div class="min-w-0">
                                <p class="text-sm font-black text-amber-800">{{ __('events::events.feature_status_approved') }}</p>
                                <p class="text-[12px] text-amber-700 mt-0.5 leading-snug">{{ __('events::events.feature_status_approved_hint') }}</p>
                                <p class="text-[11px] text-amber-700/80 mt-1.5" x-show="f.paid">{{ __('events::events.feature_paid') }}: <span class="font-bold" x-text="f.paid"></span></p>
                            </div>
                        </div>
                    </template>
                    <template x-if="f.status === 'pending'">
                        <div class="rounded-2xl p-4 bg-amber-50 border border-amber-200 flex items-start gap-3 m-in">
                            <span class="w-10 h-10 rounded-xl bg-white text-amber-600 grid place-items-center flex-shrink-0"><i class="bi bi-hourglass-split text-lg"></i></span>
                            <div class="min-w-0">
                                <p class="text-sm font-black text-amber-800">{{ __('events::events.feature_status_pending') }}</p>
                                <p class="text-[12px] text-amber-700 mt-0.5 leading-snug">{{ __('events::events.feature_status_pending_hint') }}</p>
                                <p class="text-[11px] text-amber-700/80 mt-1.5" x-show="f.paid">{{ __('events::events.feature_paid') }}: <span class="font-bold" x-text="f.paid"></span></p>
                            </div>
                        </div>
                    </template>
                    <template x-if="f.status === 'rejected' || f.status === 'revoked'">
                        <div class="rounded-2xl p-4 bg-rose-50 border border-rose-200 flex items-start gap-3 m-in">
                            <span class="w-10 h-10 rounded-xl bg-white text-rose-600 grid place-items-center flex-shrink-0"><i class="bi bi-x-octagon text-lg"></i></span>
                            <div class="min-w-0">
                                <p class="text-sm font-black text-rose-700"
                                   x-text="f.status === 'rejected' ? @js(__('events::events.feature_status_rejected')) : @js(__('events::events.feature_status_revoked'))"></p>
                                <p class="text-[12px] text-rose-600 mt-0.5 leading-snug" x-show="f.reason">
                                    {{ __('events::events.feature_reason') }}: <span x-text="f.reason"></span>
                                </p>
                                <p class="text-[12px] text-rose-600 mt-1 leading-snug" x-show="f.can_request">{{ __('events::events.feature_try_again') }}</p>
                            </div>
                        </div>
                    </template>
                    <template x-if="f.ended && ! f.featured && f.status !== 'pending'">
                        <div class="rounded-2xl p-4 bg-muted/60 text-sm text-muted-foreground flex items-center gap-2">
                            <i class="bi bi-flag-fill"></i>{{ __('events::events.feature_ended') }}
                        </div>
                    </template>
                    <template x-if="! f.fee && ! f.ended && ! f.featured && f.status !== 'pending'">
                        <div class="rounded-2xl p-4 bg-muted/60 text-sm text-muted-foreground flex items-center gap-2">
                            <i class="bi bi-lock"></i>{{ __('events::events.feature_closed') }}
                        </div>
                    </template>

                    {{-- The ask: the fee, and the receipt --}}
                    <template x-if="f.can_request">
                        <div class="space-y-3">
                            <p class="text-[12.5px] text-muted-foreground leading-relaxed">{{ __('events::events.feature_how') }}</p>

                            <div class="rounded-2xl p-4 text-white relative overflow-hidden"
                                 style="background: linear-gradient(135deg, #f59e0b, #d97706b0);">
                                <div class="absolute -right-6 -bottom-8 w-28 h-28 rounded-full bg-white/10"></div>
                                <p class="relative text-[10px] font-bold uppercase tracking-wider text-white/80">{{ __('events::events.feature_fee') }}</p>
                                <p class="relative text-2xl font-black mt-1 leading-none" x-text="f.fee ? f.fee.display : ''"></p>
                                <p class="relative text-[11.5px] text-white/85 mt-1.5">{{ __('events::events.feature_fee_note') }}</p>
                            </div>

                            <div>
                                <p class="text-sm font-bold text-foreground">{{ __('events::events.feature_proof') }}</p>
                                <p class="text-[11.5px] text-muted-foreground mt-0.5">{{ __('events::events.feature_proof_hint') }}</p>
                                <label class="m-press mt-2 flex items-center gap-3 rounded-2xl border-2 border-dashed p-3.5 cursor-pointer transition-colors"
                                       :class="file ? 'border-primary bg-primary/5' : 'border-gray-200 hover:border-primary/40'">
                                    <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0"
                                          :class="file ? 'bg-primary text-white' : 'bg-muted text-muted-foreground'">
                                        <i class="bi" :class="file ? 'bi-file-earmark-check' : 'bi-cloud-arrow-up'"></i>
                                    </span>
                                    <span class="min-w-0 flex-1 text-sm font-semibold truncate"
                                          :class="file ? 'text-foreground' : 'text-muted-foreground'"
                                          x-text="fileName || @js(__('events::events.feature_pick'))"></span>
                                    <span class="text-[11px] font-bold text-primary" x-show="file">{{ __('events::events.feature_change') }}</span>
                                    <input type="file" x-ref="proof" class="sr-only" accept="image/jpeg,image/png,image/webp,application/pdf" @change="pick($event)">
                                </label>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Footer only when there is something to send (Design Rule #8). --}}
                <div x-show="f.can_request" class="flex-shrink-0 px-5 pt-3 border-t border-gray-100 bg-white"
                     style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="send()" :disabled="! file || sending"
                            class="m-press w-full py-3 rounded-xl bg-primary text-white text-sm font-bold flex items-center justify-center gap-2 disabled:opacity-50 transition-opacity">
                        <i class="bi" :class="sending ? 'bi-arrow-repeat animate-spin' : 'bi-send-fill'"></i>
                        <span x-text="sending ? @js(__('events::events.feature_sending')) : @js(__('events::events.feature_submit'))"></span>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
