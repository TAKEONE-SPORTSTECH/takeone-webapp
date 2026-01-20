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
@endonce
