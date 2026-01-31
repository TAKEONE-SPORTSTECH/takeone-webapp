@props(['name' => 'timezone', 'id' => 'timezone', 'value' => '', 'required' => false, 'error' => null])

<div class="mb-3">
    <label for="{{ $id }}" class="form-label">Timezone</label>
    <select id="{{ $id }}"
            class="form-select timezone-dropdown-select @error($name) is-invalid @enderror"
            name="{{ $name }}"
            data-component-id="{{ $id }}"
            data-initial-value="{{ $value }}"
            {{ $required ? 'required' : '' }}>
        <option value="">Select Timezone</option>
    </select>
    @if($error)
        <span class="invalid-feedback" role="alert">
            <strong>{{ $error }}</strong>
        </span>
    @endif
</div>

@once
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Load countries from JSON file
            fetch('/data/countries.json')
                .then(response => response.json())
                .then(countries => {
                    // Initialize all timezone dropdowns
                    document.querySelectorAll('.timezone-dropdown-select').forEach(function(selectElement) {
                        const initialValue = selectElement.getAttribute('data-initial-value');

                        // Get unique timezones
                        const uniqueTimezones = {};
                        countries.forEach(country => {
                            if (country.timezone && !uniqueTimezones[country.timezone]) {
                                uniqueTimezones[country.timezone] = {
                                    timezone: country.timezone,
                                    flag: country.flag,
                                    name: country.name
                                };
                            }
                        });

                        // Populate dropdown with country name and timezone
                        Object.values(uniqueTimezones).forEach(timezoneData => {
                            const option = document.createElement('option');
                            option.value = timezoneData.timezone;
                            option.textContent = `${timezoneData.timezone}`;
                            option.setAttribute('data-flag', timezoneData.flag);
                            option.setAttribute('data-country', timezoneData.name);
                            selectElement.appendChild(option);
                        });

                        // Set initial value if provided
                        if (initialValue) {
                            selectElement.value = initialValue;
                        }

                        // Initialize Select2 for searchable dropdown with flag emojis
                        if (typeof $ !== 'undefined' && $.fn.select2) {
                            $(selectElement).select2({
                                templateResult: function(state) {
                                    if (!state.id) {
                                        return state.text;
                                    }
                                    const option = $(state.element);
                                    const flagCode = option.data('flag');
                                    // Convert ISO2 code to flag emoji
                                    const flagEmoji = flagCode ? String.fromCodePoint(...[...flagCode.toUpperCase()].map(c => 127397 + c.charCodeAt())) : '';
                                    return $(`<span>${flagEmoji} ${state.text}</span>`);
                                },
                                templateSelection: function(state) {
                                    if (!state.id) {
                                        return state.text;
                                    }
                                    const option = $(state.element);
                                    const flagCode = option.data('flag');
                                    // Convert ISO2 code to flag emoji
                                    const flagEmoji = flagCode ? String.fromCodePoint(...[...flagCode.toUpperCase()].map(c => 127397 + c.charCodeAt())) : '';
                                    return $(`<span>${flagEmoji} ${state.text}</span>`);
                                },
                                width: '100%'
                            });
                        }
                    });
                })
                .catch(error => console.error('Error loading countries:', error));
        });
    </script>
    @endpush

    @push('styles')
    <style>
        .timezone-select {
            background-size: 20px 15px;
        }
    </style>
    @endpush
@endonce
