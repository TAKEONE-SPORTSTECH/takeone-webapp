@props([
    'event',                 // event uuid
    'waiting' => 0,          // proofs waiting for review, for the row's line
    'color' => '#7c3aed',
    'title' => '',
])

{{--
    Club payments — one row of the event console opening a sheet (owner,
    2026-10-04). A club entering several athletes pays ONCE and sends one proof
    (App\Events\Models\EventEntryInvoice); here the organiser looks at it and
    approves it — which marks every athlete it covers paid — or rejects it with
    a reason the club will read.

    Standalone: its own Alpine state, its own requests (me.events.club-payments,
    …proof, …decide), its own sheet, in-place updates. Reads the list lazily on
    open. Every value is drawn with x-text/:src — never innerHTML.
--}}

@php
    $c = preg_match('/^#[0-9a-fA-F]{6}$/', (string) $color) ? $color : '#7c3aed';
@endphp

@once
    <x-media-lightbox />
@endonce

<div x-data="{
        open: false, loading: false, busy: null, rejecting: null, reason: '',
        waiting: {{ (int) $waiting }}, items: [],
        words: @js([
            'submitted' => __('events::events.club_payments_state_submitted'),
            'approved' => __('events::events.club_payments_state_approved'),
            'rejected' => __('events::events.club_payments_state_rejected'),
            'athletes' => __('events::events.club_payments_athletes'),
            'none' => __('events::events.club_payments_sub_none'),
            'waiting' => __('events::events.club_payments_sub_waiting'),
        ]),
        async load() {
            this.loading = true;
            try {
                const r = await fetch(@js(route('me.events.club-payments', $event)), { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });
                const d = await r.json();
                if (r.ok && d.success) this.take(d.payments);
            } catch (e) {} finally { this.loading = false; }
        },
        take(list) { this.items = list || []; this.waiting = this.items.filter(i => i.state === 'submitted').length; },
        show() { this.open = true; this.load(); },
        sub() { return this.waiting ? this.words.waiting.replace(':n', this.waiting) : this.words.none; },
        view(p) { window.dispatchEvent(new CustomEvent('open-media-lightbox', { detail: { src: p.proof, label: p.club, kind: 'image' } })); },
        async decide(p, approve) {
            if (this.busy) return;
            this.busy = p.uuid;
            try {
                const r = await fetch(@js(route('me.events.club-payments.decide', [$event, '__I__'])).replace('__I__', p.uuid), {
                    method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                    body: JSON.stringify({ approve, reason: approve ? null : this.reason }),
                });
                const d = await r.json().catch(() => ({}));
                if (! r.ok || ! d.success) throw new Error(d.message || @js(__('personal.event_show_action_failed')));
                this.take(d.payments); this.rejecting = null; this.reason = '';
                window.showToast('success', d.message);
                window.dispatchEvent(new CustomEvent('event-club-payment-decided', { detail: { event: @js($event), invoice: p.uuid, approve } }));
            } catch (e) { window.showToast('error', e.message); } finally { this.busy = null; }
        },
     }">

    <button type="button" @click="show()"
            class="m-card m-press w-full text-start bg-white rounded-2xl border border-gray-100 shadow-sm p-3.5 flex items-center gap-3">
        <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0"
              :class="waiting ? 'text-white' : 'bg-muted text-muted-foreground'" :style="waiting ? 'background: {{ $c }}' : ''">
            <i class="bi bi-receipt text-lg"></i>
        </span>
        <span class="min-w-0 flex-1">
            <span class="block text-sm font-bold text-foreground">{{ __('events::events.club_payments') }}</span>
            <span class="block text-[11px] mt-0.5" :class="waiting ? 'text-amber-600 font-semibold' : 'text-muted-foreground'" x-text="sub()"></span>
        </span>
        <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground/50 text-xs flex-shrink-0"></i>
    </button>

    <template x-teleport="body" data-teleport-template="true">
        <div x-show="open" x-cloak class="fixed inset-0 z-[70] flex items-end justify-center" @keydown.escape.window="open = false" style="display:none;">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/40" @click="open = false"></div>
            <div x-show="open"
                 x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="relative w-full max-w-lg max-h-[92vh] flex flex-col bg-white rounded-t-3xl shadow-2xl">

                {{-- gradient header band (Design Rule #8) --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-4 rounded-t-3xl text-white relative overflow-hidden"
                     style="background: linear-gradient(150deg, {{ $c }}, {{ $c }}b0);">
                    <div class="absolute -right-8 -top-10 w-36 h-36 rounded-full bg-white/10"></div>
                    <div class="mx-auto w-10 h-1 rounded-full bg-white/40 mb-3"></div>
                    <div class="relative flex items-start gap-3">
                        <span class="w-12 h-12 rounded-2xl bg-white/20 grid place-items-center flex-shrink-0"><i class="bi bi-receipt text-xl"></i></span>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-black leading-tight">{{ __('events::events.club_payments') }}</h3>
                            <p class="text-[12px] text-white/85 mt-0.5">{{ $title }}</p>
                        </div>
                        <button type="button" @click="open = false" aria-label="{{ __('shared.close') }}"
                                class="w-9 h-9 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center flex-shrink-0 active:scale-90 transition-transform">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <p class="relative mt-3 text-[11.5px] text-white/85 leading-snug flex items-start gap-1.5">
                        <i class="bi bi-info-circle mt-0.5 flex-shrink-0"></i>{{ __('events::events.club_payments_hint') }}
                    </p>
                </div>

                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-2.5" style="padding-bottom: calc(1rem + env(safe-area-inset-bottom));">
                    <template x-if="loading && ! items.length">
                        <p class="text-center text-[12px] text-muted-foreground py-6"><i class="bi bi-hourglass-split"></i></p>
                    </template>
                    <template x-if="! loading && ! items.length">
                        <p class="text-center text-[12.5px] text-muted-foreground py-6" x-text="words.none"></p>
                    </template>

                    <template x-for="p in items" :key="p.uuid">
                        <div class="rounded-2xl border border-gray-100 p-3.5">
                            <div class="flex items-start gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-black text-foreground" x-text="p.club"></p>
                                    <p class="text-[11.5px] text-muted-foreground mt-0.5">
                                        <span x-text="(p.currency || '') + ' ' + Number(p.amount).toFixed(3)"></span> ·
                                        <span x-text="words.athletes.replace(':n', p.athletes)"></span>
                                    </p>
                                </div>
                                <span class="flex-none px-2 py-0.5 rounded-full text-[10px] font-black"
                                      :class="p.state === 'approved' ? 'bg-green-50 text-green-700' : (p.state === 'rejected' ? 'bg-rose-50 text-rose-700' : 'bg-amber-50 text-amber-700')"
                                      x-text="words[p.state]"></span>
                            </div>
                            <p class="text-[12px] text-rose-700 mt-2" x-show="p.reason" x-text="p.reason"></p>

                            <div class="mt-3 flex items-center gap-2 flex-wrap">
                                <button type="button" x-show="p.proof" @click="view(p)"
                                        class="m-press h-9 px-3 rounded-xl text-[12px] font-bold border border-gray-200 text-foreground flex items-center gap-1.5">
                                    <i class="bi bi-image"></i>{{ __('events::events.club_payments_view') }}
                                </button>
                                <template x-if="p.state === 'submitted'">
                                    <div class="flex items-center gap-2 ms-auto">
                                        <button type="button" @click="rejecting = rejecting === p.uuid ? null : p.uuid" :disabled="busy"
                                                class="m-press h-9 px-3 rounded-xl text-[12px] font-bold border border-rose-200 text-rose-700">{{ __('events::events.club_payments_reject') }}</button>
                                        <button type="button" @click="decide(p, true)" :disabled="busy"
                                                class="m-press h-9 px-3.5 rounded-xl text-[12px] font-black text-white" style="background: {{ $c }};">{{ __('events::events.club_payments_approve') }}</button>
                                    </div>
                                </template>
                            </div>

                            <div x-show="rejecting === p.uuid" x-cloak class="mt-3">
                                <label class="block text-[12px] font-bold text-foreground mb-1">{{ __('events::events.club_payments_reason') }}</label>
                                <textarea x-model="reason" rows="2" maxlength="500"
                                          class="w-full px-3 py-2 border border-gray-200 rounded-xl text-sm focus:ring-2 focus:ring-purple-500 focus:border-transparent"></textarea>
                                <button type="button" @click="decide(p, false)" :disabled="busy"
                                        class="m-press mt-2 w-full h-10 rounded-xl text-[13px] font-black text-white bg-rose-600">{{ __('events::events.club_payments_reject') }}</button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>
</div>
