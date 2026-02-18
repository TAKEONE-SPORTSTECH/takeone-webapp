@props(['name' => 'call_code', 'id' => 'call_code', 'value' => '', 'required' => false, 'error' => null, 'label' => 'Country Code'])

<div class="mb-4">
    <label for="{{ $id }}" class="tf-label">
        {{ $label }}@if($required) <span class="text-red-500">*</span>@endif
    </label>
    <select id="{{ $id }}"
            class="call-code-select tf-select {{ $error ? 'border-red-500' : 'border-primary/20 focus:border-primary' }}"
            name="{{ $name }}"
            {{ $required ? 'required' : '' }}>
        <option value="">Select Country Code</option>
    </select>
    @if($error)
        <span class="tf-error" role="alert">
            <strong>{{ $error }}</strong>
        </span>
    @endif
</div>

@once
@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
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

                // Set initial value
                const initialValue = '{{ $value }}';
                if (initialValue) {
                    selectElement.value = initialValue;
                }

                // Initialize Select2
                if (typeof $ !== 'undefined' && $.fn.select2) {
                    $(selectElement).select2({
                        templateResult: function(state) {
                            if (!state.id) return state.text;
                            const option = $(state.element);
                            const flagCode = option.data('flag');
                            return $(`<span><span class="fi fi-${flagCode} mr-2"></span>${state.text}</span>`);
                        },
                        templateSelection: function(state) {
                            if (!state.id) return state.text;
                            const option = $(state.element);
                            const flagCode = option.data('flag');
                            return $(`<span><span class="fi fi-${flagCode} mr-2"></span>${state.text}</span>`);
                        },
                        width: '100%'
                    });
                }
            })
            .catch(error => console.error('Error loading countries:', error));
    });
</script>
@endpush

{{-- Styles moved to app.css (Phase 6) --}}
@endonce
