{{-- Assign this trainer to package class/schedule slots. Mobile add/edit sheets include this.
     Expects Alpine state: slotIds (array of club_package_activities ids) + toggleSlot(id).
     Expects Blade var: $packageSlots (collection from the controller). --}}
<div class="pt-1 space-y-2">
    <div class="flex items-center gap-2">
        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('admin.ins_package_classes') }}</span>
        <div class="flex-1 h-px bg-gray-100"></div>
    </div>

    @if($packageSlots->isEmpty())
        <p class="text-xs text-muted-foreground">{{ __('admin.ins_no_package_slots') }}</p>
    @else
        <div class="space-y-3">
            @foreach($packageSlots->groupBy('package_id') as $slots)
                <div class="rounded-xl border border-gray-100 overflow-hidden">
                    <p class="px-3 py-2 bg-muted/50 text-xs font-bold text-foreground flex items-center gap-1.5">
                        <i class="bi bi-box text-primary"></i>{{ $slots->first()->package_name }}
                    </p>
                    <div class="divide-y divide-gray-50">
                        @foreach($slots as $slot)
                            <button type="button" @click="toggleSlot({{ $slot->id }})"
                                    :class="slotIds.includes({{ $slot->id }}) ? 'bg-accent/60' : 'bg-white'"
                                    class="m-press w-full flex items-center gap-3 px-3 py-2.5 text-left transition-colors">
                                <span class="w-5 h-5 rounded-md border flex items-center justify-center flex-shrink-0"
                                      :class="slotIds.includes({{ $slot->id }}) ? 'bg-primary border-primary text-white' : 'border-gray-300 text-transparent'">
                                    <i class="bi bi-check text-xs"></i>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-medium text-foreground truncate">{{ $slot->activity_name }}</span>
                                    <span class="block text-[11px] text-muted-foreground truncate">
                                        {{ $slot->schedule_label ?: __('admin.ins_no_schedule') }}@if($slot->instructor_name) · {{ $slot->instructor_name }}@endif
                                    </span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <template x-for="sid in slotIds" :key="sid"><input type="hidden" name="package_slots[]" :value="sid"></template>
</div>
