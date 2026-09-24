@extends($shell ?? 'layouts.app')

@section('title', __('events::events.order_title').' · '.$e['title'])

{{--
    THE RUNNING ORDER — desktop. One page, two views of the same day.

    The same two boards as the mobile page, and the same reason they are tabs
    rather than one engine (personal/mobile/event-order has the long version,
    PersonalEventController::runningOrderPage has the authority story):

      · THE DAY  — the order of the day's PARTS, every clock time derived.
      · THE MATS — the order of the BOUTS inside them, one column per mat.

    The strip only appears for somebody who may manage; an official who may
    score lands on the mats with no strip at all.
--}}
@section('content')
@php $roColor = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) ($e['color'] ?? '')) ? $e['color'] : '#7c3aed'; @endphp

<div x-data="orderPage(@js($view), @js((bool) ($canManage ?? false)))" x-cloak>

    <div class="-mx-4 sm:-mx-6 lg:-mx-8 -mt-6 overflow-hidden shadow-sm mb-6 text-white relative"
         style="background: linear-gradient(150deg, {{ $roColor }}, {{ $roColor }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="relative px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
            <div class="flex items-center justify-between gap-2 mb-4">
                <a href="{{ route('me.events.manage', $e['key']) }}" data-shell-link
                   class="inline-flex items-center w-10 h-10 justify-center rounded-full bg-white/15 border border-white/25 backdrop-blur text-white text-sm font-semibold hover:bg-white/25 transition-colors"
                   aria-label="{{ __('personal.event_manage_title') }}" title="{{ __('personal.event_manage_title') }}">
                    <i class="bi bi-chevron-left rtl:rotate-180"></i>
                </a>
            </div>

            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-list-ol"></i> {{ __('events::events.order_title') }}
                </span>
                @if($canManage ?? false)
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                        <i class="bi bi-calendar-event"></i> {{ __('events::events.timeline_day', ['day' => $day]) }}
                    </span>
                @endif
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ $e['title'] }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                <i class="bi" :class="view === 'day' ? 'bi-arrow-down-up' : 'bi-hand-index-thumb'"></i>
                <span x-text="view === 'day' ? @js(__('events::events.run_order_subtitle')) : @js(__('events::events.order_sub'))"></span>
            </p>
        </div>
    </div>

    @if($canManage ?? false)
        <div class="border-b border-gray-200 mb-6">
            <nav class="-mb-px flex gap-8" role="tablist" aria-label="{{ __('events::events.order_title') }}">
                <button type="button" role="tab" @click="show('day')" :aria-selected="view === 'day'"
                        class="border-b-2 pb-3 font-medium text-sm inline-flex items-center gap-2 transition-colors"
                        :class="view === 'day' ? '' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        :style="view === 'day' ? `border-color: {{ $roColor }}; color: {{ $roColor }};` : ''">
                    <i class="bi bi-calendar3"></i>{{ __('events::events.order_tab_day') }}
                </button>
                <button type="button" role="tab" @click="show('mats')" :aria-selected="view === 'mats'"
                        class="border-b-2 pb-3 font-medium text-sm inline-flex items-center gap-2 transition-colors"
                        :class="view === 'mats' ? '' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                        :style="view === 'mats' ? `border-color: {{ $roColor }}; color: {{ $roColor }};` : ''">
                    <i class="bi bi-grid-1x2"></i>{{ __('events::events.order_tab_mats') }}
                </button>
            </nav>
        </div>

        <div x-show="view === 'day'" x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            @include('events::personal.partials.run-order-body-desktop', ['roColor' => $roColor])
        </div>
    @endif

    <div x-show="view === 'mats'" x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
        <x-running-order-board :event="$e['key']" :can-arrange="$canArrange" :color="$roColor" :plan="$plan" />
    </div>
</div>

@include('events::personal.partials.order-page-script')
@endsection
