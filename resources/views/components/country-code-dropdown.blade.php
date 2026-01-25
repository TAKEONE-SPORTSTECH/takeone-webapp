@props(['name' => 'country_code', 'id' => 'country_code', 'value' => '+1', 'required' => false, 'error' => null])

<div class="input-group" onclick="event.stopPropagation()">
    <button class="btn btn-outline-secondary dropdown-toggle country-dropdown-btn d-flex align-items-center"
            type="button"
            id="{{ $id }}Dropdown"
            data-bs-toggle="dropdown"
            data-bs-auto-close="outside"
            aria-expanded="false">
        <span id="{{ $id }}SelectedFlag">🇺🇸</span>
        <span class="country-label" id="{{ $id }}SelectedCountry">{{ $value }}</span>
    </button>

    <div class="dropdown-menu p-2" aria-labelledby="{{ $id }}Dropdown" style="min-width: 200px;" onclick="event.stopPropagation()">
        <input type="text"
               class="form-control form-control-sm mb-2"
               placeholder="Search country..."
               id="{{ $id }}Search"
               onmousedown="event.stopPropagation()"
               onfocus="event.stopPropagation()"
               oninput="event.stopPropagation()"
               onkeydown="event.stopPropagation()"
               onkeyup="event.stopPropagation()">

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
                { code: '+970', name: 'Palestine', flag: '🇵🇸' },
                { code: '+972', name: 'Palestine', flag: '🇵🇸' }
            ];

            // Function to get user's country based on GPS
            function detectUserCountry(callback) {
                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(function(position) {
                        const lat = position.coords.latitude;
                        const lon = position.coords.longitude;
                        // Use a reverse geocoding API
                        fetch(`https://api.bigdatacloud.net/data/reverse-geocode-client?latitude=${lat}&longitude=${lon}&localityLanguage=en`)
                            .then(response => response.json())
                            .then(data => {
                                const countryName = data.countryName;
                                const iso2 = data.countryCode;
                                // Find the country code based on country name or iso2
                                let defaultCode = '+1'; // Default to US
                                for (let country of countries) {
                                    if (country.name.toLowerCase().includes(countryName.toLowerCase()) || country.name.toLowerCase().includes(iso2.toLowerCase())) {
                                        defaultCode = country.code;
                                        break;
                                    }
                                }
                                callback(defaultCode);
                            })
                            .catch(() => {
                                callback('+1'); // Default if error
                            });
                    }, function() {
                        callback('+1'); // Default if geolocation denied
                    });
                } else {
                    callback('+1'); // Default if no geolocation
                }
            }

            // Initialize only country code dropdowns (not nationality dropdowns)
            document.querySelectorAll('[id$="country_codeList"]').forEach(function(listElement) {
                const componentId = listElement.id.replace('List', '');
                initializeCountryDropdown(componentId, countries);
            });

            function initializeCountryDropdown(componentId, countries) {
                const countryList = document.getElementById(componentId + 'List');
                if (!countryList) return;

                // Clear existing items
                countryList.innerHTML = '';

                // Populate country dropdown
                countries.forEach(country => {
                        const button = document.createElement('button');
                        button.className = 'dropdown-item d-flex align-items-center';
                        button.type = 'button';
                        button.setAttribute('data-country-code', country.code);
                        button.setAttribute('data-country-name', country.name);
                        button.setAttribute('data-flag', country.flag);
                        button.setAttribute('data-search', country.name.toLowerCase() + ' ' + country.code.toLowerCase());
                        button.innerHTML = `
                            <span class="me-2">${country.flag}</span>
                            <span>${country.name} (${country.code})</span>
                        `;
                        button.addEventListener('click', function() {
                            selectCountry(componentId, country.code, country.name, country.flag);
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
                            const searchText = item.getAttribute('data-search') || '';
                            if (searchText.includes(searchTerm)) {
                                item.classList.remove('d-none');
                            } else {
                                item.classList.add('d-none');
                            }
                        });
                    });
                }

                // Detect user's country and set as default
                detectUserCountry(function(defaultCode) {
                    const hiddenInput = document.getElementById(componentId);
                    if (hiddenInput) {
                        hiddenInput.value = defaultCode;
                    }
                    const initialCountry = countries.find(c => c.code === defaultCode);
                    if (initialCountry) {
                        selectCountry(componentId, initialCountry.code, initialCountry.name, initialCountry.flag);
                    }
                });
            }

            function selectCountry(componentId, code, name, flag) {
                const flagElement = document.getElementById(componentId + 'SelectedFlag');
                const countryElement = document.getElementById(componentId + 'SelectedCountry');
                const hiddenInput = document.getElementById(componentId);

                if (flagElement) flagElement.textContent = flag;
                if (countryElement) countryElement.textContent = `${name} (${code})`;
                if (hiddenInput) hiddenInput.value = code;

                // Close the dropdown after selection
                const dropdownButton = document.getElementById(componentId + 'Dropdown');
                if (dropdownButton) {
                    const dropdown = bootstrap.Dropdown.getInstance(dropdownButton);
                    if (dropdown) dropdown.hide();
                }
            }
        });
    </script>
    @endpush
@endonce
