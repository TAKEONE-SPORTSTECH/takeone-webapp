@props(['name' => 'nationality', 'id' => 'nationality', 'value' => '', 'required' => false, 'error' => null])

<select id="{{ $id }}"
        class="form-select nationality-select @error($name) is-invalid @enderror"
        name="{{ $name }}"
        {{ $required ? 'required' : '' }}>
    <option value="">Select Nationality</option>
</select>

@if($error)
    <span class="invalid-feedback" role="alert">
        <strong>{{ $error }}</strong>
    </span>
@endif

@once
    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Country data for nationality
            const countries = [
                { name: 'United States', flagCode: 'us' },
                { name: 'Canada', flagCode: 'ca' },
                { name: 'United Kingdom', flagCode: 'gb' },
                { name: 'United Arab Emirates', flagCode: 'ae' },
                { name: 'Saudi Arabia', flagCode: 'sa' },
                { name: 'Qatar', flagCode: 'qa' },
                { name: 'Kuwait', flagCode: 'kw' },
                { name: 'Bahrain', flagCode: 'bh' },
                { name: 'Oman', flagCode: 'om' },
                { name: 'Egypt', flagCode: 'eg' },
                { name: 'India', flagCode: 'in' },
                { name: 'Pakistan', flagCode: 'pk' },
                { name: 'Bangladesh', flagCode: 'bd' },
                { name: 'Malaysia', flagCode: 'my' },
                { name: 'Singapore', flagCode: 'sg' },
                { name: 'Japan', flagCode: 'jp' },
                { name: 'China', flagCode: 'cn' },
                { name: 'South Korea', flagCode: 'kr' },
                { name: 'Australia', flagCode: 'au' },
                { name: 'Germany', flagCode: 'de' },
                { name: 'France', flagCode: 'fr' },
                { name: 'Italy', flagCode: 'it' },
                { name: 'Spain', flagCode: 'es' },
                { name: 'Netherlands', flagCode: 'nl' },
                { name: 'Sweden', flagCode: 'se' },
                { name: 'Norway', flagCode: 'no' },
                { name: 'Denmark', flagCode: 'dk' },
                { name: 'Finland', flagCode: 'fi' },
                { name: 'Switzerland', flagCode: 'ch' },
                { name: 'Austria', flagCode: 'at' },
                { name: 'Poland', flagCode: 'pl' },
                { name: 'Czech Republic', flagCode: 'cz' },
                { name: 'Hungary', flagCode: 'hu' },
                { name: 'Romania', flagCode: 'ro' },
                { name: 'Greece', flagCode: 'gr' },
                { name: 'Turkey', flagCode: 'tr' },
                { name: 'Russia', flagCode: 'ru' },
                { name: 'Brazil', flagCode: 'br' },
                { name: 'Mexico', flagCode: 'mx' },
                { name: 'Argentina', flagCode: 'ar' },
                { name: 'Chile', flagCode: 'cl' },
                { name: 'Colombia', flagCode: 'co' },
                { name: 'South Africa', flagCode: 'za' },
                { name: 'Nigeria', flagCode: 'ng' },
                { name: 'Kenya', flagCode: 'ke' },
                { name: 'Sri Lanka', flagCode: 'lk' },
                { name: 'Vietnam', flagCode: 'vn' },
                { name: 'Thailand', flagCode: 'th' },
                { name: 'Indonesia', flagCode: 'id' },
                { name: 'Philippines', flagCode: 'ph' },
                { name: 'New Zealand', flagCode: 'nz' },
                { name: 'Portugal', flagCode: 'pt' },
                { name: 'Ireland', flagCode: 'ie' },
                { name: 'Israel', flagCode: 'il' },
                { name: 'Jordan', flagCode: 'jo' },
                { name: 'Lebanon', flagCode: 'lb' },
                { name: 'Iraq', flagCode: 'iq' },
            ];

            // Initialize all nationality dropdowns on the page
            document.querySelectorAll('.nationality-select').forEach(function(selectElement) {
                if (typeof $ !== 'undefined' && $.fn.select2) {
                    const $select = $(selectElement);

                    $select.select2({
                        data: countries.map(country => ({
                            id: country.name,
                            text: country.name,
                            flagCode: country.flagCode
                        })),
                        templateResult: function(data) {
                            if (!data.id) return data.text;
                            return $(`<span><span class="fi fi-${data.flagCode} me-2"></span> ${data.text}</span>`);
                        },
                        templateSelection: function(data) {
                            if (!data.id) return data.text;
                            return $(`<span><span class="fi fi-${data.flagCode} me-2"></span> ${data.text}</span>`);
                        },
                        placeholder: 'Select Nationality',
                        allowClear: true,
                        width: '100%'
                    });

                    // Restore value if provided
                    const initialValue = selectElement.getAttribute('data-value') || '{{ $value }}';
                    if (initialValue) {
                        $select.val(initialValue).trigger('change');
                    }
                }
            });
        });
    </script>
    @endpush
@endonce
