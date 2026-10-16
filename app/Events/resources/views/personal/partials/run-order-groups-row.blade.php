{{--
    One item on the GROUPS board (run-order-groups-board): a class, a break or
    a ceremony. Drawn inside a `row` scope; its buttons never start a drag.
    A male class is blue, a female class pink, everything else white
    (cardStyle in run-order-script).

    Two lines, so a weight class is read by its FULL name even on a phone: the
    time and the name on top, what it holds and its buttons underneath.
--}}
<div class="p-2.5 rounded-xl border cursor-grab select-none"
     :style="cardStyle(row)"
     style="-webkit-touch-callout:none;">
    <div class="flex items-start gap-2.5">
        <div class="flex-none w-[3.75rem]">
            <p class="text-[13px] font-black text-gray-900 tabular-nums leading-snug" x-text="row.starts_label"></p>
        </div>
        <span class="flex-none w-2 h-2 rounded-full mt-1.5" :style="`background: ${dot(row)};`"></span>
        {{-- A block's title renames in place (startRename); a class's is its division's. --}}
        <template x-if="editing !== row.uuid">
            <p class="min-w-0 flex-1 text-sm font-bold text-gray-900 leading-snug break-words" dir="auto"
               :class="row.is_division ? '' : 'cursor-text'" @click.stop="startRename(row)"
               :title="row.is_division ? '' : @js(__('events::events.run_group_rename_hint'))">
                <span x-text="row.name"></span>
                <i x-show="! row.is_division" class="bi bi-pencil text-[10px] text-gray-300 ms-1"></i>
            </p>
        </template>
        <template x-if="editing === row.uuid">
            <input type="text" :data-rename="row.uuid" x-model="editLabel" maxlength="160" dir="auto"
                   x-init="$nextTick(() => { $el.focus(); $el.select(); })"
                   @keydown.enter.prevent="saveRename(row)" @keydown.escape.prevent="cancelRename()" @blur="saveRename(row)"
                   :aria-label="@js(__('events::events.run_group_rename_hint'))"
                   class="min-w-0 flex-1 px-2 py-1 border border-gray-300 rounded-lg text-sm font-bold text-gray-900 bg-white focus:ring-2 focus:ring-purple-500 focus:border-transparent">
        </template>
    </div>
    <div class="mt-1.5 flex items-center gap-1.5">
        <p class="min-w-0 flex-1 text-[11px] text-gray-400 leading-snug break-words"
           x-text="row.duration + ' min · ' + meta(row)"></p>
        <template x-if="row.is_division && row.bouts">
            <button type="button" @click.stop="jumpToMats(row)" :aria-label="@js(__('events::events.order_jump_to_mats'))" :title="@js(__('events::events.order_jump_to_mats'))"
                    class="flex-none w-8 h-8 rounded-full grid place-items-center border border-gray-200 text-gray-400">
                <i class="bi bi-grid-1x2 text-[11px]"></i>
            </button>
        </template>
        <template x-if="row.is_event_break">
            <button type="button" @click.stop="openBreak()" :aria-label="@js(__('events::events.run_order_break_edit'))" :title="@js(__('events::events.run_order_break_edit'))"
                    class="flex-none w-8 h-8 rounded-full grid place-items-center border border-gray-200 text-gray-500"><i class="bi bi-pencil text-xs"></i></button>
        </template>
        <button type="button" @click.stop="togglePin(row)"
                :aria-label="row.pinned ? @js(__('events::events.run_order_unpin')) : @js(__('events::events.run_order_pin'))"
                class="flex-none w-8 h-8 rounded-full grid place-items-center border"
                :style="row.pinned ? 'background: {{ $roColor }}; border-color: {{ $roColor }}; color:#fff;' : 'border-color:#e5e7eb; color:#9ca3af;'">
            <i class="bi bi-pin-angle-fill text-xs"></i>
        </button>
        <button type="button" @click.stop="remove(row)" :aria-label="@js(__('events::events.run_order_remove'))"
                class="flex-none w-8 h-8 rounded-full grid place-items-center border border-gray-200 text-gray-400">
            <i class="bi bi-x-lg text-[11px]"></i>
        </button>
    </div>
</div>
