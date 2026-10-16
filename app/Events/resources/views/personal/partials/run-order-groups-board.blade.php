{{--
    THE GROUPS BOARD — the day as a list with GROUP BOXES on it
    (App\Events\Support\RunSessions). Drawn by both the desktop and the mobile
    day board, inside their `runOrderBoard` scope, while groups are on.

    · The MAIN LIST (`data-drop-list=""`) holds classes in no group, breaks,
      ceremonies and the boxes. Breaks and ceremonies only ever live here.
    · Each BOX (`data-drop-list="<group uuid>"`) holds that group's classes;
      its header (`data-drop-into`) counts as the top of the box.
    · Everything moves by DRAG (gDown → gMove → gEnd in run-order-script): a
      class into a box, out of it, or between boxes; a box as one piece.

    One partial for both boards so the two cannot drift; the boards differ in
    their surroundings, not in this list. Expects: $roColor.
--}}
@php
    $gbModes = [
        'class' => ['bi-collection', __('events::events.run_session_class')],
        'round' => ['bi-arrow-repeat', __('events::events.run_session_round')],
        'manual' => ['bi-hand-index-thumb', __('events::events.run_session_manual')],
    ];
    $gbRow = function () use ($roColor) {
        return view('events::personal.partials.run-order-groups-row', ['roColor' => $roColor])->render();
    };
@endphp

<div data-drop-list="" class="space-y-2">
    <template x-for="row in topRows" :key="row.uuid">
        <div data-drop-item @pointerdown="gDown($event, row)" @touchstart="gDown($event, row)">

            {{-- A class in no group, a break, a ceremony. --}}
            <template x-if="row.kind !== 'group'">
                {!! $gbRow() !!}
            </template>

            {{-- A GROUP: a box with its classes inside. --}}
            <template x-if="row.kind === 'group'">
                <div :data-drop-box="row.uuid" class="rounded-2xl overflow-hidden border-2"
                     style="border-color: {{ $roColor }}; background: {{ $roColor }}0a;">

                    <div data-drop-into :data-drop-into="row.uuid" data-drag-ok
                         class="px-3 py-2.5 text-white flex items-center gap-2.5 cursor-grab select-none relative overflow-hidden"
                         style="background: linear-gradient(150deg, {{ $roColor }}, {{ $roColor }}b0); -webkit-touch-callout:none;">
                        <span class="absolute -right-6 -top-8 w-24 h-24 rounded-full bg-white/10"></span>
                        <span class="w-8 h-8 rounded-lg bg-white/20 grid place-items-center flex-shrink-0 relative"><i class="bi bi-collection-fill"></i></span>
                        <div class="min-w-0 flex-1 relative">
                            {{-- The group's name renames in place (startRename). --}}
                            <template x-if="editing !== row.uuid">
                                <p class="text-[14px] font-black leading-tight break-words cursor-text" @click.stop="startRename(row)"
                                   title="{{ __('events::events.run_group_rename_hint') }}">
                                    <span dir="auto" x-text="row.name"></span>
                                    <i class="bi bi-pencil text-[10px] text-white/60 ms-1"></i>
                                </p>
                            </template>
                            <template x-if="editing === row.uuid">
                                <input type="text" :data-rename="row.uuid" x-model="editLabel" maxlength="160" dir="auto"
                   x-init="$nextTick(() => { $el.focus(); $el.select(); })"
                                       @keydown.enter.prevent="saveRename(row)" @keydown.escape.prevent="cancelRename()" @blur="saveRename(row)"
                                       aria-label="{{ __('events::events.run_group_rename_hint') }}"
                                       class="w-full px-2 py-1 rounded-lg text-[14px] font-black text-gray-900 bg-white border-0 focus:ring-2 focus:ring-white/60">
                            </template>
                            <p class="text-[11px] text-white/85 mt-0.5" x-text="meta(row) + (groupSpan(row) ? ' · ' + groupSpan(row) : '')"></p>
                        </div>
                        <button type="button" @click.stop="remove(row)" aria-label="{{ __('events::events.run_group_remove') }}" title="{{ __('events::events.run_group_remove') }}"
                                class="relative flex-none w-8 h-8 rounded-full bg-white/15 border border-white/25 grid place-items-center">
                            <i class="bi bi-x-lg text-[11px]"></i>
                        </button>
                    </div>

                    {{-- How this group calls its bouts. --}}
                    <div class="px-2.5 pt-2.5 flex flex-wrap items-center gap-2">
                        <div class="inline-flex flex-wrap rounded-lg border border-gray-200 bg-white p-0.5 gap-0.5" role="group"
                             aria-label="{{ __('events::events.run_session_label') }}">
                            @foreach ($gbModes as $gbMode => [$gbIcon, $gbLabel])
                                <button type="button" @click.stop="setGroupMode(row, '{{ $gbMode }}')" :disabled="busy"
                                        :aria-pressed="row.group_head && row.group_head.mode === '{{ $gbMode }}'"
                                        class="h-8 px-2.5 rounded-md text-[11.5px] font-bold inline-flex items-center gap-1.5 transition-colors"
                                        :style="row.group_head && row.group_head.mode === '{{ $gbMode }}' ? 'background: {{ $roColor }}; color:#fff;' : 'color:#6b7280;'">
                                    <i class="bi {{ $gbIcon }}"></i><span>{{ $gbLabel }}</span>
                                </button>
                            @endforeach
                        </div>
                        <template x-if="row.group_head && row.group_head.mode === 'manual'">
                            <p class="w-full text-[11.5px] text-muted-foreground leading-snug">
                                {{ __('events::events.run_session_manual_hint') }}
                                <button type="button" @click.stop="window.dispatchEvent(new CustomEvent('order-page:show-mats', { detail: {} }))"
                                        class="font-bold underline" style="color: {{ $roColor }};">{{ __('events::events.run_session_manual_go') }}</button>
                            </p>
                        </template>
                    </div>

                    {{-- The group's classes. Drop a class anywhere in here. --}}
                    <div data-drop-list :data-drop-list="row.uuid" class="p-2 space-y-1.5" style="min-height: 3.25rem;">
                        <template x-for="row in membersOf(row.uuid)" :key="row.uuid">
                            <div data-drop-item @pointerdown="gDown($event, row)" @touchstart="gDown($event, row)">
                                {!! $gbRow() !!}
                            </div>
                        </template>
                        <template x-if="! membersOf(row.uuid).length">
                            <p class="text-center text-[12px] text-muted-foreground py-3">{{ __('events::events.run_group_drop_here') }}</p>
                        </template>
                    </div>

                    <div class="px-2 pb-2">
                        <button type="button" @click.stop="openGroupAdd(row)"
                                class="w-full h-10 rounded-xl border-2 border-dashed text-[12.5px] font-bold inline-flex items-center justify-center gap-1.5 transition-colors"
                                style="border-color: {{ $roColor }}66; color: {{ $roColor }};">
                            <i class="bi bi-plus-lg"></i>
                            <span x-text="@js(__('events::events.run_group_add_classes', ['name' => '__N__'])).replace('__N__', row.name)"></span>
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </template>
</div>
