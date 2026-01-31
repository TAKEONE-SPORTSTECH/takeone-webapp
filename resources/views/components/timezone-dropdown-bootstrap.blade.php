@props(['name' => 'timezone', 'id' => 'timezone', 'value' => '', 'required' => false, 'error' => null, 'label' => 'Timezone'])

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
                <span class="timezone-label" id="{{ $id }}SelectedTimezone">Select Timezone</span>
            </span>
        </button>

        <div class="dropdown-menu p-2 w-100" aria-labelledby="{{ $id }}Dropdown" onclick="event.stopPropagation()">
            <input type="text"
                   class="form-control form-control-sm mb-2"
                   placeholder="Search timezone..."
                   id="{{ $id }}Search"
                   onmousedown="event.stopPropagation()"
                   onfocus="event.stopPropagation()"
                   oninput="event.stopPropagation()"
                   onkeydown="event.stopPropagation()"
                   onkeyup="event.stopPropagation()">

            <div class="timezone-list" id="{{ $id }}List" data-component-id="{{ $id }}" style="max-height: 300px; overflow-y: auto;">
                <!-- Timezones will be populated by JavaScript -->
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
        .timezone-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .timezone-list .dropdown-item {
            cursor: pointer;
        }

        .timezone-list .dropdown-item:hover {
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
                    // Initialize all timezone dropdowns
                    document.querySelectorAll('.timezone-list').forEach(function(listElement) {
                        const componentId = listElement.getAttribute('data-component-id');
                        initializeTimezoneDropdown(componentId, countries);
                    });
                })
                .catch(error => console.error('Error loading countries:', error));

            function initializeTimezoneDropdown(componentId, countries) {
                const timezoneList = document.getElementById(componentId + 'List');
                if (!timezoneList) return;

                // Clear existing items
                timezoneList.innerHTML = '';

                // Get unique timezones with their associated countries
                const timezoneMap = {};
                countries.forEach(country => {
                    if (country.timezone && !timezoneMap[country.timezone]) {
                        timezoneMap[country.timezone] = {
                            timezone: country.timezone,
                            flag: country.iso2,
                            countryName: country.name
                        };
                    }
                });

                // Populate timezone dropdown
                Object.values(timezoneMap).forEach(tzData => {
                    const button = document.createElement('button');
                    button.className = 'dropdown-item d-flex align-items-center';
                    button.type = 'button';
                    button.setAttribute('data-timezone', tzData.timezone);
                    button.setAttribute('data-flag', tzData.flag);
                    button.setAttribute('data-search', `${tzData.countryName.toLowerCase()} ${tzData.timezone.toLowerCase()}`);

                    // Convert flag code to emoji
                    const flagEmoji = tzData.flag
                        .toUpperCase()
                        .split('')
                        .map(char => String.fromCodePoint(127397 + char.charCodeAt(0)))
                        .join('');

                    button.innerHTML = `
                        <span class="me-2">${flagEmoji}</span>
                        <span>${tzData.timezone}</span>
                    `;
                    button.addEventListener('click', function() {
                        selectTimezone(componentId, tzData.timezone, flagEmoji);
                    });
                    timezoneList.appendChild(button);
                });

                // Search functionality
                const searchInput = document.getElementById(componentId + 'Search');
                if (searchInput) {
                    searchInput.addEventListener('input', function(e) {
                        const searchTerm = e.target.value.toLowerCase();
                        const items = timezoneList.querySelectorAll('.dropdown-item');
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
                    const initialTimezone = Object.values(timezoneMap).find(tz => tz.timezone === hiddenInput.value);
                    if (initialTimezone) {
                        const flagEmoji = initialTimezone.flag
                            .toUpperCase()
                            .split('')
                            .map(char => String.fromCodePoint(127397 + char.charCodeAt(0)))
                            .join('');
                        selectTimezone(componentId, initialTimezone.timezone, flagEmoji);
                    }
                }
            }

            function selectTimezone(componentId, timezone, flag) {
                const flagElement = document.getElementById(componentId + 'SelectedFlag');
                const timezoneElement = document.getElementById(componentId + 'SelectedTimezone');
                const hiddenInput = document.getElementById(componentId);

                if (flagElement) flagElement.textContent = flag + ' ';
                if (timezoneElement) timezoneElement.textContent = timezone;
                if (hiddenInput) {
                    hiddenInput.value = timezone;
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
            window.setTimezoneValue = function(componentId, timezone, countries) {
                const timezoneMap = {};
                countries.forEach(country => {
                    if (country.timezone && !timezoneMap[country.timezone]) {
                        timezoneMap[country.timezone] = {
                            timezone: country.timezone,
                            flag: country.iso2
                        };
                    }
                });

                const tzData = timezoneMap[timezone];
                if (tzData) {
                    const flagEmoji = tzData.flag
                        .toUpperCase()
                        .split('')
                        .map(char => String.fromCodePoint(127397 + char.charCodeAt(0)))
                        .join('');
                    selectTimezone(componentId, timezone, flagEmoji);
                }
            };
        });
    </script>
    @endpush
@endonce
