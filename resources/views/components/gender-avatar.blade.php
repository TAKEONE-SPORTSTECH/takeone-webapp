@props([
    'gender' => null,                 // 'm'|'f'|'male'|'female' (anything else → male silhouette)
    'bg' => 'hsl(250 55% 60%)',       // tile background, behind the artwork
    /*
     * How wide this avatar is actually drawn, as a CSS length — `sizes` for the
     * srcset below. It is not decoration: it is the ONLY thing that tells the
     * browser which cut of the artwork to fetch, and getting it wrong is what
     * "pixelated" looked like. Pass the box's width wherever it is known;
     * 112px is the largest place on the platform and the safe default.
     */
    'sizes' => '112px',
])

@php
    $female = in_array(strtolower((string) $gender), ['f', 'female', 'woman', 'girl'], true);
    $name = $female ? 'female' : 'male';

    // The supplied artwork (male.jpg / female.jpg in public/images/avatars) when it
    // is there, otherwise the plain silhouette that shipped with the app. Checked
    // rather than assumed so the placeholder never renders as a broken image while
    // the files are being replaced. file_exists is served from PHP's stat cache, so
    // this costs nothing in a long member list.
    /*
     * The 360px cut of the artwork, not the 600px master.
     *
     * ⚠️ This is a SHARPNESS fix, not a weight one. The artwork is dense line
     * work, and the browser was reducing 600px of it into a 66px box on the
     * entry lists — a 9x downscale, done with a cheap filter at paint time,
     * which aliases fine hatching into what reads as a pixelated mess. Resizing
     * it ONCE, properly, and letting the browser do the remaining ~1.8x is what
     * makes it clean.
     *
     * 360 is chosen off the largest place this is drawn: `w-28` (112 CSS px),
     * which is 336 device pixels on a 3x phone. Every other use is smaller. If
     * a bigger one ever appears, cut a bigger file and change this number —
     * do not point it back at the master.
     */
    $file = "images/avatars/{$name}.jpg";
    $artwork = file_exists(public_path($file));
    if (! $artwork) {
        $file = "images/avatars/{$name}.svg";
    }

    /*
     * ⚠️ A SHARPNESS fix, not a weight one.
     *
     * The artwork is dense line work over a halftone ground. Handed the 600px
     * master for a 66px box, the browser reduces it 9x with a cheap filter at
     * paint time, and fine hatching beating against the pixel grid is exactly
     * what reads as "pixelated". Resolution was never the problem; the SIZE OF
     * THE REDUCTION was.
     *
     * So the cuts are made once, properly, and the browser is told how wide the
     * box is (`sizes`) and asked to pick. At 1x a 66px box takes the 132 cut and
     * does no work at all; at 3x it takes 264. Nobody downloads the master.
     *
     * Cuts live beside the artwork as <name>-<width>.jpg. Only the ones that
     * exist are offered, so the platform still renders while they are being
     * regenerated — and a caller with no cuts on disk simply gets the master,
     * exactly as before.
     */
    $srcset = collect($artwork ? [132, 264, 360] : [])
        ->filter(fn ($w) => file_exists(public_path("images/avatars/{$name}-{$w}.jpg")))
        ->map(fn ($w) => asset("images/avatars/{$name}-{$w}.jpg").'?v='.@filemtime(public_path("images/avatars/{$name}-{$w}.jpg")).' '.$w.'w')
        ->implode(', ');
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
         @if($srcset) srcset="{{ $srcset }}, {{ $src }} 600w" sizes="{{ $sizes }}" @endif
         class="w-full h-full object-cover object-top"
         @if(! $artwork) style="filter: brightness(0) invert(1); opacity: 0.9;" @endif>
</div>
