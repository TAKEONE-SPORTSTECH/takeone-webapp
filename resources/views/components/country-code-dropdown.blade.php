@props(['name' => 'country_code', 'id' => 'country_code', 'value' => '+1', 'required' => false, 'error' => null])

<div class="tf-input-group"
     x-data="countryCodeDropdown_{{ $id }}()"
     x-init="init()">
    <!-- Country Code Button -->
    <div class="relative">
        <button type="button"
                @click="toggle()"
                @click.away="open = false"
                x-ref="trigger"
                class="h-full px-3 py-3 flex items-center gap-2 border-r border-primary/20 bg-transparent hover:bg-gray-50 transition-colors cursor-pointer rounded-l-xl"
                id="{{ $id }}Dropdown">
            <span :class="'fi fi-' + selectedFlag"></span>
            <span x-text="selectedCode" class="text-sm font-medium text-gray-700">{{ $value }}</span>
            <i class="bi bi-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
        </button>

        <!-- Dropdown Menu -->
        <div x-show="open" x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             :class="dropUp ? 'bottom-full mb-1' : 'top-full mt-1'"
             class="absolute left-0 z-50 w-64 bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden">
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
                         class="tf-dropdown-item-sm">
                        <span :class="'fi fi-' + country.flag" class="mr-2"></span>
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
    <span class="tf-error" role="alert">
        <strong>{{ $error }}</strong>
    </span>
@endif

<script>
    function countryCodeDropdown_{{ $id }}() {
        return {
            open: false,
            dropUp: false,
            search: '',
            countries: [
                { code: '+1', name: 'United States', flag: 'us' },
                { code: '+1', name: 'Canada', flag: 'ca' },
                { code: '+44', name: 'United Kingdom', flag: 'gb' },
                { code: '+971', name: 'United Arab Emirates', flag: 'ae' },
                { code: '+966', name: 'Saudi Arabia', flag: 'sa' },
                { code: '+974', name: 'Qatar', flag: 'qa' },
                { code: '+965', name: 'Kuwait', flag: 'kw' },
                { code: '+973', name: 'Bahrain', flag: 'bh' },
                { code: '+968', name: 'Oman', flag: 'om' },
                { code: '+20', name: 'Egypt', flag: 'eg' },
                { code: '+91', name: 'India', flag: 'in' },
                { code: '+92', name: 'Pakistan', flag: 'pk' },
                { code: '+880', name: 'Bangladesh', flag: 'bd' },
                { code: '+60', name: 'Malaysia', flag: 'my' },
                { code: '+65', name: 'Singapore', flag: 'sg' },
                { code: '+81', name: 'Japan', flag: 'jp' },
                { code: '+86', name: 'China', flag: 'cn' },
                { code: '+82', name: 'South Korea', flag: 'kr' },
                { code: '+61', name: 'Australia', flag: 'au' },
                { code: '+49', name: 'Germany', flag: 'de' },
                { code: '+33', name: 'France', flag: 'fr' },
                { code: '+39', name: 'Italy', flag: 'it' },
                { code: '+34', name: 'Spain', flag: 'es' },
                { code: '+31', name: 'Netherlands', flag: 'nl' },
                { code: '+46', name: 'Sweden', flag: 'se' },
                { code: '+47', name: 'Norway', flag: 'no' },
                { code: '+45', name: 'Denmark', flag: 'dk' },
                { code: '+358', name: 'Finland', flag: 'fi' },
                { code: '+41', name: 'Switzerland', flag: 'ch' },
                { code: '+43', name: 'Austria', flag: 'at' },
                { code: '+48', name: 'Poland', flag: 'pl' },
                { code: '+420', name: 'Czech Republic', flag: 'cz' },
                { code: '+36', name: 'Hungary', flag: 'hu' },
                { code: '+40', name: 'Romania', flag: 'ro' },
                { code: '+30', name: 'Greece', flag: 'gr' },
                { code: '+90', name: 'Turkey', flag: 'tr' },
                { code: '+98', name: 'Iran', flag: 'ir' },
                { code: '+7', name: 'Russia', flag: 'ru' },
                { code: '+55', name: 'Brazil', flag: 'br' },
                { code: '+52', name: 'Mexico', flag: 'mx' },
                { code: '+54', name: 'Argentina', flag: 'ar' },
                { code: '+56', name: 'Chile', flag: 'cl' },
                { code: '+57', name: 'Colombia', flag: 'co' },
                { code: '+27', name: 'South Africa', flag: 'za' },
                { code: '+234', name: 'Nigeria', flag: 'ng' },
                { code: '+254', name: 'Kenya', flag: 'ke' },
                { code: '+94', name: 'Sri Lanka', flag: 'lk' },
                { code: '+84', name: 'Vietnam', flag: 'vn' },
                { code: '+66', name: 'Thailand', flag: 'th' },
                { code: '+62', name: 'Indonesia', flag: 'id' },
                { code: '+63', name: 'Philippines', flag: 'ph' },
                { code: '+64', name: 'New Zealand', flag: 'nz' },
                { code: '+351', name: 'Portugal', flag: 'pt' },
                { code: '+353', name: 'Ireland', flag: 'ie' },
                { code: '+962', name: 'Jordan', flag: 'jo' },
                { code: '+961', name: 'Lebanon', flag: 'lb' },
                { code: '+964', name: 'Iraq', flag: 'iq' },
                { code: '+970', name: 'Palestine', flag: 'ps' }
            ],
            selectedCode: '{{ $value }}',
            selectedFlag: 'us',

            init() {
                // Find initial country by code
                const initialCountry = this.countries.find(c => c.code === this.selectedCode);
                if (initialCountry) {
                    this.selectedFlag = initialCountry.flag;
                }

                // Listen for country-changed events
                window.addEventListener('country-changed', (e) => {
                    const code = e.detail.call_code;
                    if (!code) return;
                    const match = this.countries.find(c => c.code === code);
                    if (match) {
                        this.selectedCode = match.code;
                        this.selectedFlag = match.flag;
                    }
                });
            },

            toggle() {
                if (!this.open) {
                    const rect = this.$refs.trigger.getBoundingClientRect();
                    const spaceBelow = window.innerHeight - rect.bottom;
                    this.dropUp = spaceBelow < 300;
                }
                this.open = !this.open;
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
