@props(['name' => 'country_code', 'id' => 'country_code', 'value' => '+1', 'required' => false, 'error' => null])

<div class="input-group">
    <button class="btn btn-outline-secondary dropdown-toggle country-dropdown-btn d-flex align-items-center"
            type="button"
            id="{{ $id }}Dropdown"
            data-bs-toggle="dropdown"
            aria-expanded="false">
        <span class="fi fi-us me-2" id="{{ $id }}SelectedFlag"></span>
        <span class="country-label" id="{{ $id }}SelectedCountry">{{ $value }}</span>
    </button>

    <div class="dropdown-menu p-2" aria-labelledby="{{ $id }}Dropdown" style="min-width: 300px;">
        <input type="text"
               class="form-control form-control-sm mb-2"
               placeholder="Search country..."
               id="{{ $id }}Search">

        <div class="country-list" id="{{ $id }}List" style="max-height: 300px; overflow-y: auto;">
            <!-- Countries will be populated by JavaScript -->
        </div>
    </div>

    <input type="hidden" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" {{ $required ? 'required' : '' }}>

    {{ $slot }}
</div>

@if($error)
    <span class="invalid-feedback d-block" role="alert">
        <strong>{{ $error }}</strong>
    </span>
@endif

@once
    @push('styles')
    <style>
        .country-dropdown-btn {
            min-width: 150px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .country-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .dropdown-item {
            cursor: pointer;
        }

        .dropdown-item:hover {
            background-color: #f8f9fa;
        }
    </style>
    @endpush

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Country data with flags and codes
            const countries = [
                { code: '+1', name: 'United States', flagCode: 'us' },
                { code: '+1', name: 'Canada', flagCode: 'ca' },
                { code: '+44', name: 'United Kingdom', flagCode: 'gb' },
                { code: '+971', name: 'United Arab Emirates', flagCode: 'ae' },
                { code: '+966', name: 'Saudi Arabia', flagCode: 'sa' },
                { code: '+974', name: 'Qatar', flagCode: 'qa' },
                { code: '+965', name: 'Kuwait', flagCode: 'kw' },
                { code: '+973', name: 'Bahrain', flagCode: 'bh' },
                { code: '+968', name: 'Oman', flagCode: 'om' },
                { code: '+20', name: 'Egypt', flagCode: 'eg' },
                { code: '+91', name: 'India', flagCode: 'in' },
                { code: '+92', name: 'Pakistan', flagCode: 'pk' },
                { code: '+880', name: 'Bangladesh', flagCode: 'bd' },
                { code: '+60', name: 'Malaysia', flagCode: 'my' },
                { code: '+65', name: 'Singapore', flagCode: 'sg' },
                { code: '+81', name: 'Japan', flagCode: 'jp' },
                { code: '+86', name: 'China', flagCode: 'cn' },
                { code: '+82', name: 'South Korea', flagCode: 'kr' },
                { code: '+61', name: 'Australia', flagCode: 'au' },
                { code: '+49', name: 'Germany', flagCode: 'de' },
                { code: '+33', name: 'France', flagCode: 'fr' },
                { code: '+39', name: 'Italy', flagCode: 'it' },
                { code: '+34', name: 'Spain', flagCode: 'es' },
                { code: '+31', name: 'Netherlands', flagCode: 'nl' },
                { code: '+46', name: 'Sweden', flagCode: 'se' },
                { code: '+47', name: 'Norway', flagCode: 'no' },
                { code: '+45', name: 'Denmark', flagCode: 'dk' },
                { code: '+358', name: 'Finland', flagCode: 'fi' },
                { code: '+41', name: 'Switzerland', flagCode: 'ch' },
                { code: '+43', name: 'Austria', flagCode: 'at' },
                { code: '+48', name: 'Poland', flagCode: 'pl' },
                { code: '+420', name: 'Czech Republic', flagCode: 'cz' },
                { code: '+36', name: 'Hungary', flagCode: 'hu' },
                { code: '+40', name: 'Romania', flagCode: 'ro' },
                { code: '+30', name: 'Greece', flagCode: 'gr' },
                { code: '+90', name: 'Turkey', flagCode: 'tr' },
                { code: '+7', name: 'Russia', flagCode: 'ru' },
                { code: '+55', name: 'Brazil', flagCode: 'br' },
                { code: '+52', name: 'Mexico', flagCode: 'mx' },
                { code: '+54', name: 'Argentina', flagCode: 'ar' },
                { code: '+56', name: 'Chile', flagCode: 'cl' },
                { code: '+57', name: 'Colombia', flagCode: 'co' },
                { code: '+27', name: 'South Africa', flagCode: 'za' },
                { code: '+234', name: 'Nigeria', flagCode: 'ng' },
                { code: '+254', name: 'Kenya', flagCode: 'ke' },
                { code: '+94', name: 'Sri Lanka', flagCode: 'lk' },
                { code: '+84', name: 'Vietnam', flagCode: 'vn' },
                { code: '+66', name: 'Thailand', flagCode: 'th' },
                { code: '+62', name: 'Indonesia', flagCode: 'id' },
                { code: '+63', name: 'Philippines', flagCode: 'ph' },
                { code: '+64', name: 'New Zealand', flagCode: 'nz' },
                { code: '+351', name: 'Portugal', flagCode: 'pt' },
                { code: '+353', name: 'Ireland', flagCode: 'ie' },
                { code: '+972', name: 'Israel', flagCode: 'il' },
                { code: '+962', name: 'Jordan', flagCode: 'jo' },
                { code: '+961', name: 'Lebanon', flagCode: 'lb' },
                { code: '+964', name: 'Iraq', flagCode: 'iq' },
            ];

            // Initialize all country code dropdowns on the page
            document.querySelectorAll('[id$="List"]').forEach(function(listElement) {
                const componentId = listElement.id.replace('List', '');
                initializeCountryDropdown(componentId, countries);
            });

            function initializeCountryDropdown(componentId, countries) {
                const countryList = document.getElementById(componentId + 'List');
                if (!countryList) return;

                // Populate country dropdown
                countries.forEach(country => {
                    const button = document.createElement('button');
                    button.className = 'dropdown-item d-flex align-items-center';
                    button.type = 'button';
                    button.setAttribute('data-country-code', country.code);
                    button.setAttribute('data-country-name', country.name);
                    button.setAttribute('data-flag-code', country.flagCode);
                    button.innerHTML = `
                        <span class="fi fi-${country.flagCode} me-2"></span>
                        <span>${country.name} (${country.code})</span>
                    `;
                    button.addEventListener('click', function() {
                        selectCountry(componentId, country.code, country.name, country.flagCode);
                    });
                    countryList.appendChild(button);
                });

                // Search functionality
                const searchInput = document.getElementById(componentId + 'Search');
                if (searchInput) {
                    searchInput.addEventListener('input', function(e) {
                        const searchTerm = e.target.value.toLowerCase();
                        const items = countryList.querySelectorAll('.dropdown-item');
                        items.forEach(item => {
                            const text = item.textContent.toLowerCase();
                            if (text.includes(searchTerm)) {
                                item.style.display = '';
                            } else {
                                item.style.display = 'none';
                            }
                        });
                    });
                }

                // Set initial value if provided
                const hiddenInput = document.getElementById(componentId);
                if (hiddenInput && hiddenInput.value) {
                    const initialCountry = countries.find(c => c.code === hiddenInput.value);
                    if (initialCountry) {
                        selectCountry(componentId, initialCountry.code, initialCountry.name, initialCountry.flagCode);
                    }
                }
            }

            function selectCountry(componentId, code, name, flagCode) {
                const flagElement = document.getElementById(componentId + 'SelectedFlag');
                const countryElement = document.getElementById(componentId + 'SelectedCountry');
                const hiddenInput = document.getElementById(componentId);

                if (flagElement) flagElement.className = `fi fi-${flagCode} me-2`;
                if (countryElement) countryElement.textContent = code;
                if (hiddenInput) hiddenInput.value = code;
            }
        });
    </script>
    @endpush
@endonce
