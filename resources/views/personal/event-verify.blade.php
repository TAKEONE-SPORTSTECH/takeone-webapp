@extends('layouts.personal-mobile')

@section('title', __('personal.event_verify_title'))

{{--
    Officials' console — the two gates into the final draw.

    An entry reaches the mat only when a payments official has matched its proof
    to the club account AND a weigh-in official has put the athlete on the scale.
    Both are shown per row, because the question an official is answering is
    never "is this paid?" on its own — it is "is this entry ready?".

    Each action authorises against its own role server-side: a weigh-in official
    sees the payment column but cannot approve money.
--}}

@section('content')
<div
     x-data="{
        rows: @js($rows),
        filter: 'pending',
        busy: null,
        proof: null,
        scale: null,   {{-- id of the row whose weight field is open --}}
        draft: '',

        get shown() {
            if (this.filter === 'ready') return this.rows.filter(r => r.ready);
            if (this.filter === 'pending') return this.rows.filter(r => ! r.ready);
            return this.rows;
        },
        get readyCount() { return this.rows.filter(r => r.ready).length; },

        open(row) {
            this.scale = this.scale === row.id ? null : row.id;
            this.draft = row.weight ?? '';
            if (this.scale) this.$nextTick(() => document.getElementById('w-' + row.id)?.focus());
        },

        async weigh(row) {
            const weight = parseFloat(this.draft);
            if (! (weight > 0)) { window.showToast('error', @js(__('personal.event_verify_bad_weight'))); return; }

            this.busy = row.id;
            try {
                const res = await fetch(`{{ url('me/events/'.$e['key'].'/verify') }}/${row.id}/weigh-in`, {
                    method: 'PUT',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json',
                               'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ weight }),
                });
                const d = await res.json().catch(() => ({}));
                if (! res.ok || ! d.success) throw new Error(d.message || 'Failed');
                row.weight = d.weight; row.weighed = true; row.weigh_verified = true;
                row.ready = row.pay_verified && row.weigh_verified;
                this.scale = null;
                window.showToast('success', d.message);
            } catch (e) { window.showToast('error', e.message); }
            finally { this.busy = null; }
        },

        async pay(row, approve) {
            this.busy = row.id;
            try {
                const res = await fetch(`{{ url('me/events/'.$e['key'].'/verify') }}/${row.id}/payment`, {
                    method: 'PUT',
                    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json',
                               'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ approve }),
                });
                const d = await res.json().catch(() => ({}));
                if (! res.ok || ! d.success) throw new Error(d.message || 'Failed');
                row.paid = approve; row.pay_verified = approve;
                row.ready = row.pay_verified && row.weigh_verified;
                this.proof = null;
                window.showToast('success', d.message);
            } catch (e) { window.showToast('error', e.message); }
            finally { this.busy = null; }
        },
     }">

    {{-- Top bar --}}
    <header class="sticky top-0 z-30 h-14 flex items-center gap-3 px-3 border-b border-gray-200 bg-white/95 backdrop-blur">
        <a href="{{ route('me.events.show', $e['key']) }}" data-shell-link data-route="me.events"
           class="w-9 h-9 shrink-0 rounded-xl border border-gray-200 grid place-items-center text-muted-foreground hover:bg-muted/60 transition-colors">
            <i class="bi bi-arrow-left rtl:rotate-180"></i>
        </a>
        <div class="min-w-0">
            <p class="text-[10px] font-extrabold uppercase tracking-[0.14em] text-muted-foreground leading-none">{{ __('personal.event_verify_title') }}</p>
            <h1 class="text-sm font-bold text-foreground truncate leading-tight mt-0.5">{{ $e['title'] }}</h1>
        </div>
    </header>

    <div class="px-4 mt-4">
        {{-- What this screen is for, said once. --}}
        <div class="rounded-2xl p-4 text-white relative overflow-hidden"
             style="background: linear-gradient(135deg, {{ $e['color'] }}, #1f2937);">
            <div class="absolute -right-6 -top-6 w-28 h-28 rounded-full bg-white/10"></div>
            <div class="relative">
                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-white/70">{{ __('personal.event_verify_final_draw') }}</p>
                <p class="text-2xl font-black leading-none mt-1">
                    <span x-text="readyCount"></span><span class="text-white/60"> / <span x-text="rows.length"></span></span>
                </p>
                <p class="text-xs text-white/85 mt-1">{{ __('personal.event_verify_ready_hint') }}</p>
            </div>
        </div>

        {{-- Filter --}}
        <div class="flex gap-2 mt-4">
            @foreach(['pending' => __('personal.event_verify_pending'), 'ready' => __('personal.event_verify_ready'), 'all' => __('personal.event_verify_all')] as $key => $label)
                <button type="button" @click="filter = '{{ $key }}'"
                        :class="filter === '{{ $key }}' ? 'text-white border-transparent' : 'bg-white text-muted-foreground border-gray-200'"
                        :style="filter === '{{ $key }}' ? 'background: {{ $e['color'] }}' : ''"
                        class="flex-1 py-2 rounded-xl border-2 text-xs font-black transition-colors">{{ $label }}</button>
            @endforeach
        </div>

        {{-- The queue --}}
        <div class="mt-4 space-y-2.5 pb-8">
            <template x-for="row in shown" :key="row.id">
                <div class="m-card rounded-2xl p-4" :class="row.ready ? 'border-green-200' : ''">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[15px] font-black text-foreground leading-tight" x-text="row.name"></p>
                            <p class="text-[11px] text-muted-foreground mt-0.5" x-text="row.division"></p>
                        </div>
                        <span class="shrink-0 px-2 py-0.5 rounded-full text-[10px] font-black"
                              :class="row.ready ? 'bg-green-50 text-green-600' : 'bg-amber-50 text-amber-600'"
                              x-text="row.ready ? '{{ __('personal.event_verify_in_draw') }}' : '{{ __('personal.event_verify_held') }}'"></span>
                    </div>

                    {{-- Gate 1 · weigh-in --}}
                    <div class="mt-3 flex items-center gap-2.5">
                        <i class="bi text-base shrink-0"
                           :class="row.weigh_verified ? 'bi-check-circle-fill text-green-600' : 'bi-circle text-muted-foreground'"></i>
                        <div class="min-w-0 flex-1">
                            <p class="text-[13px] font-bold text-foreground">{{ __('personal.event_verify_weigh_in') }}</p>
                            <p class="text-[11px] text-muted-foreground">
                                <template x-if="row.weight"><span x-text="row.weight + ' kg'"></span></template>
                                <template x-if="! row.weight"><span>{{ __('personal.event_verify_no_weight') }}</span></template>
                                <template x-if="row.weight && ! row.weigh_verified"><span> · {{ __('personal.event_verify_self_declared') }}</span></template>
                            </p>
                        </div>
                        @if($canWeigh)
                            <button type="button" @click="open(row)"
                                    class="m-press shrink-0 px-3 py-1.5 rounded-xl text-white text-[11px] font-black"
                                    style="background: {{ $e['color'] }};"
                                    x-text="scale === row.id ? '{{ __('personal.event_verify_cancel') }}'
                                            : (row.weigh_verified ? '{{ __('personal.event_verify_reweigh') }}' : '{{ __('personal.event_verify_record') }}')"></button>
                        @endif
                    </div>

                    @if($canWeigh)
                        {{-- The scale itself. Inline, because the official is holding a
                             phone next to the actual scale and reading a number off it. --}}
                        <div x-show="scale === row.id" x-cloak class="mt-2.5 ms-[26px] flex items-center gap-2">
                            <div class="relative flex-1">
                                <input :id="'w-' + row.id" type="number" inputmode="decimal" step="0.1" min="10" max="250"
                                       x-model="draft" @keydown.enter.prevent="weigh(row)"
                                       class="w-full h-10 ps-3 pe-10 rounded-xl border-2 border-gray-200 text-sm font-bold text-foreground
                                              focus:outline-none focus:border-current"
                                       :style="'caret-color: {{ $e['color'] }}'"
                                       placeholder="{{ __('personal.event_verify_enter_weight') }}">
                                <span class="absolute inset-y-0 end-3 flex items-center text-[11px] font-black text-muted-foreground">kg</span>
                            </div>
                            <button type="button" @click="weigh(row)" :disabled="busy === row.id"
                                    class="m-press h-10 px-4 rounded-xl text-white text-xs font-black disabled:opacity-60"
                                    style="background: {{ $e['color'] }};">{{ __('personal.event_verify_sign') }}</button>
                        </div>
                    @endif

                    {{-- Gate 2 · payment --}}
                    <div class="mt-2.5 flex items-center gap-2.5">
                        <i class="bi text-base shrink-0"
                           :class="row.pay_verified ? 'bi-check-circle-fill text-green-600' : 'bi-circle text-muted-foreground'"></i>
                        <div class="min-w-0 flex-1">
                            <p class="text-[13px] font-bold text-foreground">{{ __('personal.event_verify_payment') }}</p>
                            <p class="text-[11px] text-muted-foreground">
                                <template x-if="row.pay_verified"><span>{{ __('personal.event_verify_approved') }}</span></template>
                                <template x-if="! row.pay_verified && row.has_proof"><span>{{ __('personal.event_verify_proof_waiting') }}</span></template>
                                <template x-if="! row.pay_verified && ! row.has_proof"><span>{{ __('personal.event_verify_no_proof') }}</span></template>
                            </p>
                        </div>
                        @if($canPay)
                            <div class="flex items-center gap-1.5 shrink-0">
                                <template x-if="row.proof_url">
                                    <button type="button" @click="proof = row"
                                            class="m-press w-8 h-8 rounded-xl border border-gray-200 grid place-items-center text-muted-foreground"
                                            title="{{ __('personal.event_verify_view_proof') }}">
                                        <i class="bi bi-receipt"></i>
                                    </button>
                                </template>
                                <button type="button" @click="pay(row, ! row.pay_verified)" :disabled="busy === row.id"
                                        class="m-press px-3 py-1.5 rounded-xl text-[11px] font-black disabled:opacity-60"
                                        :class="row.pay_verified ? 'border border-gray-200 text-red-600' : 'text-white'"
                                        :style="row.pay_verified ? '' : 'background: {{ $e['color'] }}'"
                                        x-text="row.pay_verified ? '{{ __('personal.event_verify_revoke') }}' : '{{ __('personal.event_verify_approve') }}'"></button>
                            </div>
                        @endif
                    </div>
                </div>
            </template>

            <p x-show="! shown.length" x-cloak class="text-[12px] text-muted-foreground text-center py-8">
                {{ __('personal.event_verify_nothing_here') }}
            </p>
        </div>
    </div>

    {{-- Proof viewer: the payments official must SEE the receipt before approving,
         and approve from the same place so they never lose their spot. --}}
    <template x-teleport="body" data-teleport-template="true">
        <div x-show="proof" x-cloak class="fixed inset-0 z-[70]" @keydown.escape.window="proof = null" style="display:none;">
            <div class="absolute inset-0 bg-black/70" @click="proof = null"></div>
            <div class="absolute inset-x-0 bottom-0 max-h-[92vh] flex flex-col bg-white rounded-t-3xl">
                <div class="flex-shrink-0 px-5 pt-3 pb-4 border-b border-gray-100">
                    <div class="w-10 h-1.5 bg-gray-200 rounded-full mx-auto mb-3"></div>
                    <h3 class="text-lg font-bold text-gray-900" x-text="proof?.name"></h3>
                    <p class="text-sm text-muted-foreground">{{ __('personal.event_verify_check_against') }} {{ $payment['bank']['iban'] ?? ($payment['club'] ?? '') }}</p>
                </div>
                <div class="flex-1 overflow-y-auto p-4 bg-muted/30 grid place-items-center">
                    <template x-if="proof"><img :src="proof.proof_url" alt="" class="max-w-full rounded-xl shadow"></template>
                </div>
                @if($canPay)
                    <div class="flex-shrink-0 px-5 pt-3 border-t border-gray-100 flex gap-3"
                         style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                        <button type="button" @click="pay(proof, false)"
                                class="flex-1 py-3 rounded-xl border border-gray-200 text-red-600 font-bold text-sm">
                            {{ __('personal.event_verify_reject') }}
                        </button>
                        <button type="button" @click="pay(proof, true)"
                                class="flex-1 py-3 rounded-xl text-white font-bold text-sm" style="background: {{ $e['color'] }};">
                            {{ __('personal.event_verify_approve') }}
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </template>
</div>
@endsection
