@extends($shell ?? 'members::layouts.personal-mobile')

@section('title', __('events::events.run_order_title').' · '.$e['title'])

{{--
    The running order board — mobile.

    ORDER is the only thing the organiser sets. Every clock time on this screen
    is worked out from the day's start, each division's bouts and the bout
    length, so moving a part moves everything after it and nobody retypes
    sixteen times to insert a lunch break.

    Moving is TAP-TO-PICK then TAP-TO-PLACE — the same interaction the bracket's
    arrange mode already uses, which is also its keyboard path. Dragging on a
    phone held in one hand beside a mat is not a serious proposition.

    The desktop board (personal/desktop/event-run-order) is the same jobs laid
    out wide; both run on the ONE Alpine component in
    personal/partials/run-order-script.
--}}

@section('personal-content')
@php
    $roColor = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) ($e['color'] ?? '')) ? $e['color'] : '#7c3aed';
    $cfg = [
        'eventUuid' => $e['key'],
        'day' => $day,
        'color' => $roColor,
        'sheet' => $sheet,
        'dataUrl' => route('me.events.run-order.data', ['event' => $e['key'], 'day' => $day]),
        'storeUrl' => route('me.events.run-order.store', $e['key']),
        'breakUrl' => route('me.events.run-order.break', $e['key']),
        'reorderUrl' => route('me.events.run-order.reorder', $e['key']),
        'slotUrl' => route('me.events.run-order.update', ['event' => $e['key'], 'slot' => '__SLOT__']),
        'errorText' => __('shared.something_went_wrong'),
        'enteredText' => __('events::events.run_order_entered', ['count' => ':count', 'bouts' => ':bouts']),
        'noBoutsText' => __('events::events.run_order_no_bouts'),
        'pinnedText' => __('events::events.run_order_pinned', ['time' => ':time']),
        'orphanText' => __('events::events.run_order_orphaned'),
        'removeTitle' => __('events::events.run_order_remove'),
        'removeBody' => __('events::events.run_order_conflict_body', ['first' => ':name', 'second' => '']),
        'removeConfirm' => __('events::events.run_order_remove'),
    ];
    // The confirm body deserves its own sentence rather than a borrowed one.
    $cfg['removeBody'] = __('events::events.run_order_remove').' — :name';
@endphp

@include('partials.marquee-style')
@include('events::personal.partials.run-order-script')

