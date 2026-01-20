@props(['name' => 'timezone', 'id' => 'timezone', 'value' => '', 'required' => false, 'error' => null])

<div class="mb-3">
    <label for="{{ $id }}" class="form-label">Timezone</label>
    <select id="{{ $id }}"
            class="form-select timezone-select @error($name) is-invalid @enderror"
            name="{{ $name }}"
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
                    const selectElement = document.getElementById('{{ $id }}');
                    if (!selectElement) return;

                    // Get unique timezones
                    const uniqueTimezones = {};
                    countries.forEach(country => {
                        if (!uniqueTimezones[country.timezone]) {
                            uniqueTimezones[country.timezone] = {
                                timezone: country.timezone,
                                flag: country.flag,
                                name: country.name
                            };
                        }
                    }
                    });

                    // Populate dropdown
                    Object.values(uniqueTimezones).forEach(timezoneData => {
                        const option = document.createElement('option');
                        option.value = timezoneData.timezone;
                        option.textContent = `${timezoneData.name} (${timezoneData.timezone})`;
                        option.setAttribute('data-flag', timezoneData.flag);
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
                    }
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
