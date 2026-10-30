@extends('layouts.app')

@section('title', __('events::events.run_order_title').' · '.$e['title'])

{{--
    The running order board — desktop.

    The same jobs as the mobile board (personal/mobile/event-run-order), laid
    out wide: the day down the left, the two numbers the organiser actually sets
    down the right. Both run the ONE `runOrderBoard` Alpine component in
    personal/partials/run-order-script, and both open the same sheets — so a
    rule about what may move cannot be true on one and false on the other.

    Moving here is the arrow pair, which is faster with a mouse and is the
    keyboard path; tap-to-pick still works, because it is the same component.
--}}

@section('content')
@php
    $roColor = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) ($e['color'] ?? '')) ? $e['color'] : '#7c3aed';
    $cfg = [
        'eventUuid' => $e['key'],
        'day' => $day,
        'color' => $roColor,
        'sheet' => $sheet,
        'dataUrl' => route('me.events.run-order.data', ['event' => $e['key'], 'day' => $day]),
        'storeUrl' => route('me.events.run-order.store', $e['key']),
        'reorderUrl' => route('me.events.run-order.reorder', $e['key']),
        'slotUrl' => route('me.events.run-order.update', ['event' => $e['key'], 'slot' => '__SLOT__']),
        'errorText' => __('shared.something_went_wrong'),
        'enteredText' => __('events::events.run_order_entered', ['count' => ':count', 'bouts' => ':bouts']),
        'noBoutsText' => __('events::events.run_order_no_bouts'),
        'pinnedText' => __('events::events.run_order_pinned', ['time' => ':time']),
        'orphanText' => __('events::events.run_order_orphaned'),
        'removeTitle' => __('events::events.run_order_remove'),
        'removeBody' => __('events::events.run_order_remove').' — :name',
        'removeConfirm' => __('events::events.run_order_remove'),
    ];
@endphp

@include('partials.marquee-style')
@include('events::personal.partials.run-order-script')

