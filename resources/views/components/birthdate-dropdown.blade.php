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

    if ($minYear !== null && $maxYear !== null) {
        $startYear = $maxYear;
        $endYear = $minYear;
    } elseif ($minAge !== null && $maxAge !== null) {
        $startYear = $currentYear - $minAge;
        $endYear = $currentYear - $maxAge;
    } else {
        $startYear = $currentYear;
        $endYear = $currentYear - 100;
    }

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

<div class="mb-4" x-data="birthdateDropdown_{{ $id }}()">
    <label class="tf-label">
        {{ $label }}@if($required) <span class="text-red-500">*</span>@endif
    </label>

    <div class="grid grid-cols-3 gap-2">
        {{-- Day --}}
        <div class="relative" x-ref="dayWrapper">
            <button type="button" @click="toggleDay()" @click.away="dayOpen = false" x-ref="dayTrigger"
                class="tf-dropdown-trigger {{ $error ? 'border-red-500' : 'border-primary/20 focus:border-primary' }}">
                <span class="flex items-center gap-2">
                    <i class="bi bi-calendar-day text-primary/40" :class="{ 'text-primary': selectedDay }"></i>
                    <span class="text-sm" :class="selectedDay ? 'text-gray-800' : 'text-gray-400'" x-text="selectedDay || 'Day'"></span>
                </span>
                <i class="bi bi-chevron-down text-xs text-gray-400 transition-transform duration-200" :class="{ 'rotate-180': dayOpen }"></i>
            </button>
            <div x-show="dayOpen" x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 :class="dayDropUp ? 'bottom-full mb-1' : 'top-full mt-1'"
                 class="tf-dropdown-menu max-h-52">
                <template x-for="day in days" :key="day">
                    <div @click="selectDay(day)"
                         class="tf-dropdown-item"
                         :class="selectedDay == day ? 'bg-primary/5 font-semibold text-primary' : 'text-gray-700'"
                         x-text="day"></div>
                </template>
            </div>
        </div>

        {{-- Month --}}
        <div class="relative" x-ref="monthWrapper">
            <button type="button" @click="toggleMonth()" @click.away="monthOpen = false" x-ref="monthTrigger"
                class="tf-dropdown-trigger {{ $error ? 'border-red-500' : 'border-primary/20 focus:border-primary' }}">
                <span class="flex items-center gap-2">
                    <i class="bi bi-calendar-month text-primary/40" :class="{ 'text-primary': selectedMonth }"></i>
                    <span class="text-sm" :class="selectedMonth ? 'text-gray-800' : 'text-gray-400'" x-text="selectedMonthLabel || 'Month'"></span>
                </span>
                <i class="bi bi-chevron-down text-xs text-gray-400 transition-transform duration-200" :class="{ 'rotate-180': monthOpen }"></i>
            </button>
            <div x-show="monthOpen" x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 :class="monthDropUp ? 'bottom-full mb-1' : 'top-full mt-1'"
                 class="tf-dropdown-menu max-h-52">
                <template x-for="m in months" :key="m.value">
                    <div @click="selectMonth(m)"
                         class="tf-dropdown-item"
                         :class="selectedMonth == m.value ? 'bg-primary/5 font-semibold text-primary' : 'text-gray-700'"
                         x-text="m.short"></div>
                </template>
            </div>
        </div>

        {{-- Year --}}
        <div class="relative" x-ref="yearWrapper">
            <button type="button" @click="toggleYear()" @click.away="yearOpen = false" x-ref="yearTrigger"
                class="tf-dropdown-trigger {{ $error ? 'border-red-500' : 'border-primary/20 focus:border-primary' }}">
                <span class="flex items-center gap-2">
                    <i class="bi bi-calendar-event text-primary/40" :class="{ 'text-primary': selectedYear }"></i>
                    <span class="text-sm" :class="selectedYear ? 'text-gray-800' : 'text-gray-400'" x-text="selectedYear || 'Year'"></span>
                </span>
                <i class="bi bi-chevron-down text-xs text-gray-400 transition-transform duration-200" :class="{ 'rotate-180': yearOpen }"></i>
            </button>
            <div x-show="yearOpen" x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 :class="yearDropUp ? 'bottom-full mb-1' : 'top-full mt-1'"
                 class="tf-dropdown-menu max-h-52">
                <template x-for="y in years" :key="y">
                    <div @click="selectYear(y)"
                         class="tf-dropdown-item"
                         :class="selectedYear == y ? 'bg-primary/5 font-semibold text-primary' : 'text-gray-700'"
                         x-text="y"></div>
                </template>
            </div>
        </div>
    </div>

    {{-- Age badge --}}
    <div x-show="age !== null" x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="mt-1.5 flex items-center gap-1.5">
        <span class="inline-flex items-center gap-1 text-xs font-medium text-primary/70 bg-primary/5 rounded-full px-2.5 py-0.5">
            <i class="bi bi-person-lines-fill text-[10px]"></i>
            <span x-text="age + ' years old'"></span>
        </span>
    </div>

    <input type="hidden" id="{{ $id }}" name="{{ $name }}" x-model="hiddenValue" {{ $required ? 'required' : '' }}>

    @if($error)
        <span class="tf-error" role="alert">
            <strong>{{ $error }}</strong>
        </span>
    @endif
