@props([
    'id' => 'schedule',
    'daysName' => 'days',
    'startTimeName' => 'start_time',
    'endTimeName' => 'end_time',
    'selectedDays' => [],
    'startTime' => '',
    'endTime' => '',
    'daysLabel' => 'Days',
    'startTimeLabel' => 'Start Time',
    'endTimeLabel' => 'End Time',
    'daysPlaceholder' => 'Select days',
    'required' => false,
    'showLabels' => true,
])

@php
    $days = [
        'saturday' => 'Saturday',
        'sunday' => 'Sunday',
        'monday' => 'Monday',
        'tuesday' => 'Tuesday',
        'wednesday' => 'Wednesday',
        'thursday' => 'Thursday',
        'friday' => 'Friday',
    ];

    $uniqueId = $id . '-' . uniqid();
@endphp

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 schedule-time-picker" data-picker-id="{{ $uniqueId }}" x-data="{ daysOpen: false }">
    <!-- Days Selector -->
    <div>
        @if($showLabels)
            <label class="tf-label">
                {{ $daysLabel }} @if($required)<span class="text-red-500">*</span>@endif
            </label>
        @endif
        <div class="relative">
            <button
                type="button"
                @click="daysOpen = !daysOpen"
                @click.away="daysOpen = false"
                class="w-full h-12 px-4 text-left bg-white border-2 border-primary/20 rounded-xl flex items-center justify-between transition-all duration-300 hover:border-primary focus:border-primary focus:ring-4 focus:ring-primary/10 focus:outline-none"
                id="daysDropdown-{{ $uniqueId }}"
            >
                <span class="selected-days-text truncate {{ count($selectedDays) > 0 ? 'text-gray-700' : 'text-gray-400' }}">
                    @if(count($selectedDays) > 0)
                        {{ collect($selectedDays)->map(fn($d) => substr(ucfirst($d), 0, 3))->join(', ') }}
                    @else
                        {{ $daysPlaceholder }}
                    @endif
                </span>
                <i class="bi bi-chevron-down ml-2 transition-transform" :class="{ 'rotate-180': daysOpen }"></i>
            </button>

            <!-- Dropdown Menu -->
            <div x-show="daysOpen"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-1"
                 class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg p-3">
                @foreach($days as $value => $label)
                    <label class="flex items-center gap-2 py-2 px-1 hover:bg-gray-50 rounded-lg cursor-pointer">
                        <input
                            type="checkbox"
                            class="w-4 h-4 rounded border-primary/30 text-primary focus:ring-primary/25 schedule-day-checkbox"
                            id="day-{{ $value }}-{{ $uniqueId }}"
                            name="{{ $daysName }}[]"
                            value="{{ $value }}"
                            data-day="{{ substr($label, 0, 3) }}"
                            {{ in_array($value, $selectedDays) ? 'checked' : '' }}
                        >
                        <span class="text-sm text-gray-700">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Start Time -->
    <div>
        @if($showLabels)
            <label for="startTime-{{ $uniqueId }}" class="tf-label">
                {{ $startTimeLabel }} @if($required)<span class="text-red-500">*</span>@endif
            </label>
        @endif
        <input
            type="time"
            id="startTime-{{ $uniqueId }}"
            name="{{ $startTimeName }}"
            value="{{ $startTime }}"
            class="tf-time schedule-start-time"
            {{ $required ? 'required' : '' }}
        >
    </div>

    <!-- End Time -->
    <div>
        @if($showLabels)
            <label for="endTime-{{ $uniqueId }}" class="tf-label">
                {{ $endTimeLabel }} @if($required)<span class="text-red-500">*</span>@endif
            </label>
        @endif
        <input
            type="time"
            id="endTime-{{ $uniqueId }}"
            name="{{ $endTimeName }}"
            value="{{ $endTime }}"
            class="tf-time schedule-end-time"
            {{ $required ? 'required' : '' }}
        >
    </div>
</div>

@once
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all schedule time pickers
    document.querySelectorAll('.schedule-time-picker').forEach(function(picker) {
        const checkboxes = picker.querySelectorAll('.schedule-day-checkbox');
        const textEl = picker.querySelector('.selected-days-text');

        function updateSelectedText() {
            const selected = Array.from(picker.querySelectorAll('.schedule-day-checkbox:checked'))
                .map(cb => cb.dataset.day);

            if (selected.length > 0) {
                textEl.textContent = selected.join(', ');
                textEl.classList.remove('text-gray-400');
                textEl.classList.add('text-gray-700');
            } else {
                textEl.textContent = '{{ $daysPlaceholder }}';
                textEl.classList.add('text-gray-400');
                textEl.classList.remove('text-gray-700');
            }
        }

        checkboxes.forEach(cb => {
            cb.addEventListener('change', updateSelectedText);
        });
    });
});

// Helper functions for external use
window.ScheduleTimePicker = {
    getSelectedDays: function(pickerId) {
        const picker = document.querySelector(`[data-picker-id="${pickerId}"]`);
        if (!picker) return [];
        return Array.from(picker.querySelectorAll('.schedule-day-checkbox:checked')).map(cb => ({
            value: cb.value,
            name: cb.dataset.day
        }));
    },

    getStartTime: function(pickerId) {
        const picker = document.querySelector(`[data-picker-id="${pickerId}"]`);
        return picker ? picker.querySelector('.schedule-start-time').value : '';
    },

    getEndTime: function(pickerId) {
        const picker = document.querySelector(`[data-picker-id="${pickerId}"]`);
        return picker ? picker.querySelector('.schedule-end-time').value : '';
    },

    setSelectedDays: function(pickerId, days) {
        const picker = document.querySelector(`[data-picker-id="${pickerId}"]`);
        if (!picker) return;

        picker.querySelectorAll('.schedule-day-checkbox').forEach(cb => {
            cb.checked = days.some(d => d.value === cb.value || d === cb.value);
        });

        const event = new Event('change');
        picker.querySelector('.schedule-day-checkbox').dispatchEvent(event);
    },

    setStartTime: function(pickerId, time) {
        const picker = document.querySelector(`[data-picker-id="${pickerId}"]`);
        if (picker) picker.querySelector('.schedule-start-time').value = time;
    },

    setEndTime: function(pickerId, time) {
        const picker = document.querySelector(`[data-picker-id="${pickerId}"]`);
        if (picker) picker.querySelector('.schedule-end-time').value = time;
    },

    reset: function(pickerId) {
        const picker = document.querySelector(`[data-picker-id="${pickerId}"]`);
        if (!picker) return;

        picker.querySelectorAll('.schedule-day-checkbox').forEach(cb => cb.checked = false);
        picker.querySelector('.schedule-start-time').value = '';
        picker.querySelector('.schedule-end-time').value = '';

        const textEl = picker.querySelector('.selected-days-text');
        textEl.textContent = '{{ $daysPlaceholder }}';
        textEl.classList.add('text-gray-400');
        textEl.classList.remove('text-gray-700');
    },

    validate: function(pickerId) {
        const days = this.getSelectedDays(pickerId);
        const startTime = this.getStartTime(pickerId);
        const endTime = this.getEndTime(pickerId);

        const errors = [];
        if (days.length === 0) errors.push('Please select at least one day');
        if (!startTime) errors.push('Start time is required');
        if (!endTime) errors.push('End time is required');
        if (startTime && endTime && endTime <= startTime) errors.push('End time must be after start time');

        return { isValid: errors.length === 0, errors };
    }
};
</script>
@endpush
@endonce
