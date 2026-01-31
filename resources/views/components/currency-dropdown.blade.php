@props(['name' => 'currency', 'id' => 'currency', 'value' => '', 'required' => false, 'error' => null])

<div class="mb-3">
    <label for="{{ $id }}" class="form-label">Currency</label>
    <select id="{{ $id }}"
            class="form-select currency-dropdown-select @error($name) is-invalid @enderror"
            name="{{ $name }}"
            data-component-id="{{ $id }}"
            data-initial-value="{{ $value }}"
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
                    // Initialize all currency dropdowns
                    document.querySelectorAll('.currency-dropdown-select').forEach(function(selectElement) {
                        const initialValue = selectElement.getAttribute('data-initial-value');

                        // Get unique currencies
                        const uniqueCurrencies = {};
                        countries.forEach(country => {
                            if (country.currency && !uniqueCurrencies[country.currency]) {
                                uniqueCurrencies[country.currency] = {
                                    currency: country.currency,
                                    currency_symbol: country.currency_symbol || '',
                                    flag: country.flag,
                                    name: country.name
                                };
                            }
                        });

                        // Populate dropdown with enhanced format: Flag + Country Name – Currency Code
                        Object.values(uniqueCurrencies).forEach(currencyData => {
                            const option = document.createElement('option');
                            option.value = currencyData.currency;
                            // Format: "Bahrain – BHD"
                            option.textContent = `${currencyData.name} – ${currencyData.currency}`;
                            option.setAttribute('data-flag', currencyData.flag);
                            option.setAttribute('data-country', currencyData.name);
                            selectElement.appendChild(option);
                        });

                        // Set initial value if provided
                        if (initialValue) {
                            selectElement.value = initialValue;
                        }

                        // Initialize Select2 for searchable dropdown with flags
                        if (typeof $ !== 'undefined' && $.fn.select2) {
                            $(selectElement).select2({
                                templateResult: function(state) {
                                    if (!state.id) {
                                        return state.text;
                                    }
                                    const option = $(state.element);
                                    const flagCode = option.data('flag');
                                    // Show flag emoji + text
                                    const flagEmoji = flagCode ? String.fromCodePoint(...[...flagCode.toUpperCase()].map(c => 127397 + c.charCodeAt())) : '';
                                    return $(`<span>${flagEmoji} ${state.text}</span>`);
                                },
                                templateSelection: function(state) {
                                    if (!state.id) {
                                        return state.text;
                                    }
                                    const option = $(state.element);
                                    const flagCode = option.data('flag');
                                    // Show flag emoji + text
                                    const flagEmoji = flagCode ? String.fromCodePoint(...[...flagCode.toUpperCase()].map(c => 127397 + c.charCodeAt())) : '';
                                    return $(`<span>${flagEmoji} ${state.text}</span>`);
                                },
                                width: '100%',
                                // Enable search by country name or currency code
                                matcher: function(params, data) {
                                    if ($.trim(params.term) === '') {
                                        return data;
                                    }
                                    const term = params.term.toLowerCase();
                                    const text = data.text.toLowerCase();
                                    const country = $(data.element).data('country');

                                    if (text.indexOf(term) > -1 || (country && country.toLowerCase().indexOf(term) > -1)) {
                                        return data;
                                    }
                                    return null;
                                }
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
        .currency-select {
            background-size: 20px 15px;
        }
    </style>
    @endpush
@endonce
