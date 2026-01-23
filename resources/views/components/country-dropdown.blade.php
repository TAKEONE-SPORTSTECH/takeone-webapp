@props(['name' => 'country', 'id' => 'country', 'value' => '', 'required' => false, 'error' => null, 'label' => 'Nationality'])

<div class="mb-3">
    <label for="{{ $id }}" class="form-label">{{ $label }}</label>
    <select id="{{ $id }}"
            class="form-select country-select @error($name) is-invalid @enderror"
            name="{{ $name }}"
            {{ $required ? 'required' : '' }}
            style="width: 100%;">
        <option value="">Select {{ $label }}</option>
    </select>
    @if($error)
        <span class="invalid-feedback" role="alert">
            <strong>{{ $error }}</strong>
        </span>
    @endif
</div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectElement = document.getElementById('{{ $id }}');
            if (!selectElement) return;

            // Check if Select2 is already initialized
            if ($(selectElement).hasClass('select2-hidden-accessible')) {
                return;
            }

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
                                callback(countryName, iso2);
                            })
                            .catch(() => {
                                callback('United States', 'US'); // Default if error
                            });
                    }, function() {
                        callback('United States', 'US'); // Default if geolocation denied
                    });
                } else {
                    callback('United States', 'US'); // Default if no geolocation
                }
            }

            // Load countries from JSON file
            fetch('/data/countries.json')
                .then(response => response.json())
                .then(countries => {
                    // Clear existing options except the first one
                    while (selectElement.options.length > 1) {
                        selectElement.remove(1);
                    }

                    // Populate dropdown
                    countries.forEach(country => {
                        const option = document.createElement('option');
                        option.value = country.iso3;
                        option.textContent = country.name;
                        option.setAttribute('data-flag', country.flag);
                        selectElement.appendChild(option);
                    });

                    // Set initial value if provided
                    const initialValue = '{{ $value }}';
                    if (initialValue) {
                        selectElement.value = initialValue;
                    }

                    // Initialize Select2 for searchable dropdown
                    if (typeof $ !== 'undefined' && $.fn.select2) {
                        $(selectElement).select2({
                            templateResult: function(state) {
                                if (!state.id) {
                                    return state.text;
                                }
                                const option = $(state.element);
                                const flagCode = option.data('flag');
                                return $(`<span><span class="fi fi-${flagCode} me-2"></span>${state.text}</span>`);
                            },
                            templateSelection: function(state) {
                                if (!state.id) {
                                    return state.text;
                                }
                                const option = $(state.element);
                                const flagCode = option.data('flag');
                                return $(`<span><span class="fi fi-${flagCode} me-2"></span>${state.text}</span>`);
                            },
                            width: '100%'
                        });

                        // Detect user's country and set as default
                        detectUserCountry(function(countryName, iso2) {
                            // Find the country by name or iso2
                            let defaultIso3 = 'USA'; // Default to US
                            for (let country of countries) {
                                if (country.name.toLowerCase().includes(countryName.toLowerCase()) || country.iso2 === iso2) {
                                    defaultIso3 = country.iso3;
                                    break;
                                }
                            }
                            $(selectElement).val(defaultIso3).trigger('change');
                        });
                    }
                })
                .catch(error => console.error('Error loading countries:', error));
        });
    </script>
    @endpush

    @push('styles')
    <style>
        .country-select {
            background-size: 20px 15px;
        }
    </style>
    @endpush