<div x-data="runOrderBoard(@js($cfg))" x-cloak>

    <div class="-mx-4 sm:-mx-6 lg:-mx-8 -mt-6 overflow-hidden shadow-sm mb-6 text-white relative"
         style="background: linear-gradient(150deg, {{ $roColor }}, {{ $roColor }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="relative px-4 sm:px-6 lg:px-8 py-6 sm:py-8">
            <div class="flex items-center justify-between gap-2 mb-4">
                <a href="{{ route('me.events.manage', $e['key']) }}"
                   class="inline-flex items-center w-10 h-10 justify-center rounded-full bg-white/15 border border-white/25 backdrop-blur text-white text-sm font-semibold hover:bg-white/25 transition-colors"
                   aria-label="{{ __('personal.event_manage_title') }}" title="{{ __('personal.event_manage_title') }}">
                    <i class="bi bi-chevron-left rtl:rotate-180"></i>
                </a>

                <div class="flex items-center gap-3">
                    <div class="px-4 py-2 rounded-2xl bg-white/15 border border-white/25 backdrop-blur text-end">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-white/80">{{ __('events::events.run_order_finishes') }}</p>
                        <p class="text-xl font-black leading-none mt-1" x-text="sheet.finishes_label || '—'"></p>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-list-ol"></i> {{ __('events::events.run_order_title') }}
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-calendar-event"></i> {{ __('events::events.timeline_day', ['day' => $day]) }}
                </span>
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ $e['title'] }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                <i class="bi bi-arrow-down-up"></i>{{ __('events::events.run_order_subtitle') }}
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <div class="lg:col-span-2 space-y-4">

            <template x-for="c in conflicts" :key="c.first + c.second">
                <div class="rounded-xl p-4 flex items-start gap-3 bg-orange-50 border border-orange-200">
                    <i class="bi bi-exclamation-triangle-fill text-orange-700 mt-0.5 flex-shrink-0"></i>
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-orange-900"
                           x-text="c.count === 1 ? @js(trans_choice('events::events.run_order_conflict_title', 1, ['count' => 1])) : @js(trans_choice('events::events.run_order_conflict_title', 2, ['count' => ':n'])).replace(':n', c.count)"></p>
                        <p class="text-[13px] text-orange-900/90 leading-snug mt-1"
                           x-text="@js(__('events::events.run_order_conflict_body', ['first' => ':a', 'second' => ':b'])).replace(':a', c.first).replace(':b', c.second)"></p>
                    </div>
                </div>
            </template>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-2">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">{{ __('events::events.run_order_title') }}</span>
                    <span class="ms-auto text-xs font-semibold text-muted-foreground" x-text="rows.length"></span>
                </div>

                <div class="p-2">
                    <template x-if="isEmpty">
                        <div class="px-6 py-14 text-center">
                            <span class="w-16 h-16 rounded-2xl grid place-items-center mx-auto"
                                  style="background: {{ $roColor }}14; color: {{ $roColor }};">
                                <i class="bi bi-list-ol text-3xl"></i>
                            </span>
                            <p class="text-base font-bold text-gray-900 mt-4">{{ __('events::events.run_order_empty_title') }}</p>
                            <p class="text-sm text-muted-foreground leading-snug mt-2 max-w-md mx-auto">{{ __('events::events.run_order_empty_body') }}</p>
                        </div>
                    </template>

                    <template x-for="(row, i) in rows" :key="row.uuid">
                        <div class="flex items-center gap-3 p-2.5 rounded-lg hover:bg-gray-50 transition-colors"
                             :style="row.is_division ? '' : 'background:#f9fafb;'">

                            <div class="flex-none flex flex-col gap-0.5">
                                <button type="button" @click="nudge(row.uuid, -1)" :disabled="i === 0"
                                        aria-label="{{ __('events::events.run_order_move_earlier') }}"
                                        class="w-6 h-4.5 grid place-items-center border border-gray-200 bg-white rounded-t-md disabled:opacity-40">
                                    <i class="bi bi-chevron-up text-[9px] text-gray-500"></i>
                                </button>
                                <button type="button" @click="nudge(row.uuid, 1)" :disabled="i === rows.length - 1"
                                        aria-label="{{ __('events::events.run_order_move_later') }}"
                                        class="w-6 h-4.5 grid place-items-center border border-gray-200 bg-white rounded-b-md disabled:opacity-40">
                                    <i class="bi bi-chevron-down text-[9px] text-gray-500"></i>
                                </button>
                            </div>

                            <div class="flex-none w-20 text-end">
                                <p class="text-sm font-black text-gray-900 tabular-nums" x-text="row.starts_label"></p>
                                <p class="text-[10px] font-semibold text-gray-400 mt-0.5" x-text="row.duration + ' min'"></p>
                            </div>

                            <span class="flex-none w-2 h-2 rounded-full" :style="`background: ${dot(row)};`"></span>

                            <div class="min-w-0 flex-1">
                                <p class="mq text-sm font-bold text-gray-900 leading-snug"><span dir="auto" x-text="row.name"></span></p>
                                <p class="mq text-[11px] text-gray-400 mt-0.5"><span x-text="meta(row)"></span></p>
                            </div>

                            <button type="button" @click="togglePin(row)"
                                    :aria-label="row.pinned ? @js(__('events::events.run_order_unpin')) : @js(__('events::events.run_order_pin'))"
                                    :title="row.pinned ? @js(__('events::events.run_order_unpin')) : @js(__('events::events.run_order_pin'))"
                                    class="flex-none w-8 h-8 rounded-full grid place-items-center border transition-colors"
                                    :style="row.pinned ? `background: {{ $roColor }}; border-color: {{ $roColor }}; color:#fff;` : 'border-color:#e5e7eb; color:#9ca3af;'">
                                <i class="bi bi-pin-angle-fill text-xs"></i>
                            </button>

                            <button type="button" @click="remove(row)" title="{{ __('events::events.run_order_remove') }}"
                                    aria-label="{{ __('events::events.run_order_remove') }}"
                                    class="flex-none w-8 h-8 rounded-full grid place-items-center border border-gray-200 text-gray-400 hover:border-red-300 hover:text-red-600 transition-colors">
                                <i class="bi bi-x-lg text-[11px]"></i>
                            </button>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <div class="space-y-4">

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground mb-4">{{ __('events::events.run_order_day_starts') }}</p>

                <div class="flex items-center gap-3 mb-4">
                    <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0"
                          style="background: {{ $roColor }}14; color: {{ $roColor }};"><i class="bi bi-clock"></i></span>
                    <div class="min-w-0">
                        <p class="text-lg font-black text-gray-900 leading-none" x-text="sheet.starts_label || '—'"></p>
                        {{-- Once bouts have run the start is the real one; the plan stays beside it. --}}
                        <p class="text-[11px] text-muted-foreground" x-show="sheet.started && sheet.planned_starts_label" x-cloak>{{ __('events::events.run_order_planned') }} <span x-text="sheet.planned_starts_label"></span></p>
                        <p class="text-[11px] text-muted-foreground mt-1">{{ __('events::events.run_order_bout_length') }} <span x-text="sheet.bout_minutes"></span> min</p>
                    </div>
                </div>

                <p class="text-[12px] text-muted-foreground leading-relaxed p-3 rounded-lg"
                   style="background: {{ $roColor }}0f;">{{ __('events::events.run_order_derived') }}</p>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground mb-3">{{ __('events::events.run_order_bench') }}</p>

                <template x-if="bench.length">
                    <button type="button" @click="openAdd()"
                            class="w-full flex items-center gap-3 p-3 rounded-xl text-start transition-colors hover:brightness-95 mb-3"
                            style="background: {{ $roColor }}0f;">
                        <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0"
                              style="background: {{ $roColor }}; color:#fff;"><i class="bi bi-plus-lg"></i></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-bold text-gray-900">{{ __('events::events.run_order_add_all') }}</span>
                            <span class="block text-[11px] text-muted-foreground" x-text="bench.length"></span>
                        </span>
                    </button>
                </template>

                <template x-if="! bench.length">
                    <p class="text-[12px] text-muted-foreground mb-3">&mdash;</p>
                </template>

                <div class="flex flex-wrap gap-2">
                    <button type="button" @click="openBlock('ceremony')" class="h-9 px-3.5 rounded-full border border-gray-200 text-xs font-bold text-gray-700 hover:bg-gray-50 transition-colors">{{ __('events::events.run_order_add_ceremony') }}</button>
                    <button type="button" @click="openBlock('break')" class="h-9 px-3.5 rounded-full border border-gray-200 text-xs font-bold text-gray-700 hover:bg-gray-50 transition-colors">{{ __('events::events.run_order_add_break') }}</button>
                    <button type="button" @click="openBlock('custom')" class="h-9 px-3.5 rounded-full border border-gray-200 text-xs font-bold text-gray-700 hover:bg-gray-50 transition-colors">{{ __('events::events.run_order_add_custom') }}</button>
                </div>
            </div>
        </div>
    </div>

    @include('events::personal.partials.run-order-sheets', ['roColor' => $roColor])
</div>
@endsection
