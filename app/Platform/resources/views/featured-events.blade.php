@extends('layouts.admin')

@section('admin-content')
<div x-data="@include('platform::partials.featured-events-state')" class="space-y-6">
    <x-admin-hero eyebrow="{{ __('platform.featured_eyebrow') }}" title="{{ __('platform.featured_title') }}" icon="bi-stars"
                  subtitle="{{ __('platform.featured_subtitle') }}" />

    {{-- ===== The fee ===== --}}
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 flex flex-col lg:flex-row lg:items-end gap-5">
        <div class="flex items-start gap-4 flex-1 min-w-0">
            <span class="w-12 h-12 rounded-2xl grid place-items-center flex-shrink-0 text-white shadow-sm"
                  style="background: linear-gradient(150deg, #f59e0b, #d97706b0);"><i class="bi bi-stars text-xl"></i></span>
            <div class="min-w-0">
                <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2 flex-wrap">
                    {{ __('platform.featured_fee_title') }}
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold"
                          :class="feeLabel ? 'bg-amber-50 text-amber-700' : 'bg-muted text-muted-foreground'"
                          x-text="feeLabel || @js(__('platform.featured_fee_closed'))"></span>
                </h3>
                <p class="text-sm text-muted-foreground mt-1 leading-relaxed">{{ __('platform.featured_fee_hint') }}</p>
            </div>
        </div>
        <form @submit.prevent="saveFee()" class="flex items-end gap-3 flex-shrink-0">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('platform.featured_fee_amount') }}</label>
                <input type="number" min="0" step="0.001" x-model="fee" inputmode="decimal"
                       class="w-36 px-3 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ __('platform.featured_fee_currency') }}</label>
                <input type="text" maxlength="3" x-model="currency" @input="currency = currency.toUpperCase()"
                       class="w-24 px-3 py-2.5 border border-gray-200 rounded-lg uppercase focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            </div>
            <button type="submit" :disabled="savingFee"
                    class="bg-primary text-white px-4 py-2.5 rounded-lg hover:bg-primary/90 transition-colors font-medium disabled:opacity-50 inline-flex items-center gap-2">
                <i class="bi" :class="savingFee ? 'bi-arrow-repeat animate-spin' : 'bi-check-lg'"></i>{{ __('platform.featured_fee_save') }}
            </button>
        </form>
    </div>

    {{-- ===== Tabs ===== --}}
    <div class="border-b border-gray-200 overflow-x-auto">
        <nav class="nav -mb-px flex gap-8">
            @foreach(['pending' => __('platform.featured_tab_pending'), 'approved' => __('platform.featured_tab_approved'), 'history' => __('platform.featured_tab_history')] as $key => $label)
                <button type="button" @click="tab = @js($key)"
                        class="border-b-2 font-medium text-sm py-3 whitespace-nowrap transition-colors"
                        :class="tab === @js($key) ? 'border-purple-500 text-purple-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'">
                    {{ $label }}
                    <span class="ml-2 py-0.5 px-2.5 rounded-full text-xs font-medium bg-purple-100 text-purple-600" x-show="count(@js($key))" x-text="count(@js($key))"></span>
                </button>
            @endforeach
        </nav>
    </div>

    {{-- ===== Requests ===== --}}
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <template x-for="r in shown" :key="r.uuid">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col hover:shadow-md transition-shadow">
                <div class="flex gap-4 p-4">
                    {{-- the proof, as a tile you can open --}}
                    <button type="button" @click="proof(r)" :disabled="! r.proof_url"
                            class="w-24 h-32 rounded-xl overflow-hidden flex-shrink-0 bg-muted grid place-items-center relative group"
                            :title="@js(__('platform.featured_view_proof'))">
                        <template x-if="r.proof_url && ! r.proof_is_pdf">
                            <img :src="r.proof_url" alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy">
                        </template>
                        <template x-if="r.proof_is_pdf">
                            <span class="flex flex-col items-center text-rose-600"><i class="bi bi-file-earmark-pdf-fill text-3xl"></i><span class="text-[10px] font-bold mt-1">PDF</span></span>
                        </template>
                        <span class="absolute inset-x-0 bottom-0 py-1 text-[10px] font-bold text-white bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity">
                            <i class="bi bi-zoom-in"></i> {{ __('platform.featured_view_proof') }}
                        </span>
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
                        <h3 class="font-bold text-gray-900 mt-1.5 leading-snug" x-text="r.title"></h3>
                        <p class="text-xs text-muted-foreground mt-0.5 flex items-center gap-1.5" x-show="r.owner">
                            <i class="bi bi-building"></i><span x-text="r.owner"></span>
                        </p>
                        <p class="text-xs text-muted-foreground mt-0.5 flex items-center gap-1.5" x-show="r.date">
                            <i class="bi bi-calendar3"></i><span x-text="when(r.date)"></span>
                        </p>
                        <span class="inline-flex items-center gap-1 mt-2 px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-50 text-amber-700">
                            <i class="bi bi-cash-coin"></i><span x-text="r.amount"></span>
                        </span>
                    </div>
                </div>

                <div class="px-4 pb-3 text-[11px] text-muted-foreground space-y-0.5">
                    <p x-show="r.requester"><span>{{ __('platform.featured_requested_by') }}</span> <span class="font-semibold text-gray-700" x-text="r.requester"></span> · <span x-text="when(r.requested_at)"></span></p>
                    <p x-show="r.reason" class="text-rose-600"><i class="bi bi-chat-left-quote"></i> <span x-text="r.reason"></span></p>
                    <p x-show="r.decided_by"><span x-text="when(r.decided_at)"></span> {{ __('platform.featured_decided_by') }} <span class="font-semibold text-gray-700" x-text="r.decided_by"></span></p>
                </div>

                <div class="mt-auto px-4 py-3 border-t border-gray-100 flex items-center gap-2">
                    <a :href="r.event_url" x-show="r.event_url" target="_blank" rel="noopener"
                       class="w-9 h-9 rounded-lg flex items-center justify-center bg-card text-foreground hover:bg-accent hover:shadow-sm transition-all border border-border"
                       :title="@js(__('platform.featured_open_event'))"><i class="bi bi-box-arrow-up-right"></i></a>
                    <div class="flex-1"></div>
                    <template x-if="r.status === 'pending'">
                        <div class="flex items-center gap-2">
                            <button type="button" @click="ask(r, 'reject')" :disabled="busy === r.uuid"
                                    class="border border-red-300 text-red-600 hover:bg-red-50 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors disabled:opacity-50">{{ __('platform.featured_reject') }}</button>
                            <button type="button" @click="decide(r, 'approve')" :disabled="busy === r.uuid"
                                    class="bg-primary text-white px-3 py-1.5 rounded-lg hover:bg-primary/90 transition-colors text-sm font-medium disabled:opacity-50 inline-flex items-center gap-1.5">
                                <i class="bi" :class="busy === r.uuid ? 'bi-arrow-repeat animate-spin' : 'bi-check-lg'"></i>{{ __('platform.featured_approve') }}
                            </button>
                        </div>
                    </template>
                    <template x-if="r.status === 'approved'">
                        <button type="button" @click="ask(r, 'revoke')" :disabled="busy === r.uuid"
                                class="border border-red-300 text-red-600 hover:bg-red-50 px-3 py-1.5 rounded-lg text-sm font-medium transition-colors disabled:opacity-50">{{ __('platform.featured_revoke') }}</button>
                    </template>
                </div>
            </div>
        </template>
    </div>

    {{-- per-tab empty states --}}
    @foreach(['pending' => ['bi-inbox', __('platform.featured_empty_pending')], 'approved' => ['bi-stars', __('platform.featured_empty_approved')], 'history' => ['bi-clock-history', __('platform.featured_empty_history')]] as $key => [$icon, $text])
        <div x-show="tab === @js($key) && ! count(@js($key))" x-cloak class="bg-white rounded-2xl border border-gray-100 px-5 py-14 text-center">
            <i class="bi {{ $icon }} text-4xl text-gray-300 m-float"></i>
            <p class="text-sm text-muted-foreground mt-3">{{ $text }}</p>
        </div>
    @endforeach

    @include('platform::partials.featured-events-reason')
    <x-media-lightbox />
</div>
@endsection
