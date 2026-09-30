@extends('layouts.app')

@section('hide-navbar', true)
@section('title', __('platform.featured_title'))

@section('content')
<div x-data="@include('platform::partials.featured-events-state')" class="min-h-screen bg-background pb-16 px-4 pt-4">

    {{-- ===== Hero band (Design Rule #6) ===== --}}
    <header class="m-hero -mx-4 -mt-4 px-5 pt-5 pb-16 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, #d97706, #d97706b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="flex items-center justify-between relative z-50">
            <a href="{{ route('admin.platform.index') }}" aria-label="{{ __('shared.back') }}" title="{{ __('shared.back') }}"
               class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur inline-flex items-center justify-center text-white">
                <i class="bi bi-chevron-left rtl:rotate-180"></i>
            </a>
        </div>

        <div class="relative z-10 mt-6">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-stars"></i> {{ __('platform.featured_eyebrow') }}
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"
                      x-show="count('pending')"><i class="bi bi-hourglass-split"></i> <span x-text="count('pending')"></span></span>
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ __('platform.featured_title') }}</h1>
            <p class="text-sm text-white/85 mt-1.5 leading-snug">{{ __('platform.featured_subtitle') }}</p>
        </div>
    </header>

    <div class="-mt-10 relative z-10 space-y-4 mobile-stagger">

        {{-- ===== The fee ===== --}}
        <div class="m-card bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
            <div class="flex items-center gap-3">
                <span class="w-11 h-11 rounded-2xl grid place-items-center flex-shrink-0 text-white"
                      style="background: linear-gradient(150deg, #f59e0b, #d97706b0);"><i class="bi bi-cash-coin text-lg"></i></span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-bold text-foreground">{{ __('platform.featured_fee_title') }}</p>
                    <p class="text-[12px] font-semibold mt-0.5" :class="feeLabel ? 'text-amber-700' : 'text-muted-foreground'"
                       x-text="feeLabel || @js(__('platform.featured_fee_closed'))"></p>
                </div>
            </div>
            <p class="text-[12px] text-muted-foreground mt-3 leading-relaxed">{{ __('platform.featured_fee_hint') }}</p>
            <form @submit.prevent="saveFee()" class="mt-3 flex items-end gap-2">
                <div class="flex-1">
                    <label class="block text-[11px] font-medium text-muted-foreground mb-1">{{ __('platform.featured_fee_amount') }}</label>
                    <input type="number" min="0" step="0.001" x-model="fee" inputmode="decimal"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-xl focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                </div>
                <div class="w-20">
                    <label class="block text-[11px] font-medium text-muted-foreground mb-1">{{ __('platform.featured_fee_currency') }}</label>
                    <input type="text" maxlength="3" x-model="currency" @input="currency = currency.toUpperCase()"
                           class="w-full px-3 py-2.5 border border-gray-200 rounded-xl uppercase focus:ring-2 focus:ring-purple-500 focus:border-transparent text-sm">
                </div>
                <button type="submit" :disabled="savingFee" aria-label="{{ __('platform.featured_fee_save') }}"
                        class="m-press h-[42px] px-4 rounded-xl bg-primary text-white text-sm font-bold disabled:opacity-50 inline-flex items-center gap-1.5">
                    <i class="bi" :class="savingFee ? 'bi-arrow-repeat animate-spin' : 'bi-check-lg'"></i>
                </button>
            </form>
        </div>

        {{-- ===== Segmented filter ===== --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-1 flex">
            @foreach(['pending' => __('platform.featured_tab_pending'), 'approved' => __('platform.featured_tab_approved'), 'history' => __('platform.featured_tab_history')] as $key => $label)
                <button type="button" @click="tab = @js($key)"
                        class="m-press flex-1 py-2 rounded-xl text-xs font-semibold transition-colors"
                        :class="tab === @js($key) ? 'bg-primary text-white' : 'text-muted-foreground'">
                    {{ $label }}<span class="opacity-70" x-show="count(@js($key))"> (<span x-text="count(@js($key))"></span>)</span>
                </button>
            @endforeach
        </div>

        {{-- ===== Requests ===== --}}
        <div class="space-y-3">
            <template x-for="r in shown" :key="r.uuid">
                <div class="m-card bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden" x-transition>
                    <div class="flex gap-3 p-3.5">
                        <button type="button" @click="proof(r)" :disabled="! r.proof_url"
                                class="m-press w-[72px] h-24 rounded-xl overflow-hidden flex-shrink-0 bg-muted grid place-items-center relative">
                            <template x-if="r.proof_url && ! r.proof_is_pdf">
                                <img :src="r.proof_url" alt="" class="w-full h-full object-cover" loading="lazy">
                            </template>
                            <template x-if="r.proof_is_pdf">
                                <span class="flex flex-col items-center text-rose-600"><i class="bi bi-file-earmark-pdf-fill text-2xl"></i><span class="text-[10px] font-bold">PDF</span></span>
                            </template>
                            <span class="absolute bottom-1 end-1 w-6 h-6 rounded-full bg-black/50 text-white grid place-items-center text-[11px]"><i class="bi bi-zoom-in"></i></span>
                        </button>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold"
                                      :class="r.kind === 'club' ? 'bg-accent text-primary' : 'bg-slate-100 text-slate-700'"
                                      x-text="r.kind === 'club' ? @js(__('platform.featured_club_event')) : @js(__('platform.featured_federation_event'))"></span>
                                <span x-show="r.ended" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-500">{{ __('platform.featured_ended') }}</span>
                                <span x-show="r.status === 'rejected'" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-600">{{ __('platform.featured_status_rejected') }}</span>
                                <span x-show="r.status === 'revoked'" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-600">{{ __('platform.featured_status_revoked') }}</span>
                            </div>
                            <h3 class="font-bold text-foreground mt-1 leading-snug text-sm" x-text="r.title"></h3>
                            <p class="text-[11px] text-muted-foreground mt-0.5 flex items-center gap-1" x-show="r.owner"><i class="bi bi-building"></i><span x-text="r.owner"></span></p>
                            <div class="flex items-center gap-1.5 mt-1.5 flex-wrap">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-50 text-amber-700"><i class="bi bi-cash-coin"></i><span x-text="r.amount"></span></span>
                                <span class="text-[10px] text-muted-foreground" x-show="r.date"><i class="bi bi-calendar3"></i> <span x-text="when(r.date)"></span></span>
                            </div>
                        </div>
                    </div>
                    <div class="px-3.5 pb-2 text-[11px] text-muted-foreground space-y-0.5">
                        <p x-show="r.requester">{{ __('platform.featured_requested_by') }} <span class="font-semibold text-foreground" x-text="r.requester"></span> · <span x-text="when(r.requested_at)"></span></p>
                        <p x-show="r.reason" class="text-rose-600"><i class="bi bi-chat-left-quote"></i> <span x-text="r.reason"></span></p>
                    </div>
                    <div class="px-3.5 py-2.5 border-t border-gray-100 flex items-center gap-2">
                        <a :href="r.event_url" x-show="r.event_url" target="_blank" rel="noopener"
                           class="m-press w-10 h-10 rounded-xl grid place-items-center bg-muted text-foreground" :aria-label="@js(__('platform.featured_open_event'))"><i class="bi bi-box-arrow-up-right"></i></a>
                        <div class="flex-1"></div>
                        <template x-if="r.status === 'pending'">
                            <div class="flex items-center gap-2">
                                <button type="button" @click="ask(r, 'reject')" :disabled="busy === r.uuid"
                                        class="m-press h-10 px-4 rounded-xl border border-red-300 text-red-600 text-sm font-semibold disabled:opacity-50">{{ __('platform.featured_reject') }}</button>
                                <button type="button" @click="decide(r, 'approve')" :disabled="busy === r.uuid"
                                        class="m-press h-10 px-4 rounded-xl bg-primary text-white text-sm font-bold disabled:opacity-50 inline-flex items-center gap-1.5">
                                    <i class="bi" :class="busy === r.uuid ? 'bi-arrow-repeat animate-spin' : 'bi-check-lg'"></i>{{ __('platform.featured_approve') }}
                                </button>
                            </div>
                        </template>
                        <template x-if="r.status === 'approved'">
                            <button type="button" @click="ask(r, 'revoke')" :disabled="busy === r.uuid"
                                    class="m-press h-10 px-4 rounded-xl border border-red-300 text-red-600 text-sm font-semibold disabled:opacity-50">{{ __('platform.featured_revoke') }}</button>
                        </template>
                    </div>
                </div>
            </template>

            @foreach(['pending' => ['bi-inbox', __('platform.featured_empty_pending')], 'approved' => ['bi-stars', __('platform.featured_empty_approved')], 'history' => ['bi-clock-history', __('platform.featured_empty_history')]] as $key => [$icon, $text])
                <div x-show="tab === @js($key) && ! count(@js($key))" x-cloak class="bg-white rounded-2xl border border-gray-100 px-5 py-10 text-center">
                    <i class="bi {{ $icon }} text-3xl text-gray-300 m-float"></i>
                    <p class="text-sm text-muted-foreground mt-2">{{ $text }}</p>
                </div>
            @endforeach
        </div>
    </div>

    @include('platform::partials.featured-events-reason')
    <x-media-lightbox />
</div>
@endsection