</div>

<script>
    function birthdateDropdown_{{ $id }}() {
        return {
            dayOpen: false, monthOpen: false, yearOpen: false,
            dayDropUp: false, monthDropUp: false, yearDropUp: false,
            selectedDay: '{{ $selectedDay }}',
            selectedMonth: '{{ $selectedMonth }}',
            selectedMonthLabel: '',
            selectedYear: '{{ $selectedYear }}',
            hiddenValue: '{{ $value }}',
            age: null,
            days: Array.from({ length: 31 }, (_, i) => String(i + 1).padStart(2, '0')),
            months: [
                { value: '01', label: 'January', short: 'Jan' },
                { value: '02', label: 'February', short: 'Feb' },
                { value: '03', label: 'March', short: 'Mar' },
                { value: '04', label: 'April', short: 'Apr' },
                { value: '05', label: 'May', short: 'May' },
                { value: '06', label: 'June', short: 'Jun' },
                { value: '07', label: 'July', short: 'Jul' },
                { value: '08', label: 'August', short: 'Aug' },
                { value: '09', label: 'September', short: 'Sep' },
                { value: '10', label: 'October', short: 'Oct' },
                { value: '11', label: 'November', short: 'Nov' },
                { value: '12', label: 'December', short: 'Dec' }
            ],
            years: Array.from({ length: {{ $startYear }} - {{ $endYear }} + 1 }, (_, i) => {{ $startYear }} - i),

            init() {
                if (this.selectedMonth) {
                    const match = this.months.find(m => m.value === this.selectedMonth);
                    if (match) this.selectedMonthLabel = match.short;
                }
                this.updateValue();
            },

            toggleDay() {
                this.monthOpen = false; this.yearOpen = false;
                if (!this.dayOpen) {
                    const rect = this.$refs.dayTrigger.getBoundingClientRect();
                    this.dayDropUp = (window.innerHeight - rect.bottom) < 220;
                }
                this.dayOpen = !this.dayOpen;
            },
            toggleMonth() {
                this.dayOpen = false; this.yearOpen = false;
                if (!this.monthOpen) {
                    const rect = this.$refs.monthTrigger.getBoundingClientRect();
                    this.monthDropUp = (window.innerHeight - rect.bottom) < 220;
                }
                this.monthOpen = !this.monthOpen;
            },
            toggleYear() {
                this.dayOpen = false; this.monthOpen = false;
                if (!this.yearOpen) {
                    const rect = this.$refs.yearTrigger.getBoundingClientRect();
                    this.yearDropUp = (window.innerHeight - rect.bottom) < 220;
                }
                this.yearOpen = !this.yearOpen;
            },

            selectDay(day) { this.selectedDay = day; this.dayOpen = false; this.updateValue(); },
            selectMonth(m) { this.selectedMonth = m.value; this.selectedMonthLabel = m.short; this.monthOpen = false; this.updateValue(); },
            selectYear(y) { this.selectedYear = String(y); this.yearOpen = false; this.updateValue(); },

            updateValue() {
                if (this.selectedDay && this.selectedMonth && this.selectedYear) {
                    this.hiddenValue = `${this.selectedYear}-${this.selectedMonth}-${this.selectedDay}`;
                    const today = new Date();
                    const birth = new Date(parseInt(this.selectedYear), parseInt(this.selectedMonth) - 1, parseInt(this.selectedDay));
                    let a = today.getFullYear() - birth.getFullYear();
                    const monthDiff = today.getMonth() - birth.getMonth();
                    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) a--;
                    this.age = a >= 0 ? a : null;
                } else {
                    this.hiddenValue = '';
                    this.age = null;
                }
            }
        }
    }
</script>
