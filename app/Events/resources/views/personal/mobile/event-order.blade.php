@extends($shell ?? 'members::layouts.personal-mobile')

@section('title', __('events::events.order_title').' · '.$e['title'])

{{--
    THE RUNNING ORDER — mobile. One page, two views of the same day.

    There were two of these, on two addresses, and the console carried two rows
    both reading "Running order". They were never rivals:

      · THE DAY  — the order of the day's PARTS (weigh-in, the opening, each
                   division, the prayer break), with every clock time worked out
                   from that order. Written to `event_run_slots` by RunSheet.
      · THE MATS — the order of the BOUTS inside those divisions, one column per
                   mat. Written to `day`/`court`/`match_no` on `event_matches`
                   by OrderBoard, which is what the wall board and the caller
                   read.

    One says WHEN a division happens; the other says what happens INSIDE it. So
    they are two tabs rather than one merged engine — see
    PersonalEventController::runningOrderPage for why welding them together
    would have been a rewrite of two working boards to solve a naming problem.

    The tab strip only appears for somebody who may manage: THE DAY is the
    organiser's timetable, while THE MATS is read by whoever may score, and an
    official who lands here sees the mats with no strip rather than a tab that
    would 403.

    Both bodies are partials shared with the desktop page where the logic is
    shared (personal/partials/run-order-body-mobile, <x-running-order-board>) —
    a drag that behaves differently on two screens is two bugs waiting.
--}}
@section('personal-content')
@php $roColor = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) ($e['color'] ?? '')) ? $e['color'] : '#7c3aed'; @endphp

<div class="pb-6" x-data="orderPage(@js($view), @js((bool) ($canManage ?? false)))" x-cloak>
    <header class="m-hero -mx-4 -mt-4 px-5 pt-5 pb-16 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, {{ $roColor }}, {{ $roColor }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="flex items-center justify-between relative z-50">
            <a href="{{ ($sealed ?? false) ? url('/e/'.$e['key'].'/admin/manage') : route('me.events.manage', $e['key']) }}"
               data-shell-link
               class="m-press w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur inline-flex items-center justify-center text-white"
               aria-label="{{ __('personal.event_manage_title') }}" title="{{ __('personal.event_manage_title') }}">
                <i class="bi bi-chevron-left rtl:rotate-180"></i>
            </a>
        </div>

        <div class="relative z-10 mt-6">
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
    </header>

    {{-- The two views, as a segmented control riding up over the band's tail —
         the same place a card would sit, because the choice IS the page's first
         card. Rendered only when there are two things to choose between. --}}
    @if($canManage ?? false)
        <div class="-mt-10 relative z-10 mb-4">
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-1.5 flex gap-1.5 m-card"
                 role="tablist" aria-label="{{ __('events::events.order_title') }}">
                <button type="button" role="tab" @click="show('day')" :aria-selected="view === 'day'"
                        class="m-press flex-1 h-11 rounded-xl text-[13px] font-bold inline-flex items-center justify-center gap-2 transition-colors"
                        :class="view === 'day' ? 'text-white' : 'text-muted-foreground'"
                        :style="view === 'day' ? `background: {{ $roColor }};` : ''">
                    <i class="bi bi-calendar3"></i>{{ __('events::events.order_tab_day') }}
                </button>
                <button type="button" role="tab" @click="show('mats')" :aria-selected="view === 'mats'"
                        class="m-press flex-1 h-11 rounded-xl text-[13px] font-bold inline-flex items-center justify-center gap-2 transition-colors"
                        :class="view === 'mats' ? 'text-white' : 'text-muted-foreground'"
                        :style="view === 'mats' ? `background: {{ $roColor }};` : ''">
                    <i class="bi bi-grid-1x2"></i>{{ __('events::events.order_tab_mats') }}
                </button>
            </div>
        </div>

        <div x-show="view === 'day'" x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
            @include('events::personal.partials.run-order-body-mobile', ['roColor' => $roColor])
        </div>
    @endif

    <div @class(['-mt-10 relative z-10' => ! ($canManage ?? false)])
         x-show="view === 'mats'" x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 translate-y-1" x-transition:enter-end="opacity-100 translate-y-0">
        <x-running-order-board :event="$e['key']" :can-arrange="$canArrange" :color="$roColor" :plan="$plan" />
    </div>
</div>

@include('events::personal.partials.order-page-script')
@endsection
