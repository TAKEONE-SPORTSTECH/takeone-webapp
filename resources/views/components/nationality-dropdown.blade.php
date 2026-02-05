@props(['name' => 'nationality', 'id' => 'nationality', 'value' => '', 'required' => false, 'error' => null, 'label' => 'Nationality'])

<div class="mb-4" x-data="nationalityDropdown_{{ $id }}()" x-init="init()">
    <label for="{{ $id }}" class="block text-sm font-medium text-gray-600 mb-1">
        {{ $label }}@if($required) <span class="text-red-500">*</span>@endif
    </label>
    <div class="relative">
        <button type="button"
                @click="open = !open"
                @click.away="open = false"
                class="w-full px-4 py-3 text-base text-left border-2 rounded-xl bg-white/80 shadow-inner transition-all duration-300 focus:bg-white focus:ring-4 focus:ring-primary/10 focus:outline-none cursor-pointer flex items-center justify-between {{ $error ? 'border-red-500' : 'border-primary/20 focus:border-primary' }}"
                id="{{ $id }}Dropdown">
            <span class="flex items-center">
                <span x-text="selectedFlag" class="mr-2"></span>
                <span x-text="selectedCountry">Select Nationality</span>
            </span>
            <i class="bi bi-chevron-down transition-transform" :class="{ 'rotate-180': open }"></i>
        </button>

        <!-- Dropdown Menu -->
        <div x-show="open"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-1"
             class="absolute z-50 w-full mt-1 bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden">
            <!-- Search Input -->
            <div class="p-2 border-b border-gray-100">
                <input type="text"
                       x-model="search"
                       @click.stop
                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:border-primary focus:ring-2 focus:ring-primary/20 focus:outline-none"
                       placeholder="Search country...">
            </div>
            <!-- Country List -->
            <div class="max-h-60 overflow-y-auto">
                <template x-for="country in filteredCountries" :key="country.iso3">
                    <div @click="selectCountry(country)"
                         class="px-4 py-2 hover:bg-primary hover:text-white cursor-pointer flex items-center transition-colors">
                        <span x-text="country.flag" class="mr-2"></span>
                        <span x-text="country.name"></span>
                    </div>
                </template>
                <div x-show="filteredCountries.length === 0" class="px-4 py-2 text-gray-500 text-sm">
                    No countries found
                </div>
            </div>
        </div>

        <input type="hidden" id="{{ $id }}" name="{{ $name }}" x-model="selectedValue" {{ $required ? 'required' : '' }}>
    </div>

    @if($error)
        <span class="text-red-500 text-sm mt-1 block" role="alert">
            <strong>{{ $error }}</strong>
        </span>
    @endif
</div>

@once
@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        // Store countries data globally to avoid multiple fetches
        if (!window.countriesData) {
            window.countriesData = null;
            window.countriesPromise = fetch('/data/countries.json')
                .then(response => response.json())
                .then(data => {
                    window.countriesData = data.map(country => ({
                        ...country,
                        flag: country.iso2
                            .toUpperCase()
                            .split('')
                            .map(char => String.fromCodePoint(127397 + char.charCodeAt(0)))
                            .join('')
                    }));
                    return window.countriesData;
                });
        }
    });
</script>
@endpush
@endonce

<script>
    function nationalityDropdown_{{ $id }}() {
        return {
            open: false,
            search: '',
            countries: [],
            selectedValue: '{{ $value }}',
            selectedFlag: '',
            selectedCountry: 'Select Nationality',

            async init() {
                // Wait for countries data
                if (window.countriesData) {
                    this.countries = window.countriesData;
                } else if (window.countriesPromise) {
                    this.countries = await window.countriesPromise;
                } else {
                    const response = await fetch('/data/countries.json');
                    const data = await response.json();
                    this.countries = data.map(country => ({
                        ...country,
                        flag: country.iso2
                            .toUpperCase()
                            .split('')
                            .map(char => String.fromCodePoint(127397 + char.charCodeAt(0)))
                            .join('')
                    }));
                }

                // Set initial value if provided
                if (this.selectedValue) {
                    const country = this.countries.find(c => c.iso3 === this.selectedValue || c.name === this.selectedValue);
                    if (country) {
                        this.selectedFlag = country.flag;
                        this.selectedCountry = country.name;
                        this.selectedValue = country.iso3;
                    }
                }
            },

            get filteredCountries() {
                if (!this.search) return this.countries;
                const term = this.search.toLowerCase();
                return this.countries.filter(c => c.name.toLowerCase().includes(term));
            },

            selectCountry(country) {
                this.selectedFlag = country.flag;
                this.selectedCountry = country.name;
                this.selectedValue = country.iso3;
                this.open = false;
                this.search = '';
            }
        }
    }
</script>
