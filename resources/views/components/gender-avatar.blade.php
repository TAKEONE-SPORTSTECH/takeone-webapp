@props([
    'gender' => null,                 // 'm'|'f'|'male'|'female' (anything else → male silhouette)
    'bg' => 'hsl(250 55% 60%)',       // tile background
])

@php
    $female = in_array(strtolower((string) $gender), ['f', 'female', 'woman', 'girl'], true);
    // Public-domain silhouette images (Wikimedia Commons), stored in public/images/avatars.
    $src = asset('images/avatars/' . ($female ? 'female' : 'male') . '.svg');
@endphp

{{-- Portrait fallback avatar: a real gendered silhouette image rendered white on a colored
     tile. Sizing/rounding/border come from the caller via the merged class. --}}
<div {{ $attributes->merge(['class' => 'overflow-hidden grid place-items-center']) }} style="background: {{ $bg }};">
    <img src="{{ $src }}" alt="" aria-hidden="true"
         class="w-full h-full object-cover object-top"
         style="filter: brightness(0) invert(1); opacity: 0.9;">
</div>
