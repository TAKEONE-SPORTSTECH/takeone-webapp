@props(['name' => 'country_code', 'id' => 'country_code', 'value' => '+1', 'required' => false, 'error' => null])

<div class="flex border-2 border-primary/20 rounded-xl bg-white/80 shadow-inner transition-all duration-300 focus-within:border-primary focus-within:bg-white focus-within:ring-4 focus-within:ring-primary/10"
     x-data="countryCodeDropdown_{{ $id }}()"
     x-init="init()">
    <!-- Country Code Button -->
    <div class="relative">
        <button type="button"
                @click="open = !open"
                @click.away="open = false"
                class="h-full px-3 py-3 flex items-center gap-2 border-r border-primary/20 bg-transparent hover:bg-gray-50 transition-colors cursor-pointer rounded-l-xl"
                id="{{ $id }}Dropdown">
            <span x-text="selectedFlag">🇺🇸</span>
            <span x-text="selectedCode" class="text-sm font-medium text-gray-700">{{ $value }}</span>
            <i class="bi bi-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
        </button>

        <!-- Dropdown Menu -->
        <div x-show="open"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-1"
             class="absolute left-0 z-50 mt-1 w-64 bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden">
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
                <template x-for="country in filteredCountries" :key="country.name">
                    <div @click="selectCountry(country)"
                         class="px-4 py-2 hover:bg-primary hover:text-white cursor-pointer flex items-center transition-colors">
                        <span x-text="country.flag" class="mr-2"></span>
                        <span x-text="country.name + ' (' + country.code + ')'" class="text-sm"></span>
                    </div>
                </template>
                <div x-show="filteredCountries.length === 0" class="px-4 py-2 text-gray-500 text-sm">
                    No countries found
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" id="{{ $id }}" name="{{ $name }}" x-model="selectedCode" {{ $required ? 'required' : '' }}>

    <!-- Phone Number Input Slot -->
    <div class="flex-1">
        {{ $slot }}
    </div>
</div>

@if($error)
    <span class="text-red-500 text-sm mt-1 block" role="alert">
        <strong>{{ $error }}</strong>
    </span>
@endif

<script>
    function countryCodeDropdown_{{ $id }}() {
        return {
            open: false,
            search: '',
            countries: [
                { code: '+1', name: 'United States', flag: '🇺🇸' },
                { code: '+1', name: 'Canada', flag: '🇨🇦' },
                { code: '+44', name: 'United Kingdom', flag: '🇬🇧' },
                { code: '+971', name: 'United Arab Emirates', flag: '🇦🇪' },
                { code: '+966', name: 'Saudi Arabia', flag: '🇸🇦' },
                { code: '+974', name: 'Qatar', flag: '🇶🇦' },
                { code: '+965', name: 'Kuwait', flag: '🇰🇼' },
                { code: '+973', name: 'Bahrain', flag: '🇧🇭' },
                { code: '+968', name: 'Oman', flag: '🇴🇲' },
                { code: '+20', name: 'Egypt', flag: '🇪🇬' },
                { code: '+91', name: 'India', flag: '🇮🇳' },
                { code: '+92', name: 'Pakistan', flag: '🇵🇰' },
                { code: '+880', name: 'Bangladesh', flag: '🇧🇩' },
                { code: '+60', name: 'Malaysia', flag: '🇲🇾' },
                { code: '+65', name: 'Singapore', flag: '🇸🇬' },
                { code: '+81', name: 'Japan', flag: '🇯🇵' },
                { code: '+86', name: 'China', flag: '🇨🇳' },
                { code: '+82', name: 'South Korea', flag: '🇰🇷' },
                { code: '+61', name: 'Australia', flag: '🇦🇺' },
                { code: '+49', name: 'Germany', flag: '🇩🇪' },
                { code: '+33', name: 'France', flag: '🇫🇷' },
                { code: '+39', name: 'Italy', flag: '🇮🇹' },
                { code: '+34', name: 'Spain', flag: '🇪🇸' },
                { code: '+31', name: 'Netherlands', flag: '🇳🇱' },
                { code: '+46', name: 'Sweden', flag: '🇸🇪' },
                { code: '+47', name: 'Norway', flag: '🇳🇴' },
                { code: '+45', name: 'Denmark', flag: '🇩🇰' },
                { code: '+358', name: 'Finland', flag: '🇫🇮' },
                { code: '+41', name: 'Switzerland', flag: '🇨🇭' },
                { code: '+43', name: 'Austria', flag: '🇦🇹' },
                { code: '+48', name: 'Poland', flag: '🇵🇱' },
                { code: '+420', name: 'Czech Republic', flag: '🇨🇿' },
                { code: '+36', name: 'Hungary', flag: '🇭🇺' },
                { code: '+40', name: 'Romania', flag: '🇷🇴' },
                { code: '+30', name: 'Greece', flag: '🇬🇷' },
                { code: '+90', name: 'Turkey', flag: '🇹🇷' },
                { code: '+98', name: 'Iran', flag: '🇮🇷' },
                { code: '+7', name: 'Russia', flag: '🇷🇺' },
                { code: '+55', name: 'Brazil', flag: '🇧🇷' },
                { code: '+52', name: 'Mexico', flag: '🇲🇽' },
                { code: '+54', name: 'Argentina', flag: '🇦🇷' },
                { code: '+56', name: 'Chile', flag: '🇨🇱' },
                { code: '+57', name: 'Colombia', flag: '🇨🇴' },
                { code: '+27', name: 'South Africa', flag: '🇿🇦' },
                { code: '+234', name: 'Nigeria', flag: '🇳🇬' },
                { code: '+254', name: 'Kenya', flag: '🇰🇪' },
                { code: '+94', name: 'Sri Lanka', flag: '🇱🇰' },
                { code: '+84', name: 'Vietnam', flag: '🇻🇳' },
                { code: '+66', name: 'Thailand', flag: '🇹🇭' },
                { code: '+62', name: 'Indonesia', flag: '🇮🇩' },
                { code: '+63', name: 'Philippines', flag: '🇵🇭' },
                { code: '+64', name: 'New Zealand', flag: '🇳🇿' },
                { code: '+351', name: 'Portugal', flag: '🇵🇹' },
                { code: '+353', name: 'Ireland', flag: '🇮🇪' },
                { code: '+962', name: 'Jordan', flag: '🇯🇴' },
                { code: '+961', name: 'Lebanon', flag: '🇱🇧' },
                { code: '+964', name: 'Iraq', flag: '🇮🇶' },
                { code: '+970', name: 'Palestine', flag: '🇵🇸' }
            ],
            selectedCode: '{{ $value }}',
            selectedFlag: '🇺🇸',

            init() {
                // Find initial country by code
                const initialCountry = this.countries.find(c => c.code === this.selectedCode);
                if (initialCountry) {
                    this.selectedFlag = initialCountry.flag;
                }
            },

            get filteredCountries() {
                if (!this.search) return this.countries;
                const term = this.search.toLowerCase();
                return this.countries.filter(c =>
                    c.name.toLowerCase().includes(term) ||
                    c.code.includes(term)
                );
            },

            selectCountry(country) {
                this.selectedFlag = country.flag;
                this.selectedCode = country.code;
                this.open = false;
                this.search = '';
            }
        }
    }
</script>
