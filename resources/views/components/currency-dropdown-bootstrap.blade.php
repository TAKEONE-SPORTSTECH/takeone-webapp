@props(['name' => 'currency', 'id' => 'currency', 'value' => '', 'required' => false, 'error' => null, 'label' => 'Currency'])

<div class="mb-3">
    <label for="{{ $id }}" class="form-label">{{ $label }}</label>
    <div class="dropdown w-100" onclick="event.stopPropagation()">
        <button class="form-select dropdown-toggle d-flex align-items-center justify-content-between @error($name) is-invalid @enderror"
                type="button"
                id="{{ $id }}Dropdown"
                data-bs-toggle="dropdown"
                data-bs-auto-close="outside"
                aria-expanded="false"
                style="text-align: left; background-color: rgba(255,255,255,0.8);">
            <span class="d-flex align-items-center">
                <span id="{{ $id }}SelectedFlag"></span>
                <span class="currency-label" id="{{ $id }}SelectedCurrency">Select Currency</span>
            </span>
        </button>

        <div class="dropdown-menu p-2 w-100" aria-labelledby="{{ $id }}Dropdown" onclick="event.stopPropagation()">
            <input type="text"
                   class="form-control form-control-sm mb-2"
                   placeholder="Search currency..."
                   id="{{ $id }}Search"
                   onmousedown="event.stopPropagation()"
                   onfocus="event.stopPropagation()"
                   oninput="event.stopPropagation()"
                   onkeydown="event.stopPropagation()"
                   onkeyup="event.stopPropagation()">

            <div class="currency-list" id="{{ $id }}List" data-component-id="{{ $id }}" style="max-height: 300px; overflow-y: auto;">
                <!-- Currencies will be populated by JavaScript -->
            </div>
        </div>

        <input type="hidden" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" {{ $required ? 'required' : '' }}>
    </div>

    @if($error)
        <span class="invalid-feedback d-block" role="alert">
            <strong>{{ $error }}</strong>
        </span>
    @endif
</div>

@once
    @push('styles')
    <style>
        .currency-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .currency-list .dropdown-item {
            cursor: pointer;
        }

        .currency-list .dropdown-item:hover {
            background-color: #f8f9fa;
        }
    </style>
    @endpush

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Load countries from JSON file
            fetch('/data/countries.json')
                .then(response => response.json())
                .then(countries => {
                    // Initialize all currency dropdowns
                    document.querySelectorAll('.currency-list').forEach(function(listElement) {
                        const componentId = listElement.getAttribute('data-component-id');
                        initializeCurrencyDropdown(componentId, countries);
                    });
                })
                .catch(error => console.error('Error loading countries:', error));

            function initializeCurrencyDropdown(componentId, countries) {
                const currencyList = document.getElementById(componentId + 'List');
                if (!currencyList) return;

                // Clear existing items
                currencyList.innerHTML = '';

                // Get unique currencies with their associated countries
                const currencyMap = {};
                countries.forEach(country => {
                    if (country.currency && !currencyMap[country.currency]) {
                        currencyMap[country.currency] = {
                            currency: country.currency,
                            flag: country.iso2,
                            countryName: country.name
                        };
                    }
                });

                // Populate currency dropdown
                Object.values(currencyMap).forEach(currData => {
                    const button = document.createElement('button');
                    button.className = 'dropdown-item d-flex align-items-center';
                    button.type = 'button';
                    button.setAttribute('data-currency-code', currData.currency);
                    button.setAttribute('data-country-name', currData.countryName);
                    button.setAttribute('data-flag', currData.flag);
                    button.setAttribute('data-search', `${currData.countryName.toLowerCase()} ${currData.currency.toLowerCase()}`);

                    // Convert flag code to emoji
                    const flagEmoji = currData.flag
                        .toUpperCase()
                        .split('')
                        .map(char => String.fromCodePoint(127397 + char.charCodeAt(0)))
                        .join('');

                    // Format: "🇧🇭 Bahrain – BHD"
                    button.innerHTML = `
                        <span class="me-2">${flagEmoji}</span>
                        <span>${currData.countryName} – ${currData.currency}</span>
                    `;
                    button.addEventListener('click', function() {
                        selectCurrency(componentId, currData.currency, flagEmoji, currData.countryName);
                    });
                    currencyList.appendChild(button);
                });

                // Search functionality
                const searchInput = document.getElementById(componentId + 'Search');
                if (searchInput) {
                    searchInput.addEventListener('input', function(e) {
                        const searchTerm = e.target.value.toLowerCase();
                        const items = currencyList.querySelectorAll('.dropdown-item');
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

                // Set initial value if provided
                const hiddenInput = document.getElementById(componentId);
                if (hiddenInput && hiddenInput.value) {
                    const initialCurrency = Object.values(currencyMap).find(curr => curr.currency === hiddenInput.value);
                    if (initialCurrency) {
                        const flagEmoji = initialCurrency.flag
                            .toUpperCase()
                            .split('')
                            .map(char => String.fromCodePoint(127397 + char.charCodeAt(0)))
                            .join('');
                        selectCurrency(componentId, initialCurrency.currency, flagEmoji, initialCurrency.countryName);
                    }
                }
            }

            function selectCurrency(componentId, currency, flag, countryName) {
                const flagElement = document.getElementById(componentId + 'SelectedFlag');
                const currencyElement = document.getElementById(componentId + 'SelectedCurrency');
                const hiddenInput = document.getElementById(componentId);

                if (flagElement) flagElement.textContent = flag + ' ';
                if (currencyElement) currencyElement.textContent = `${countryName} – ${currency}`;
                if (hiddenInput) {
                    hiddenInput.value = currency;
                    // Trigger change event for other handlers
                    hiddenInput.dispatchEvent(new Event('change'));
                }

                // Close the dropdown after selection
                const dropdownButton = document.getElementById(componentId + 'Dropdown');
                if (dropdownButton) {
                    const dropdown = bootstrap.Dropdown.getInstance(dropdownButton);
                    if (dropdown) dropdown.hide();
                }
            }

            // Expose function globally for external use
            window.setCurrencyValue = function(componentId, currency, countries) {
                const currencyMap = {};
                countries.forEach(country => {
                    if (country.currency && !currencyMap[country.currency]) {
                        currencyMap[country.currency] = {
                            currency: country.currency,
                            flag: country.iso2,
                            countryName: country.name
                        };
                    }
                });

                const currData = currencyMap[currency];
                if (currData) {
                    const flagEmoji = currData.flag
                        .toUpperCase()
                        .split('')
                        .map(char => String.fromCodePoint(127397 + char.charCodeAt(0)))
                        .join('');
                    selectCurrency(componentId, currency, flagEmoji, currData.countryName);
                }
            };
        });
    </script>
    @endpush
@endonce
