{{-- Inside the personal mobile shell: header (avatar → drawer), notifications,
     chat and bottom tabs come from the shell. -mx-4 -mt-4 cancels <main>'s padding. --}}
@extends('layouts.personal-mobile')

@section('title', __('nav.payments'))

@section('personal-content')
<div class="-mx-4 -mt-4"
     x-data="paymentsPage({ settleBase: '{{ url('me/payments') }}', csrf: '{{ csrf_token() }}' })"
     @realtime:payments.window="onRealtime($event.detail)">

    {{-- ===== Summary hero ===== --}}
    <header class="m-hero px-5 pt-7 pb-6 text-white relative overflow-hidden">
        <div class="absolute -end-8 -top-8 w-36 h-36 rounded-full bg-white/10"></div>
        <div class="flex items-center justify-between relative z-10">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70">{{ __('personal.membership') }}</p>
                <h1 class="text-2xl font-black mt-0.5">{{ __('nav.payments') }}</h1>
            </div>
            <div class="w-12 h-12 rounded-2xl bg-white/15 border border-white/25 backdrop-blur grid place-items-center">
                <i class="bi bi-wallet2 text-xl m-float"></i>
            </div>
        </div>

        <div class="flex gap-2 mt-5 relative z-10">
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none">{{ number_format($totalPaid, 2) }}</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('personal.paid') }}</p>
            </div>
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none">{{ number_format($totalDue, 2) }}</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('personal.due') }}</p>
            </div>
            <div class="flex-1 rounded-2xl bg-white/12 border border-white/20 backdrop-blur px-3 py-2.5">
                <p class="text-lg font-black leading-none">{{ $subscriptions->count() }}</p>
                <p class="text-[10px] text-white/75 mt-1 uppercase tracking-wide">{{ __('personal.aff_total') }}</p>
            </div>
        </div>
    </header>

    <div class="px-4 pt-5 relative z-10 space-y-5 mobile-stagger">

        {{-- ===== History ===== --}}
        <div>
            <p class="px-1 mb-2 text-[11px] font-bold uppercase tracking-wider text-muted-foreground/80">{{ __('personal.history') }}</p>
            @forelse($subscriptions as $sub)
                @php
                    $cur    = $sub->tenant->currency ?? '';
                    $status = $sub->payment_status ?? '';
                    $paid   = $status === 'paid';
                    $amount = $paid ? $sub->amount_paid : $sub->amount_due;
                    $settleable = $status === 'unpaid';
                    $pkgName = $sub->package?->tr('name') ?? __('personal.membership');
                    $period = $sub->start_date
                        ? $sub->start_date->format('d M Y') . ($sub->end_date ? ' – ' . $sub->end_date->format('d M Y') : '')
                        : optional($sub->created_at)->format('d M Y');
                    [$icon, $iconBg, $tone] = $paid
                        ? ['bi-check-circle-fill', 'bg-green-100', 'text-green-600']
                        : ($status === 'pending_approval'
                            ? ['bi-hourglass-split', 'bg-amber-100', 'text-amber-600']
                            : ['bi-exclamation-circle-fill', 'bg-amber-100', 'text-amber-600']);
                @endphp
                <div id="pay-card-{{ $sub->id }}"
                     class="m-card {{ $settleable ? 'm-press cursor-pointer' : '' }} bg-white rounded-2xl shadow-sm border border-gray-100 p-3.5 mb-2.5 flex items-center gap-3"
                     @if($settleable)
                         role="button"
                         data-id="{{ $sub->id }}" data-name="{{ $pkgName }}" data-period="{{ $period }}"
                         data-amount="{{ number_format((float) $amount, 2) }}" data-cur="{{ $cur }}"
                         @click="openSettle($el)"
                     @endif>
                    <span data-role="icon" class="w-10 h-10 rounded-xl {{ $iconBg }} grid place-items-center flex-shrink-0">
                        <i class="bi {{ $icon }} {{ $tone }}"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold text-foreground truncate">{{ $pkgName }}</p>
                        <p class="text-[11px] text-muted-foreground truncate">{{ $sub->tenant?->tr('club_name') ?? '' }} · {{ $period }}</p>
                        <p data-role="meta" class="text-[11px] {{ $paid ? 'text-green-600' : 'text-muted-foreground' }} truncate">
                            @if($paid && $sub->settled_at){{ __('Settled') }} {{ $sub->settled_at->format('d M Y') }}
                            @elseif($status === 'pending_approval'){{ __('Awaiting approval') }}
                            @elseif($settleable){{ __('Tap to settle') }}
                            @endif
                        </p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p data-role="amount" class="text-sm font-bold {{ $tone }}">{{ $cur }} {{ number_format((float) $amount, 2) }}</p>
                        <span data-role="status" class="text-[10px] font-medium capitalize {{ $tone }}">{{ str_replace('_', ' ', $status) }}</span>
                    </div>
                    @if($settleable)<i data-role="chev" class="bi bi-chevron-right text-muted-foreground/40 flex-shrink-0"></i>@endif
                </div>
            @empty
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm px-6 py-12 text-center">
                    <i class="bi bi-receipt text-4xl text-gray-300 m-float inline-block"></i>
                    <p class="text-sm text-muted-foreground mt-3">{{ __('personal.no_payment_history') }}</p>
                </div>
            @endforelse
        </div>
    </div>

    {{-- ===== Settle-bill bottom sheet (teleported so nothing clips it) ===== --}}
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[60]" @keydown.escape.window="close()">
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/40" @click="close()"></div>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="translate-y-0" x-transition:leave-end="translate-y-full"
                 class="absolute inset-x-0 bottom-0 max-h-[92vh] flex flex-col bg-white rounded-t-3xl shadow-2xl">

                {{-- Header --}}
                <div class="flex-shrink-0 px-5 pt-3 pb-4 border-b border-gray-100">
                    <div class="w-10 h-1.5 bg-gray-200 rounded-full mx-auto mb-3"></div>
                    <h3 class="text-lg font-bold text-gray-900">{{ __('Settle bill') }}</h3>
                    <p class="text-sm text-muted-foreground truncate" x-text="current.name"></p>
                </div>

                {{-- Scrollable body --}}
                <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
                    {{-- Amount + period --}}
                    <div class="rounded-2xl bg-muted/40 p-4 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">{{ __('personal.due') }}</p>
                            <p class="text-2xl font-extrabold text-primary mt-0.5 truncate">
                                <span x-text="current.cur"></span> <span x-text="current.amount"></span>
                            </p>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">{{ __('Period') }}</p>
                            <p class="text-xs text-foreground mt-0.5" x-text="current.period"></p>
                        </div>
                    </div>

                    {{-- Proof upload --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">{{ __('Payment proof') }}</label>
                        <label class="relative flex flex-col items-center justify-center border-2 border-dashed border-gray-200 rounded-2xl p-6 cursor-pointer hover:border-primary/50 transition-colors overflow-hidden">
                            <template x-if="!preview">
                                <div class="text-center">
                                    <i class="bi bi-camera text-3xl text-gray-300"></i>
                                    <p class="text-sm text-muted-foreground mt-2">{{ __('Tap to add a photo of your receipt') }}</p>
                                </div>
                            </template>
                            <template x-if="preview">
                                <img :src="preview" class="max-h-56 rounded-xl object-contain" alt="">
                            </template>
                            <input type="file" accept="image/*" class="hidden" @change="pickFile($event)">
                        </label>
                        <p class="text-[11px] text-muted-foreground mt-2">
                            <i class="bi bi-info-circle mr-1"></i>{{ __('The club will review and approve your payment.') }}
                        </p>
                    </div>
                </div>

                {{-- Sticky footer --}}
                <div class="flex-shrink-0 px-5 pt-3 border-t border-gray-100 flex gap-3"
                     style="padding-bottom: calc(0.75rem + env(safe-area-inset-bottom));">
                    <button type="button" @click="close()"
                        class="flex-1 py-3 rounded-xl border border-gray-200 text-gray-700 font-medium active:scale-[.98] transition">
                        {{ __('Cancel') }}
                    </button>
                    <button type="button" @click="submit()" :disabled="submitting || !proof"
                        class="flex-1 py-3 rounded-xl bg-primary text-white font-semibold active:scale-[.98] transition disabled:opacity-60 flex items-center justify-center gap-2">
                        <span x-show="!submitting"><i class="bi bi-send mr-1"></i>{{ __('Send for review') }}</span>
                        <span x-show="submitting" class="flex items-center gap-2"><i class="bi bi-arrow-repeat animate-spin"></i>{{ __('Sending…') }}</span>
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>

<script>
window.paymentsPage = function (cfg) {
    return {
        open: false,
        submitting: false,
        proof: null,
        preview: null,
        current: { id: null, name: '', period: '', amount: '', cur: '' },

        openSettle(el) {
            this.current = {
                id: el.dataset.id, name: el.dataset.name, period: el.dataset.period,
                amount: el.dataset.amount, cur: el.dataset.cur,
            };
            this.proof = null; this.preview = null; this.open = true;
        },
        close() { this.open = false; },

        pickFile(e) {
            const f = e.target.files && e.target.files[0];
            if (!f) return;
            if (!f.type.startsWith('image/')) { window.showToast && window.showToast('error', @js(__('Please choose an image.'))); return; }
            const r = new FileReader();
            r.onload = () => { this.proof = r.result; this.preview = r.result; };
            r.readAsDataURL(f);
        },

        async submit() {
            if (!this.proof) { window.showToast && window.showToast('error', @js(__('Add a payment proof image.'))); return; }
            this.submitting = true;
            try {
                const res = await fetch(cfg.settleBase + '/' + this.current.id + '/settle', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': cfg.csrf, 'X-Requested-With': 'XMLHttpRequest' },
                    body: JSON.stringify({ payment_proof_base64: this.proof }),
                });
                const j = await res.json();
                window.showToast && window.showToast(j.success ? 'success' : 'error', j.message || '');
                if (j.success) { this.patchCard(this.current.id, 'pending'); this.close(); }
            } catch (e) {
                window.showToast && window.showToast('error', @js(__('Something went wrong.')));
            } finally {
                this.submitting = false;
            }
        },

        onRealtime(d) {
            if (d && d.action === 'settled' && d.subscription_id) {
                this.patchCard(String(d.subscription_id), 'paid', d.settled_at);
            }
        },

        // Patch a card in place — no reload (create/update reflects immediately).
        patchCard(id, state, dateText) {
            const card = document.getElementById('pay-card-' + id);
            if (!card) return;
            const icon   = card.querySelector('[data-role=icon]');
            const iconI  = icon && icon.querySelector('i');
            const amount = card.querySelector('[data-role=amount]');
            const status = card.querySelector('[data-role=status]');
            const meta   = card.querySelector('[data-role=meta]');
            const chev   = card.querySelector('[data-role=chev]');

            // No longer tappable once submitted/settled.
            card.classList.remove('m-press', 'cursor-pointer');
            card.removeAttribute('role');
            if (chev) chev.remove();

            if (state === 'pending') {
                if (icon)  icon.className = 'w-10 h-10 rounded-xl bg-amber-100 grid place-items-center flex-shrink-0';
                if (iconI) iconI.className = 'bi bi-hourglass-split text-amber-600';
                if (status) { status.textContent = @js(__('pending approval')); status.className = 'text-[10px] font-medium capitalize text-amber-600'; }
                if (meta)   { meta.textContent = @js(__('Awaiting approval')); meta.className = 'text-[11px] text-muted-foreground truncate'; }
            } else if (state === 'paid') {
                if (icon)  icon.className = 'w-10 h-10 rounded-xl bg-green-100 grid place-items-center flex-shrink-0';
                if (iconI) iconI.className = 'bi bi-check-circle-fill text-green-600';
                if (amount) amount.className = 'text-sm font-bold text-green-600';
                if (status) { status.textContent = @js(__('paid')); status.className = 'text-[10px] font-medium capitalize text-green-600'; }
                if (meta)   { meta.textContent = @js(__('Settled')) + (dateText ? ' ' + dateText : ''); meta.className = 'text-[11px] text-green-600 truncate'; }
            }
        },
    };
};
</script>
@endsection
