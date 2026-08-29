@extends($isMobile ? 'layouts.personal-mobile' : 'layouts.app')

@section('title', __('event-open_mat::messages.join_title'))

{{--
    "Take a corner" — the page somebody lands on after scanning the mat.

    They are not the operator, they may be from another club and another
    country, and they are holding nothing but six characters. So the page asks
    exactly one thing — red or blue — and says who is already standing in each.

    A wrong or expired code says the code is wrong and NOTHING else: never "that
    mat has closed", which would tell a stranger the code was once real.

    One view for phone and laptop, because it is one decision either way; only
    the layout it extends differs.
--}}

@section($isMobile ? 'personal-content' : 'content')
@php $omColor = '#F97316'; @endphp

<div class="{{ $isMobile ? '-mx-4 -mt-4' : '' }}"
     x-data="{
        busy: false,
        corners: @js($corners),
        async take(side) {
            if (this.busy) return;
            this.busy = true;
            try {
                const res = await fetch(@js($takeUrl), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=&quot;csrf-token&quot;]')?.content || '',
                    },
                    body: JSON.stringify({ side }),
                });
                const data = await res.json();
                if (data.success) {
                    this.corners = data.corners;
                    window.showToast?.('success', data.message);
                } else {
                    window.showToast?.('error', data.message || '');
                }
            } catch (e) { window.showToast?.('error', ''); }
            this.busy = false;
        },
        mine(side) {
            const c = this.corners?.[side];
            return c && c.user_id === {{ (int) auth()->id() }};
        },
     }">

    {{-- ══════════════ Hero ══════════════ --}}
    <header class="{{ $isMobile ? 'm-hero px-5 pt-6 pb-16' : '-mx-4 sm:-mx-6 lg:-mx-8 -mt-6 px-8 pt-6 pb-20' }} text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, {{ $omColor }}, {{ $omColor }}b0);">
        <div class="absolute -end-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute end-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="relative z-10">
            <span class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wider bg-white/20 border border-white/30 rounded-full px-2.5 py-1">
                <i class="bi bi-fire"></i>{{ __('event-open_mat::messages.label') }}
            </span>
            <h1 class="{{ $isMobile ? 'text-2xl' : 'text-3xl' }} font-black mt-3 leading-tight">{{ __('event-open_mat::messages.join_title') }}</h1>
            @if($ok)
                {{-- What she actually scanned. Both of these were already resolved
                     server-side and thrown away: a visitor from another club was
                     asked to step onto a mat without being told whose club it was
                     or which sport's rules she was agreeing to. --}}
                <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5 flex-wrap">
                    <i class="bi bi-grid-1x2"></i>{{ __('event-open_mat::messages.join_mat') }} · {{ $court }}
                    @if($event?->tenant?->name)
                        <span class="text-white/60">·</span>
                        <i class="bi bi-building"></i>{{ __('event-open_mat::messages.join_at_club', ['club' => $event->tenant->name]) }}
                    @endif
                </p>
                @if($sport)
                    <span class="inline-flex items-center gap-1.5 mt-2 px-2.5 py-1 rounded-full bg-white/20 border border-white/25 text-[11px] font-bold">
                        <i class="bi bi-shield-check"></i>{{ __('event-open_mat::messages.join_rules', ['sport' => \Illuminate\Support\Str::title($sport)]) }}
                    </span>
                @endif
            @endif
        </div>
    </header>

    <div class="{{ $isMobile ? 'px-4' : '' }} -mt-10 relative z-10 space-y-4 pb-24">

        @if(! $ok)
            <section class="{{ $isMobile ? 'm-card rounded-3xl' : 'bg-white rounded-xl shadow-sm border border-gray-100' }} p-8 text-center">
                <span class="w-14 h-14 rounded-2xl bg-muted grid place-items-center mx-auto text-muted-foreground">
                    <i class="bi bi-qr-code text-2xl"></i>
                </span>
                <p class="text-sm font-bold text-foreground mt-3">{{ $message }}</p>
                <p class="text-xs text-muted-foreground mt-1.5 max-w-xs mx-auto leading-snug">
                    {{ __('event-open_mat::messages.join_ask_operator') }}
                </p>

                {{-- Reopen the scanner, typed code ready.
                     This button used to say "Open your own mat", which on a
                     mistyped code opened a SECOND live mat at the visitor's own
                     club with them as its operator. The person here is trying to
                     get onto somebody else's mat; the only useful action is
                     another go at the code. --}}
                <button type="button"
                        onclick="window.dispatchEvent(new CustomEvent('qr-scan:open', { detail: { manual: true, manualLength: 6, manualLabel: @js(__('header.scan_or_type')), manualPlaceholder: @js(__('header.scan_code_placeholder')) } }))"
                        class="m-press inline-flex items-center gap-2 mt-4 h-11 px-5 rounded-2xl bg-primary text-white font-bold text-sm">
                    <i class="bi bi-qr-code-scan"></i>{{ __('event-open_mat::messages.join_try_again') }}
                </button>
            </section>
        @else
            <p class="text-xs text-muted-foreground text-center">{{ __('event-open_mat::messages.join_signed_in_as', ['name' => $me['name']]) }}</p>

            <div class="{{ $isMobile ? 'space-y-3' : 'grid grid-cols-1 md:grid-cols-2 gap-4' }}">
                @foreach([['aka', 'rose', __('event-open_mat::messages.console_red'), __('event-open_mat::messages.join_take_red')],
                          ['ao', 'blue', __('event-open_mat::messages.console_blue'), __('event-open_mat::messages.join_take_blue')]] as [$side, $tone, $label, $cta])
                    <div class="rounded-3xl border-2 p-5 relative overflow-hidden bg-white
                                {{ $tone === 'rose' ? 'border-rose-300' : 'border-blue-300' }}">
                        <span class="absolute -end-8 -top-8 w-28 h-28 rounded-full {{ $tone === 'rose' ? 'bg-rose-500/10' : 'bg-blue-500/10' }}"></span>
                        <span class="relative block text-[10px] font-black uppercase tracking-wider {{ $tone === 'rose' ? 'text-rose-500' : 'text-blue-500' }}">{{ $label }}</span>

                        <p class="relative text-lg font-black mt-1.5 {{ $tone === 'rose' ? 'text-rose-700' : 'text-blue-700' }}"
                           x-text="corners['{{ $side }}'] ? corners['{{ $side }}'].name : '{{ __('event-open_mat::messages.console_empty_corner') }}'"></p>

                        <p class="relative text-[11px] text-muted-foreground mt-1" x-show="mine('{{ $side }}')">
                            <i class="bi bi-check-circle-fill text-green-600 me-1"></i>{{ __('event-open_mat::messages.join_you') }}
                        </p>

                        <button type="button" @click="take('{{ $side }}')" :disabled="busy || mine('{{ $side }}')"
                                class="relative w-full h-12 rounded-2xl text-white font-black text-sm mt-4 disabled:opacity-40"
                                style="background: linear-gradient(120deg, {{ $tone === 'rose' ? '#E11D48, #F97316' : '#2563EB, #06B6D4' }});">
                            {{ $cta }}
                        </button>
                    </div>
                @endforeach
            </div>

            <p class="text-xs text-muted-foreground text-center leading-relaxed" x-show="mine('aka') || mine('ao')">
                {{ __('event-open_mat::messages.join_joined') }}
            </p>
        @endif
    </div>
</div>
@endsection
