@props(['name' => 'nationality', 'id' => 'nationality', 'value' => '', 'required' => false, 'error' => null, 'label' => 'Nationality'])

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
            <span class="country-label" id="{{ $id }}SelectedCountry">Select Nationality</span>
        </span>
    </button>

    <div class="dropdown-menu p-2 w-100" aria-labelledby="{{ $id }}Dropdown" onclick="event.stopPropagation()">
        <input type="text"
               class="form-control form-control-sm mb-2"
               placeholder="Search country..."
               id="{{ $id }}Search"
               onmousedown="event.stopPropagation()"
               onfocus="event.stopPropagation()"
               oninput="event.stopPropagation()"
               onkeydown="event.stopPropagation()"
               onkeyup="event.stopPropagation()">

        <div class="country-list nationality-dropdown-list" id="{{ $id }}List" data-component-id="{{ $id }}" style="max-height: 300px; overflow-y: auto;">
            <!-- Countries will be populated by JavaScript -->
        </div>
    </div>

    <input type="hidden" id="{{ $id }}" name="{{ $name }}" value="{{ $value }}" {{ $required ? 'required' : '' }}>
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
            // Load countries from JSON file
            fetch('/data/countries.json')
                .then(response => response.json())
                .then(countries => {
                    // Initialize all nationality/country dropdowns by finding elements with data attribute
                    document.querySelectorAll('.nationality-dropdown-list').forEach(function(listElement) {
                        const componentId = listElement.getAttribute('data-component-id');
                        initializeNationalityDropdown(componentId, countries);
                    });
                })
                .catch(error => console.error('Error loading countries:', error));

            function initializeNationalityDropdown(componentId, countries) {
                const countryList = document.getElementById(componentId + 'List');
                if (!countryList) return;

                // Clear existing items
                countryList.innerHTML = '';

                // Populate country dropdown
                countries.forEach(country => {
                    const button = document.createElement('button');
                    button.className = 'dropdown-item d-flex align-items-center';
                    button.type = 'button';
                    button.setAttribute('data-country-name', country.name);
                    button.setAttribute('data-flag', country.flag);
                    button.setAttribute('data-search', country.name.toLowerCase());

                    // Convert flag code to emoji
                    const flagEmoji = country.iso2
                        .toUpperCase()
                        .split('')
                        .map(char => String.fromCodePoint(127397 + char.charCodeAt(0)))
                        .join('');

                    button.innerHTML = `
                        <span class="me-2">${flagEmoji}</span>
                        <span>${country.name}</span>
                    `;
                    button.addEventListener('click', function() {
                        selectNationality(componentId, country.iso3, flagEmoji, country.name);
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

                // Set initial value if provided
                const hiddenInput = document.getElementById(componentId);
                if (hiddenInput && hiddenInput.value) {
                    // Try to find by ISO3 code first, then by name
                    let initialCountry = countries.find(c => c.iso3 === hiddenInput.value);
                    if (!initialCountry) {
                        initialCountry = countries.find(c => c.name === hiddenInput.value);
                    }

                    if (initialCountry) {
                        const flagEmoji = initialCountry.iso2
                            .toUpperCase()
                            .split('')
                            .map(char => String.fromCodePoint(127397 + char.charCodeAt(0)))
                            .join('');
                        selectNationality(componentId, initialCountry.iso3, flagEmoji, initialCountry.name);
                    }
                }
            }

            function selectNationality(componentId, iso3, flag, displayName) {
                const flagElement = document.getElementById(componentId + 'SelectedFlag');
                const countryElement = document.getElementById(componentId + 'SelectedCountry');
                const hiddenInput = document.getElementById(componentId);

                if (flagElement) flagElement.textContent = flag + ' ';
                if (countryElement) countryElement.textContent = displayName;
                if (hiddenInput) hiddenInput.value = iso3;

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
