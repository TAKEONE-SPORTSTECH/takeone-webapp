@props([
    'model',                      // Alpine state path to bind, e.g. "self.gender"
    'maleLabel'   => "'Male'",    // Alpine expression for the male button text
    'femaleLabel' => "'Female'",  // Alpine expression for the female button text
    'maleValue'   => 'Male',      // stored value when Male is chosen
    'femaleValue' => 'Female',    // stored value when Female is chosen
])

{{-- Two-button gender selector. Male shades blue, Female shades pink when selected. --}}
<div {{ $attributes->merge(['class' => 'grid grid-cols-2 gap-3']) }}>
    {{-- ⚠️ The colours live ONLY in the `:class` binding — never also in the
         static class list. Tailwind's bundle emits `.border-gray-200` AFTER
         `.border-blue-500` and `.text-gray-600` after `.text-blue-600`, and
         equal-specificity classes are resolved by stylesheet ORDER, not by the
         order they appear in the attribute. A static grey therefore beats the
         bound colour and the selected button loses its highlight — which is
         exactly what happened when the neutral state was added here on
         2026-09-03. The unselected look is the binding's else-branch. --}}
    <button type="button" @click="{{ $model }} = '{{ $maleValue }}'"
            class="flex items-center justify-center gap-2 py-3 rounded-xl border-2 transition-all font-semibold text-sm"
            :class="{{ $model }} === '{{ $maleValue }}' ? 'border-blue-500 bg-blue-50 text-blue-600' : 'border-gray-200 text-gray-600 hover:border-gray-300'">
        <i class="bi bi-gender-male"></i><span x-text="{{ $maleLabel }}"></span>
    </button>
    <button type="button" @click="{{ $model }} = '{{ $femaleValue }}'"
            class="flex items-center justify-center gap-2 py-3 rounded-xl border-2 transition-all font-semibold text-sm"
            :class="{{ $model }} === '{{ $femaleValue }}' ? 'border-pink-500 bg-pink-50 text-pink-600' : 'border-gray-200 text-gray-600 hover:border-gray-300'">
        <i class="bi bi-gender-female"></i><span x-text="{{ $femaleLabel }}"></span>
    </button>
</div>
