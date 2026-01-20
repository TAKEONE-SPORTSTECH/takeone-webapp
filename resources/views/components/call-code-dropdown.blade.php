@props(['name' => 'call_code', 'id' => 'call_code', 'value' => '', 'required' => false, 'error' => null])

<div class="mb-3">
    <label for="{{ $id }}" class="form-label">Country Code</label>
    <select id="{{ $id }}"
            class="form-select call-code-select @error($name) is-invalid @enderror"
            name="{{ $name }}"
            {{ $required ? 'required' : '' }}>
        <option value="">Select Country Code</option>
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
                        option.value = country.call_code;
                        option.textContent = `${country.name} (${country.call_code})`;
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
        .call-code-select {
            background-size: 20px 15px;
        }
    </style>
    @endpush
@endonce
