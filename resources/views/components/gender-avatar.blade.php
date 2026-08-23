@props([
    'gender' => null,                 // 'm'|'f'|'male'|'female' (anything else → male silhouette)
    'bg' => 'hsl(250 55% 60%)',       // tile background, behind the artwork
])

@php
    $female = in_array(strtolower((string) $gender), ['f', 'female', 'woman', 'girl'], true);
    $name = $female ? 'female' : 'male';

    // The supplied artwork (male.jpg / female.jpg in public/images/avatars) when it
    // is there, otherwise the plain silhouette that shipped with the app. Checked
    // rather than assumed so the placeholder never renders as a broken image while
    // the files are being replaced. file_exists is served from PHP's stat cache, so
    // this costs nothing in a long member list.
    $file = "images/avatars/{$name}.jpg";
    $artwork = file_exists(public_path($file));
    if (! $artwork) {
        $file = "images/avatars/{$name}.svg";
    }
    // Stamped with the file's own mtime, the way profile pictures are: these sit
    // behind a CDN that caches images for hours, so replacing the artwork without
    // a new URL leaves every visitor on the old bytes until the edge expires.
    $src = asset($file).'?v='.@filemtime(public_path($file));
@endphp

{{-- Portrait fallback avatar, for a person with no picture of their own — or who
     removed the one they had. Sizing, rounding and any border come from the caller
     via the merged class; only the ratio matters here, and it is the platform's
     3:4 portrait (object-cover keeps the crop, object-top keeps the head).

     A real photograph or illustration is shown as drawn. The bundled SVG is a bare
     shape, so that one alone is flattened to white over the tile — inverting real
     artwork would turn it into a silhouette of itself. --}}
<div {{ $attributes->merge(['class' => 'overflow-hidden grid place-items-center']) }} style="background: {{ $bg }};">
    <img src="{{ $src }}" alt="" aria-hidden="true"
         class="w-full h-full object-cover object-top"
         @if(! $artwork) style="filter: brightness(0) invert(1); opacity: 0.9;" @endif>
</div>
