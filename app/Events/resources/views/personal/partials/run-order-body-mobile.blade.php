{{--
    THE DAY — the running order's first tab, on a phone.

    ORDER is the only thing the organiser sets. Every clock time on this screen
    is worked out from the day's start, each division's bouts and the bout
    length, so moving a part moves everything after it and nobody retypes
    sixteen times to insert a lunch break.

    Moving is TAP-TO-PICK then TAP-TO-PLACE — the same interaction the bracket's
    arrange mode already uses, which is also its keyboard path. Dragging on a
    phone held in one hand beside a mat is not a serious proposition.

    ── Why a partial ─────────────────────────────────────────────────────────
    This was a page of its own (personal/mobile/event-run-order) until the two
    running-order boards became two tabs of one page — see
    PersonalEventController::runningOrderPage. The markup is that page's body,
    unchanged: only the hero band and the @extends around it went, because the
    host page owns those now. The desktop half is run-order-body-desktop, and
    both still run the ONE `runOrderBoard` component in
    personal/partials/run-order-script, so a rule about what may move cannot be
    true on one and false on the other.

    Expects: $e (the event view), $day, $sheet, $roColor.
--}}

@php
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
        'sessionUrl' => route('me.events.run-order.session', $e['key']),
        // The GROUPS board (RunSessions): boxes, and a drag in and out of them.
        'groups' => \App\Events\Support\RunSessions::enabled(),
        'moveUrl' => route('me.events.run-order.move', $e['key']),
        'groupText' => __('events::events.run_group_meta', ['n' => ':n']),
        'interleavedText' => __('events::events.run_session_interleaved'),
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

    <div class="space-y-4">

        {{-- What the tap does next. It sat in the hero when this was a page of
             its own; the hero belongs to the host page now, and an instruction
             that changes while you are holding something has to live beside the
             thing being held. --}}
        <p class="text-[12px] text-muted-foreground flex items-center gap-1.5 px-0.5"
           x-text="cfg.groups ? @js(__('events::events.run_group_drag_hint')) : (picked ? @js(__('events::events.run_order_tap_to_place')) : @js(__('events::events.run_order_tap_to_move')))"></p>

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

                {{-- The flat list — exactly as it always was, while groups are off. --}}
                <template x-if="! cfg.groups">
                <div>
                <template x-for="(row, i) in rows" :key="row.uuid">
                    <div data-ro-row @pointerdown="rowDown($event, row.uuid, i)" @touchstart="rowDown($event, row.uuid, i)"
                         style="-webkit-touch-callout:none;-webkit-user-select:none;user-select:none;">
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

                            <button type="button" data-drag-ok @click="pick(row.uuid)"
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

                            {{-- This division, on the mats. The day board says
                                 WHEN a division runs; the other tab says what
                                 happens inside it, and this is the seam between
                                 the two questions. Only on a division that has
                                 bouts — there is nothing to look at otherwise. --}}
                            <template x-if="row.is_division && row.bouts">
                                <button type="button" @click.stop="jumpToMats(row)"
                                        :aria-label="@js(__('events::events.order_jump_to_mats'))"
                                        :title="@js(__('events::events.order_jump_to_mats'))"
                                        class="flex-none w-9 h-9 rounded-full grid place-items-center border border-gray-200 text-gray-400">
                                    <i class="bi bi-grid-1x2 text-[12px]"></i>
                                </button>
                            </template>

                            <template x-if="row.is_event_break">
                                <button type="button" @click.stop="openBreak()" :aria-label="@js(__('events::events.run_order_break_edit'))" :title="@js(__('events::events.run_order_break_edit'))"
                                        class="flex-none w-9 h-9 rounded-full grid place-items-center border border-gray-200 text-gray-500 hover:bg-gray-50"><i class="bi bi-pencil text-[13px]"></i></button>
                            </template>

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
                </template>

                {{-- The GROUPS board: boxes on a list, dragged in and out (RunSessions). --}}
                <template x-if="cfg.groups">
                    @include('events::personal.partials.run-order-groups-board', ['roColor' => $roColor])
                </template>
            </div>
        </div>

        {{-- Adding. Divisions come from the event; everything else is the
             organiser's own words. --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 space-y-3 m-card">
            <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground">{{ __('events::events.run_order_bench') }}</p>

            {{-- The break set on the event itself, until it is on the day. --}}
            <template x-if="eventBreak">
                <div class="w-full flex items-center gap-3 p-3 rounded-xl border border-dashed border-gray-300 bg-white mb-3">
                    <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0" style="background:#fef3c7;color:#b45309;"><i class="bi bi-cup-hot-fill"></i></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[13.5px] font-bold text-foreground">{{ __('events::events.run_order_event_break') }}</span>
                        <span class="block text-[11.5px] text-muted-foreground" x-text="eventBreak.starts_label + ' – ' + eventBreak.ends_label + ' · ' + eventBreak.duration + ' min'"></span>
                    </span>
                    {{-- Change the event's break times, or put it on the day. --}}
                    <button type="button" @click="openBreak()" :aria-label="@js(__('events::events.run_order_break_edit'))" :title="@js(__('events::events.run_order_break_edit'))"
                            class="flex-none w-9 h-9 rounded-full grid place-items-center border border-gray-200 text-gray-500 hover:bg-gray-50"><i class="bi bi-pencil"></i></button>
                    <button type="button" @click="addEventBreak()" :disabled="busy" :aria-label="@js(__('events::events.run_order_break_add'))" :title="@js(__('events::events.run_order_break_add'))"
                            class="flex-none w-9 h-9 rounded-full grid place-items-center text-white" style="background: {{ $roColor }};"><i class="bi bi-plus-lg"></i></button>
                </div>
            </template>

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
                @if(\App\Events\Support\RunSessions::enabled())
                    <button type="button" @click="openBlock('group')" class="h-9 px-3.5 rounded-full border border-gray-200 text-[12px] font-bold text-gray-700 m-press"><i class="bi bi-collection me-1"></i>{{ __('events::events.run_group_add') }}</button>
                    @endif
                <button type="button" @click="openBlock('custom')" class="h-9 px-3.5 rounded-full border border-gray-200 text-[12px] font-bold text-gray-700 m-press">{{ __('events::events.run_order_add_custom') }}</button>
            </div>

            <p class="text-[11.5px] text-muted-foreground leading-snug">{{ __('events::events.run_order_derived') }}</p>
        </div>
    </div>

    @include('events::personal.partials.run-order-sheets', ['roColor' => $roColor])
</div>
