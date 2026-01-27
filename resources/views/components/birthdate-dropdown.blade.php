@props([
    'name' => 'date',
    'id' => 'date',
    'value' => '',
    'required' => false,
    'error' => null,
    'label' => 'Date',
    'minYear' => null,
    'maxYear' => null,
    'minAge' => null,
    'maxAge' => null
])

@php
    $currentYear = date('Y');

    // Calculate year range - support both direct year values and age-based calculation
    if ($minYear !== null && $maxYear !== null) {
        // Direct year range specified
        $startYear = $maxYear;
        $endYear = $minYear;
    } elseif ($minAge !== null && $maxAge !== null) {
        // Age-based calculation (for birthdates)
        $startYear = $currentYear - $minAge;
        $endYear = $currentYear - $maxAge;
    } else {
        // Default: current year to 100 years ago
        $startYear = $currentYear;
        $endYear = $currentYear - 100;
    }

    // Parse existing value
    $selectedDay = '';
    $selectedMonth = '';
    $selectedYear = '';
    if ($value) {
        $parts = explode('-', $value);
        if (count($parts) === 3) {
            $selectedYear = $parts[0];
            $selectedMonth = $parts[1];
            $selectedDay = $parts[2];
        }
    }
@endphp

<div class="mb-3">
    <label class="form-label">{{ $label }}@if($required) <span class="text-danger">*</span>@endif</label>
    <div class="row g-2">
        <div class="col-4">
            <select id="{{ $id }}_day"
                    class="form-select @if($error) is-invalid @endif"
                    {{ $required ? 'required' : '' }}
                    onchange="updateDate_{{ $id }}()">
                <option value="">Day</option>
                @for($day = 1; $day <= 31; $day++)
                    @php $dayPadded = str_pad($day, 2, '0', STR_PAD_LEFT); @endphp
                    <option value="{{ $dayPadded }}" {{ $selectedDay == $dayPadded ? 'selected' : '' }}>
                        {{ $day }}
                    </option>
                @endfor
            </select>
        </div>
        <div class="col-4">
            <select id="{{ $id }}_month"
                    class="form-select @if($error) is-invalid @endif"
                    {{ $required ? 'required' : '' }}
                    onchange="updateDate_{{ $id }}()">
                <option value="">Month</option>
                <option value="01" {{ $selectedMonth == '01' ? 'selected' : '' }}>January</option>
                <option value="02" {{ $selectedMonth == '02' ? 'selected' : '' }}>February</option>
                <option value="03" {{ $selectedMonth == '03' ? 'selected' : '' }}>March</option>
                <option value="04" {{ $selectedMonth == '04' ? 'selected' : '' }}>April</option>
                <option value="05" {{ $selectedMonth == '05' ? 'selected' : '' }}>May</option>
                <option value="06" {{ $selectedMonth == '06' ? 'selected' : '' }}>June</option>
                <option value="07" {{ $selectedMonth == '07' ? 'selected' : '' }}>July</option>
                <option value="08" {{ $selectedMonth == '08' ? 'selected' : '' }}>August</option>
                <option value="09" {{ $selectedMonth == '09' ? 'selected' : '' }}>September</option>
                <option value="10" {{ $selectedMonth == '10' ? 'selected' : '' }}>October</option>
                <option value="11" {{ $selectedMonth == '11' ? 'selected' : '' }}>November</option>
                <option value="12" {{ $selectedMonth == '12' ? 'selected' : '' }}>December</option>
            </select>
        </div>
        <div class="col-4">
            <select id="{{ $id }}_year"
                    class="form-select @if($error) is-invalid @endif"
                    {{ $required ? 'required' : '' }}
                    onchange="updateDate_{{ $id }}()">
                <option value="">Year</option>
                @for($year = $startYear; $year >= $endYear; $year--)
                    <option value="{{ $year }}" {{ $selectedYear == $year ? 'selected' : '' }}>
                        {{ $year }}
                    </option>
                @endfor
            </select>
        </div>
    </div>
    <input type="hidden" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}">
    @if($error)
        <span class="invalid-feedback d-block" role="alert">
            <strong>{{ $error }}</strong>
        </span>
    @endif
</div>

@once
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all date dropdown components
    document.querySelectorAll('[id$="_day"]').forEach(function(daySelect) {
        const baseId = daySelect.id.replace('_day', '');
        if (document.getElementById(baseId + '_month') && document.getElementById(baseId + '_year')) {
            // Component exists, initialize it
            window['updateDate_' + baseId] = function() {
                const day = document.getElementById(baseId + '_day').value;
                const month = document.getElementById(baseId + '_month').value;
                const year = document.getElementById(baseId + '_year').value;
                const hidden = document.getElementById(baseId);

                if (day && month && year) {
                    hidden.value = `${year}-${month}-${day}`;
                } else {
                    hidden.value = '';
                }
            };
        }
    });
});
</script>
@endpush
@endonce