<div x-data="runOrderBoard(@js($cfg))" x-cloak>

    {{-- The standard hero band. Full-bleed, the event's own colour, the tail
         sized to what rides up over it. --}}
    <header class="m-hero -mx-4 -mt-4 px-5 pt-5 pb-16 text-white relative overflow-hidden"
            style="background: linear-gradient(150deg, {{ $roColor }}, {{ $roColor }}b0);">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        <div class="flex items-center justify-between relative z-50">
            <a href="{{ route('me.events.manage', $e['key']) }}"
               aria-label="{{ __('personal.event_manage_title') }}" title="{{ __('personal.event_manage_title') }}"
               class="w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur inline-flex items-center justify-center text-white m-press">
                <i class="bi bi-chevron-left rtl:rotate-180"></i>
            </a>
        </div>

        <div class="relative z-10 mt-6">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-list-ol"></i> {{ __('events::events.run_order_title') }}
                </span>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-calendar-event"></i> {{ __('events::events.timeline_day', ['day' => $day]) }}
                </span>
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ $e['title'] }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5"
               x-text="picked ? @js(__('events::events.run_order_tap_to_place')) : @js(__('events::events.run_order_tap_to_move'))"></p>
        </div>
    </header>

    <div class="-mt-10 relative z-10 space-y-4">

        {{-- The one number the organiser wants without opening anything. --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 flex items-center gap-3 m-card">
            <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0"
                  style="background: {{ $roColor }}14; color: {{ $roColor }};">
                <i class="bi bi-flag-fill"></i>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground">{{ __('events::events.run_order_finishes') }}</p>
                <p class="text-lg font-black text-foreground leading-tight" x-text="sheet.finishes_label || '—'"></p>
            </div>
            <div class="text-end flex-shrink-0">
                <p class="text-[11px] font-bold uppercase tracking-wide text-muted-foreground">{{ __('events::events.run_order_day_starts') }}</p>
                <p class="text-sm font-bold text-foreground" x-text="sheet.starts_label || '—'"></p>
                {{-- Once bouts have run the start is the real one; the plan stays beside it. --}}
                <p class="text-[11px] text-muted-foreground" x-show="sheet.started && sheet.planned_starts_label" x-cloak>{{ __('events::events.run_order_planned') }} <span x-text="sheet.planned_starts_label"></span></p>
            </div>
        </div>

        {{-- The mistake that actually costs a day: one athlete in two parts
             running, with no time to change kit or breathe. --}}
        <template x-for="c in conflicts" :key="c.first + c.second">
            <div class="rounded-2xl p-3.5 flex items-start gap-2.5 bg-orange-50 border border-orange-200">
                <i class="bi bi-exclamation-triangle-fill text-orange-700 text-sm mt-0.5 flex-shrink-0"></i>
                <div class="min-w-0">
                    <p class="text-[13px] font-bold text-orange-900"
                       x-text="c.count === 1 ? @js(trans_choice('events::events.run_order_conflict_title', 1, ['count' => 1])) : @js(trans_choice('events::events.run_order_conflict_title', 2, ['count' => ':n'])).replace(':n', c.count)"></p>
                    <p class="text-[12px] text-orange-900/90 leading-snug mt-0.5"
                       x-text="@js(__('events::events.run_order_conflict_body', ['first' => ':a', 'second' => ':b'])).replace(':a', c.first).replace(':b', c.second)"></p>
                </div>
            </div>
        </template>

        {{-- The day itself. --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden m-card">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-2">
                <span class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">{{ __('events::events.run_order_title') }}</span>
                <span class="ms-auto text-[11px] font-semibold text-muted-foreground" x-text="rows.length"></span>
            </div>

            <div class="p-2">
                <template x-if="isEmpty">
                    <div class="px-4 py-10 text-center">
                        <span class="w-14 h-14 rounded-2xl grid place-items-center mx-auto"
                              style="background: {{ $roColor }}14; color: {{ $roColor }};">
                            <i class="bi bi-list-ol text-2xl"></i>
                        </span>
                        <p class="text-[15px] font-bold text-foreground mt-3">{{ __('events::events.run_order_empty_title') }}</p>
                        <p class="text-[12px] text-muted-foreground leading-snug mt-1.5 max-w-xs mx-auto">{{ __('events::events.run_order_empty_body') }}</p>
                    </div>
                </template>

                <template x-for="(row, i) in rows" :key="row.uuid">
                    <div>
                        {{-- A place to drop the part being moved. Only ever
                             shown while something is picked. --}}
                        <template x-if="picked && picked !== row.uuid">
                            <button type="button" @click="placeAt(i)"
                                    class="w-full my-1 py-1.5 px-3 rounded-lg text-[11.5px] font-bold border border-dashed"
                                    :style="`border-color: {{ $roColor }}; color: {{ $roColor }}; background: {{ $roColor }}0a;`">
                                {{ __('events::events.run_order_place_here') }}
                            </button>
                        </template>

                        <div class="flex items-center gap-2.5 p-2.5 rounded-xl m-press"
                             :class="picked === row.uuid ? 'border' : 'border border-transparent'"
                             :style="picked === row.uuid ? `background: {{ $roColor }}0f; border-color: {{ $roColor }}4d;` : (row.is_division ? '' : 'background:#f9fafb;')">

                            <button type="button" @click="pick(row.uuid)"
                                    class="flex items-center gap-2.5 min-w-0 flex-1 text-start">
                                <span class="flex-none w-14">
                                    <span class="block text-[13px] font-black text-foreground tabular-nums" x-text="row.starts_label"></span>
                                    <span class="block text-[10px] font-semibold text-muted-foreground" x-text="row.duration + ' min'"></span>
                                </span>
                                <span class="flex-none w-2 h-2 rounded-full" :style="`background: ${dot(row)};`"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="mq block text-[13px] font-bold text-foreground leading-snug"><span dir="auto" x-text="row.name"></span></span>
                                    <span class="mq block text-[11px] text-muted-foreground mt-0.5"><span x-text="meta(row)"></span></span>
                                </span>
                            </button>

                            <button type="button" @click="togglePin(row)"
                                    :aria-label="row.pinned ? @js(__('events::events.run_order_unpin')) : @js(__('events::events.run_order_pin'))"
                                    class="flex-none w-9 h-9 rounded-full grid place-items-center border"
                                    :style="row.pinned ? `background: {{ $roColor }}; border-color: {{ $roColor }}; color:#fff;` : 'border-color:#e5e7eb; color:#9ca3af;'">
                                <i class="bi bi-pin-angle-fill text-[13px]"></i>
                            </button>

                            <button type="button" @click="remove(row)" :aria-label="@js(__('events::events.run_order_remove'))"
                                    class="flex-none w-9 h-9 rounded-full grid place-items-center border border-gray-200 text-gray-400">
                                <i class="bi bi-x-lg text-[12px]"></i>
                            </button>
                        </div>
                    </div>
                </template>

                {{-- The tail: placing something at the very end. --}}
                <template x-if="picked">
                    <button type="button" @click="placeAt(rows.length)"
                            class="w-full my-1 py-1.5 px-3 rounded-lg text-[11.5px] font-bold border border-dashed"
                            :style="`border-color: {{ $roColor }}; color: {{ $roColor }}; background: {{ $roColor }}0a;`">
                        {{ __('events::events.run_order_place_here') }}
                    </button>
                </template>
            </div>
        </div>

        {{-- Adding. Divisions come from the event; everything else is the
             organiser's own words. --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 space-y-3 m-card">
            <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">{{ __('events::events.run_order_bench') }}</p>

            <template x-if="bench.length">
                <button type="button" @click="openAdd()"
                        class="w-full flex items-center gap-3 p-3 rounded-xl text-start m-press"
                        style="background: {{ $roColor }}0f;">
                    <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0"
                          style="background: {{ $roColor }}; color:#fff;"><i class="bi bi-plus-lg"></i></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[13.5px] font-bold text-foreground">{{ __('events::events.run_order_add_all') }}</span>
                        <span class="block text-[11.5px] text-muted-foreground" x-text="bench.length"></span>
                    </span>
                    <i class="bi bi-chevron-right rtl:rotate-180 text-muted-foreground"></i>
                </button>
            </template>

            <div class="flex flex-wrap gap-2">
                <button type="button" @click="openBlock('ceremony')" class="h-9 px-3.5 rounded-full border border-gray-200 text-[12px] font-bold text-gray-700 m-press">{{ __('events::events.run_order_add_ceremony') }}</button>
                <button type="button" @click="openBlock('break')" class="h-9 px-3.5 rounded-full border border-gray-200 text-[12px] font-bold text-gray-700 m-press">{{ __('events::events.run_order_add_break') }}</button>
                <button type="button" @click="openBlock('custom')" class="h-9 px-3.5 rounded-full border border-gray-200 text-[12px] font-bold text-gray-700 m-press">{{ __('events::events.run_order_add_custom') }}</button>
            </div>

            <p class="text-[11.5px] text-muted-foreground leading-snug">{{ __('events::events.run_order_derived') }}</p>
        </div>
    </div>

    @include('events::personal.partials.run-order-sheets', ['roColor' => $roColor])
</div>
@endsection
