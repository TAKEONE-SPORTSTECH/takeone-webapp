{{--
    THE DAY — the running order's first tab, on a wide screen.

    The same jobs as the mobile half (personal/partials/run-order-body-mobile),
    laid out wide: the day down the left, the two numbers the organiser actually
    sets down the right. Both run the ONE `runOrderBoard` Alpine component in
    personal/partials/run-order-script, and both open the same sheets — so a
    rule about what may move cannot be true on one and false on the other.

    Moving here is the arrow pair, which is faster with a mouse and is the
    keyboard path; tap-to-pick still works, because it is the same component.

    ── Why a partial ─────────────────────────────────────────────────────────
    This was a page of its own (personal/desktop/event-run-order) until the two
    running-order boards became two tabs of one page — see
    PersonalEventController::runningOrderPage. The markup is that page's body,
    unchanged but for the hero band, which the host page owns now; the one
    number that hero carried (when the day finishes) moved into the card at the
    top of the right-hand column, beside the one that says when it starts.

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
        'removeBody' => __('events::events.run_order_remove').' — :name',
        'removeConfirm' => __('events::events.run_order_remove'),
    ];
@endphp

@include('partials.marquee-style')
@include('events::personal.partials.run-order-script')

<div x-data="runOrderBoard(@js($cfg))" x-cloak>

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

                    {{-- The flat list — exactly as it always was, while groups are off. --}}
                    <template x-if="! cfg.groups">
                    <div>
                    <template x-for="(row, i) in rows" :key="row.uuid">
                        <div data-ro-row @pointerdown="rowDown($event, row.uuid, i)" @touchstart="rowDown($event, row.uuid, i)"
                         style="-webkit-touch-callout:none;-webkit-user-select:none;user-select:none;">
                        {{-- Pick a part (click its name), then place it anywhere —
                             the same pick/place the mobile board has (2026-10-03).
                             The arrows stay for one-step moves and the keyboard. --}}
                        <template x-if="picked && picked !== row.uuid">
                            <button type="button" @click="placeAt(i)"
                                    class="w-full my-1 py-1.5 px-3 rounded-lg text-xs font-bold border border-dashed transition-colors"
                                    :style="`border-color: {{ $roColor }}; color: {{ $roColor }}; background: {{ $roColor }}0a;`">
                                {{ __('events::events.run_order_place_here') }}
                            </button>
                        </template>
                        <div class="flex items-center gap-3 p-2.5 rounded-lg hover:bg-gray-50 transition-colors"
                             :class="picked === row.uuid ? 'border' : 'border border-transparent'"
                             :style="picked === row.uuid ? `background: {{ $roColor }}0f; border-color: {{ $roColor }}4d;` : (row.is_division ? '' : 'background:#f9fafb;')">

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

                            <button type="button" data-drag-ok @click="pick(row.uuid)"
                                    :title="picked === row.uuid ? @js(__('events::events.run_order_tap_to_place')) : @js(__('events::events.run_order_tap_to_move'))"
                                    class="min-w-0 flex-1 text-start">
                                <p class="mq text-sm font-bold text-gray-900 leading-snug"><span dir="auto" x-text="row.name"></span></p>
                                <p class="mq text-[11px] text-gray-400 mt-0.5"><span x-text="meta(row)"></span></p>
                            </button>

                            {{-- This division, on the mats — the seam between
                                 the two tabs: when it runs, and what runs
                                 inside it. --}}
                            <template x-if="row.is_division && row.bouts">
                                <button type="button" @click.stop="jumpToMats(row)"
                                        :aria-label="@js(__('events::events.order_jump_to_mats'))"
                                        :title="@js(__('events::events.order_jump_to_mats'))"
                                        class="flex-none w-8 h-8 rounded-full grid place-items-center border border-gray-200 text-gray-400 hover:border-gray-300 hover:text-gray-600 transition-colors">
                                    <i class="bi bi-grid-1x2 text-[11px]"></i>
                                </button>
                            </template>

                            <template x-if="row.is_event_break">
                                <button type="button" @click.stop="openBreak()" :aria-label="@js(__('events::events.run_order_break_edit'))" :title="@js(__('events::events.run_order_break_edit'))"
                                        class="flex-none w-8 h-8 rounded-full grid place-items-center border border-gray-200 text-gray-500 hover:bg-gray-50"><i class="bi bi-pencil text-xs"></i></button>
                            </template>

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
                        </div>
                    </template>

                    {{-- The tail: placing something at the very end. --}}
                    <template x-if="picked">
                        <button type="button" @click="placeAt(rows.length)"
                                class="w-full my-1 py-1.5 px-3 rounded-lg text-xs font-bold border border-dashed"
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
        </div>

        <div class="space-y-4">

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground mb-4">{{ __('events::events.run_order_day_starts') }}</p>

                <div class="flex items-center gap-3 mb-4">
                    <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0"
                          style="background: {{ $roColor }}14; color: {{ $roColor }};"><i class="bi bi-clock"></i></span>
                    <div class="min-w-0">
                        <p class="text-lg font-black text-gray-900 leading-none" x-text="sheet.starts_label || '—'"></p>
                        <p class="text-[11px] text-muted-foreground mt-1">{{ __('events::events.run_order_bout_length') }} <span x-text="sheet.bout_minutes"></span> min</p>
                    </div>
                </div>

                {{-- When the day finishes. It was a chip in this board's own
                     hero; the hero is the host page's now, so the answer sits
                     beside the question it follows from — the day starts here,
                     and on this arrangement it ends there. --}}
                <div class="flex items-center gap-3 mb-4 pt-4 border-t border-gray-100">
                    <span class="w-10 h-10 rounded-xl grid place-items-center flex-shrink-0"
                          style="background: {{ $roColor }}14; color: {{ $roColor }};"><i class="bi bi-flag-fill"></i></span>
                    <div class="min-w-0">
                        <p class="text-lg font-black text-gray-900 leading-none" x-text="sheet.finishes_label || '—'"></p>
                        <p class="text-[11px] text-muted-foreground mt-1">{{ __('events::events.run_order_finishes') }}</p>
                    </div>
                </div>

                <p class="text-[12px] text-muted-foreground leading-relaxed p-3 rounded-lg"
                   style="background: {{ $roColor }}0f;">{{ __('events::events.run_order_derived') }}</p>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
                <p class="text-[11px] font-bold uppercase tracking-wider text-muted-foreground mb-3">{{ __('events::events.run_order_bench') }}</p>

                {{-- The break set on the event itself, until it is on the day. --}}
                <template x-if="eventBreak">
                    <div class="w-full flex items-center gap-3 p-3 rounded-xl border border-dashed border-gray-300 bg-white mb-3">
                        <span class="w-9 h-9 rounded-xl grid place-items-center flex-shrink-0" style="background:#fef3c7;color:#b45309;"><i class="bi bi-cup-hot-fill"></i></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-bold text-foreground">{{ __('events::events.run_order_event_break') }}</span>
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
@if(\App\Events\Support\RunSessions::enabled())
                    <button type="button" @click="openBlock('group')" class="h-9 px-3.5 rounded-full border border-gray-200 text-xs font-bold text-gray-700 hover:bg-gray-50 transition-colors"><i class="bi bi-collection me-1"></i>{{ __('events::events.run_group_add') }}</button>
                    @endif
                    <button type="button" @click="openBlock('custom')" class="h-9 px-3.5 rounded-full border border-gray-200 text-xs font-bold text-gray-700 hover:bg-gray-50 transition-colors">{{ __('events::events.run_order_add_custom') }}</button>
                </div>
            </div>
        </div>
    </div>

    @include('events::personal.partials.run-order-sheets', ['roColor' => $roColor])
</div>
