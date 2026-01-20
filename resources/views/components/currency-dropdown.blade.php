@props(['name' => 'currency', 'id' => 'currency', 'value' => '', 'required' => false, 'error' => null])

<div class="mb-3">
    <label for="{{ $id }}" class="form-label">Currency</label>
    <select id="{{ $id }}"
            class="form-select currency-select @error($name) is-invalid @enderror"
            name="{{ $name }}"
            {{ $required ? 'required' : '' }}>
        <option value="">Select Currency</option>
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

                    // Get unique currencies
                    const uniqueCurrencies = {};
                    countries.forEach(country => {
                        if (!uniqueCurrencies[country.currency]) {
                            uniqueCurrencies[country.currency] = {
                                currency: country.currency,
                                currency_symbol: country.currency_symbol,
                                flag: country.flag,
                                name: country.name
                            };
                        }
                    }
                    });

                    // Populate dropdown
                    Object.values(uniqueCurrencies).forEach(currencyData => {
                        const option = document.createElement('option');
                        option.value = currencyData.currency;
                        option.textContent = `${currencyData.name} (${currencyData.currency} ${currencyData.currency_symbol})`;
                        option.setAttribute('data-flag', currencyData.flag);
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
        .currency-select {
            background-size: 20px 15px;
        }
    </style>
    @endpush
@endonce
