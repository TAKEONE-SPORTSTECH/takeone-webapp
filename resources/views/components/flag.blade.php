@props([
    /* A country code as anything the platform actually stores: 'BH', 'bh',
       'BHR', or a whole name is rejected rather than guessed at. */
    'code' => null,
    /* Height in px. The width follows it at 4:3, which is the shape the files
       are drawn in — a flag squeezed to a square is a different flag. */
    'h' => 13,
    /* Rounding. Flags are 4:3 and are drawn as a small rounded rectangle, which
       is what a flag looks like on a card — never a circle, which crops a
       horizontal stripe to nothing. */
    'rounded' => 'rounded-[3px]',
    'title' => null,
])

@php
    /* ── Flags are FILES, not emoji ────────────────────────────────────────
       The platform drew these as emoji built from the ISO-2 code. That renders
       on iOS, Android and macOS — and as the LETTERS "BH" on Windows, which
       ships no flag glyphs in its emoji font. Most scoring tables are Windows
       laptops, so the one reader who most needs to see a country saw two
       capitals instead (reported 2026-09-12).

       A vendored SVG renders the same everywhere, prints, and survives a
       screenshot. See public/images/flags/README.md. */
    $raw = strtolower(preg_replace('/[^a-zA-Z]/', '', (string) $code));

    /* ISO-3 is accepted because some rows carry it (a club's country is
       'BHR' in places). Only the codes the set actually has resolve; anything
       else falls through to the text below rather than to a missing image. */
    $iso = \App\Support\Countries::iso2($raw);

    $file = $iso ? public_path('images/flags/'.$iso.'.svg') : null;
    $exists = $file && is_file($file);

    /* ⚠️ The SIZE is an inline style, never a utility class.
       The Tailwind bundle is prebuilt, so an arbitrary value nobody has used
       before (`h-[13.5px]`) ships with no CSS — and an unsized SVG paints at
       its intrinsic 640×480. Inline styles cannot silently do nothing. */
    $h = max(8, (int) $h);
    $box = 'height: '.$h.'px; width: '.round($h * 4 / 3, 1).'px;';
@endphp

@if($exists)
    <img src="{{ asset('images/flags/'.$iso.'.svg') }}"
         alt="" aria-hidden="true" loading="lazy" decoding="async"
         @if($title) title="{{ $title }}" @endif
         style="{{ $box }}"
         {{ $attributes->merge(['class' => 'inline-block object-cover flex-shrink-0 '.$rounded]) }}>
@elseif($raw !== '')
    {{-- No file for this code: say the code rather than nothing. A flag that
         cannot be drawn is still a fact about where somebody is from. --}}
    <span style="{{ $box }} line-height: {{ $h }}px;"
          {{ $attributes->merge(['class' => 'inline-flex items-center justify-center rounded-[3px] bg-muted text-[9px] font-bold text-muted-foreground flex-shrink-0 overflow-hidden']) }}
          @if($title) title="{{ $title }}" @endif>{{ strtoupper($raw) }}</span>
@endif
