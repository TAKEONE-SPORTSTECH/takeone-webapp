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
        <div class="relative">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none">
                <i class="bi bi-calendar-event text-primary/40" :class="{ 'text-primary': selectedYear }"></i>
            </span>
            <input type="text" inputmode="numeric" maxlength="4" placeholder="Year"
                   x-model="selectedYear"
                   @input="onYearInput($event)"
                   class="tf-dropdown-trigger pl-9 text-sm text-gray-800 placeholder:text-gray-400 {{ $error ? 'border-red-500' : 'border-primary/20 focus:border-primary' }}">
        </div>
    </div>

    {{-- Age · Horoscope · Age-group badges --}}
    <div x-show="age !== null" x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="mt-1.5 flex items-center gap-1.5 flex-wrap">

        {{-- Age --}}
        <span class="inline-flex items-center gap-1 text-xs font-medium rounded-full px-2.5 py-0.5"
              style="color: hsl(var(--primary) / 0.7); background: hsl(var(--primary) / 0.08);">
            <i class="bi bi-person-lines-fill" style="font-size:0.6rem;"></i>
            <span x-text="age + ' years old'"></span>
        </span>

        {{-- Horoscope --}}
        <span x-show="horoscope"
              class="inline-flex items-center gap-1 text-xs font-medium rounded-full px-2.5 py-0.5"
              style="color: #7c3aed; background: #f5f3ff;">
            <span x-text="horoscope ? horoscope.symbol : ''" style="font-size:0.75rem; line-height:1;"></span>
            <span x-text="horoscope ? horoscope.sign : ''"></span>
        </span>

        {{-- Age group --}}
        <span x-show="ageGroup"
              class="inline-flex items-center gap-1 text-xs font-medium rounded-full px-2.5 py-0.5"
              :style="ageGroup ? ageGroup.style : ''">
            <i class="bi bi-people-fill" style="font-size:0.6rem;"></i>
            <span x-text="ageGroup ? ageGroup.label : ''"></span>
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
            dayOpen: false, monthOpen: false,
            dayDropUp: false, monthDropUp: false,
            selectedDay: '{{ $selectedDay }}',
            selectedMonth: '{{ $selectedMonth }}',
            selectedMonthLabel: '',
            selectedYear: '{{ $selectedYear }}',
            hiddenValue: '{{ $value }}',
            age: null,
            horoscope: null,
            ageGroup: null,
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
            minYear: {{ $endYear }},
            maxYear: {{ $startYear }},

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
            selectDay(day) { this.selectedDay = day; this.dayOpen = false; this.updateValue(); },
            selectMonth(m) { this.selectedMonth = m.value; this.selectedMonthLabel = m.short; this.monthOpen = false; this.updateValue(); },
            onYearInput(e) {
                const cleaned = e.target.value.replace(/\D/g, '').slice(0, 4);
                this.selectedYear = cleaned;
                this.updateValue();
            },

            updateValue() {
                const yearValid = /^\d{4}$/.test(this.selectedYear) &&
                    parseInt(this.selectedYear) >= this.minYear &&
                    parseInt(this.selectedYear) <= this.maxYear;
                if (this.selectedDay && this.selectedMonth && yearValid) {
                    this.hiddenValue = `${this.selectedYear}-${this.selectedMonth}-${this.selectedDay}`;
                    const today = new Date();
                    const birth = new Date(parseInt(this.selectedYear), parseInt(this.selectedMonth) - 1, parseInt(this.selectedDay));
                    let a = today.getFullYear() - birth.getFullYear();
                    const monthDiff = today.getMonth() - birth.getMonth();
                    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birth.getDate())) a--;
                    this.age      = a >= 0 ? a : null;
                    this.horoscope = this.getHoroscope(parseInt(this.selectedMonth), parseInt(this.selectedDay));
                    this.ageGroup  = this.age !== null ? this.getAgeGroup(this.age) : null;
                } else {
                    this.hiddenValue = '';
                    this.age = null;
                    this.horoscope = null;
                    this.ageGroup  = null;
                }
            },

            getHoroscope(m, d) {
                if ((m === 3 && d >= 21) || (m === 4 && d <= 19))  return { sign: 'Aries',       symbol: '♈' };
                if ((m === 4 && d >= 20) || (m === 5 && d <= 20))  return { sign: 'Taurus',      symbol: '♉' };
                if ((m === 5 && d >= 21) || (m === 6 && d <= 20))  return { sign: 'Gemini',      symbol: '♊' };
                if ((m === 6 && d >= 21) || (m === 7 && d <= 22))  return { sign: 'Cancer',      symbol: '♋' };
                if ((m === 7 && d >= 23) || (m === 8 && d <= 22))  return { sign: 'Leo',         symbol: '♌' };
                if ((m === 8 && d >= 23) || (m === 9 && d <= 22))  return { sign: 'Virgo',       symbol: '♍' };
                if ((m === 9 && d >= 23) || (m === 10 && d <= 22)) return { sign: 'Libra',       symbol: '♎' };
                if ((m === 10 && d >= 23) || (m === 11 && d <= 21)) return { sign: 'Scorpio',    symbol: '♏' };
                if ((m === 11 && d >= 22) || (m === 12 && d <= 21)) return { sign: 'Sagittarius',symbol: '♐' };
                if ((m === 12 && d >= 22) || (m === 1 && d <= 19)) return { sign: 'Capricorn',   symbol: '♑' };
                if ((m === 1 && d >= 20) || (m === 2 && d <= 18))  return { sign: 'Aquarius',    symbol: '♒' };
                return                                                      { sign: 'Pisces',      symbol: '♓' };
            },

            getAgeGroup(age) {
                if (age <= 2)  return { label: 'Infant',      style: 'color:#0284c7;background:#f0f9ff;' };
                if (age <= 12) return { label: 'Child',       style: 'color:#16a34a;background:#f0fdf4;' };
                if (age <= 17) return { label: 'Teenager',    style: 'color:#ca8a04;background:#fefce8;' };
                if (age <= 25) return { label: 'Young Adult', style: 'color:#9333ea;background:#faf5ff;' };
                if (age <= 45) return { label: 'Adult',       style: 'color:#4f46e5;background:#eef2ff;' };
                if (age <= 64) return { label: 'Middle Aged', style: 'color:#ea580c;background:#fff7ed;' };
                return                { label: 'Senior',      style: 'color:#dc2626;background:#fef2f2;' };
            }
        }
    }
</script>
