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

<div class="row g-3 schedule-time-picker" data-picker-id="{{ $uniqueId }}">
    <!-- Days Selector -->
    <div class="col-md-4">
        @if($showLabels)
            <label class="form-label fw-medium">
                {{ $daysLabel }} @if($required)<span class="text-danger">*</span>@endif
            </label>
        @endif
        <div class="dropdown">
            <button
                class="btn btn-outline-secondary w-100 d-flex justify-content-between align-items-center schedule-days-btn"
                type="button"
                id="daysDropdown-{{ $uniqueId }}"
                data-bs-toggle="dropdown"
                data-bs-auto-close="outside"
                aria-expanded="false"
            >
                <span class="selected-days-text text-truncate {{ count($selectedDays) > 0 ? '' : 'text-muted' }}">
                    @if(count($selectedDays) > 0)
                        {{ collect($selectedDays)->map(fn($d) => substr(ucfirst($d), 0, 3))->join(', ') }}
                    @else
                        {{ $daysPlaceholder }}
                    @endif
                </span>
                <i class="bi bi-chevron-down ms-2"></i>
            </button>
            <div class="dropdown-menu w-100 p-3" aria-labelledby="daysDropdown-{{ $uniqueId }}">
                @foreach($days as $value => $label)
                    <div class="form-check mb-2">
                        <input
                            type="checkbox"
                            class="form-check-input schedule-day-checkbox"
                            id="day-{{ $value }}-{{ $uniqueId }}"
                            name="{{ $daysName }}[]"
                            value="{{ $value }}"
                            data-day="{{ substr($label, 0, 3) }}"
                            {{ in_array($value, $selectedDays) ? 'checked' : '' }}
                        >
                        <label class="form-check-label" for="day-{{ $value }}-{{ $uniqueId }}">
                            {{ $label }}
                        </label>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <!-- Start Time -->
    <div class="col-md-4">
        @if($showLabels)
            <label for="startTime-{{ $uniqueId }}" class="form-label fw-medium">
                {{ $startTimeLabel }} @if($required)<span class="text-danger">*</span>@endif
            </label>
        @endif
        <input
            type="time"
            id="startTime-{{ $uniqueId }}"
            name="{{ $startTimeName }}"
            value="{{ $startTime }}"
            class="form-control form-control-lg schedule-start-time"
            {{ $required ? 'required' : '' }}
        >
    </div>

    <!-- End Time -->
    <div class="col-md-4">
        @if($showLabels)
            <label for="endTime-{{ $uniqueId }}" class="form-label fw-medium">
                {{ $endTimeLabel }} @if($required)<span class="text-danger">*</span>@endif
            </label>
        @endif
        <input
            type="time"
            id="endTime-{{ $uniqueId }}"
            name="{{ $endTimeName }}"
            value="{{ $endTime }}"
            class="form-control form-control-lg schedule-end-time"
            {{ $required ? 'required' : '' }}
        >
    </div>
</div>

@once
@push('styles')
<style>
.schedule-time-picker .schedule-days-btn {
    height: calc(3.5rem + 2px);
    text-align: left;
    background-color: #fff;
}
.schedule-time-picker .schedule-days-btn:hover,
.schedule-time-picker .schedule-days-btn:focus {
    border-color: var(--bs-primary);
}
.schedule-time-picker .dropdown-menu.show {
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}
</style>
@endpush

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
                textEl.classList.remove('text-muted');
            } else {
                textEl.textContent = '{{ $daysPlaceholder }}';
                textEl.classList.add('text-muted');
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

        // Trigger update
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
        textEl.classList.add('text-muted');
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
