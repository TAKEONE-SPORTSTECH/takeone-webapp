@props(['name' => 'call_code', 'id' => 'call_code', 'value' => '', 'required' => false, 'error' => null, 'label' => 'Country Code'])

<div class="mb-4">
    <label for="{{ $id }}" class="block text-sm font-medium text-gray-600 mb-1">
        {{ $label }}@if($required) <span class="text-red-500">*</span>@endif
    </label>
    <select id="{{ $id }}"
            class="call-code-select w-full px-4 py-3 text-base border-2 rounded-xl bg-white/80 shadow-inner transition-all duration-300 focus:bg-white focus:ring-4 focus:ring-primary/10 focus:outline-none {{ $error ? 'border-red-500' : 'border-primary/20 focus:border-primary' }}"
            name="{{ $name }}"
            {{ $required ? 'required' : '' }}>
        <option value="">Select Country Code</option>
    </select>
    @if($error)
        <span class="text-red-500 text-sm mt-1 block" role="alert">
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

@push('styles')
<style>
    /* Select2 Tailwind styling */
    .select2-container--default .select2-selection--single {
        border: 2px solid rgba(139, 92, 246, 0.2) !important;
        border-radius: 0.75rem !important;
        padding: 0.5rem 1rem !important;
        background: rgba(255,255,255,0.8) !important;
        height: auto !important;
        min-height: 3rem !important;
    }

    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 1.5 !important;
        padding: 0 !important;
    }

    .select2-container--default.select2-container--open .select2-selection--single {
        border-color: hsl(250 60% 70%) !important;
    }

    .select2-dropdown {
        border: 2px solid rgba(139, 92, 246, 0.2) !important;
        border-radius: 0.75rem !important;
    }

    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: hsl(250 60% 70%) !important;
    }
</style>
@endpush
@endonce
