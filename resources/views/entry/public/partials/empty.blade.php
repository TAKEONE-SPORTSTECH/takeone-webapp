{{--
    The public event page's empty state.

    Every section of the page is ALWAYS on it — a reader looking for the draw
    needs to be told it has not been made, not left guessing whether this
    competition even has one. So each section that has nothing to show yet says
    so in the same shape: the section's own glyph in the event's colour, and one
    line. No illustration, no call to action; the way in already has a button
    of its own further down the page.

    Inputs:
      ev     — the event's colour, already through App\Support\Palette
      icon   — the section's `bi-*` glyph (plus `bracket-icon` where it is a draw)
      title  — the one line
      flush  — true when the caller already supplies the section's side padding
--}}
@php
    /* An INCLUDE, not a component — so the defaults are written here rather
       than with @props, which only exists inside components/. */
    $ev = $ev ?? '#7c3aed';
    $icon = $icon ?? 'bi-dash';
    $title = $title ?? '';
    $flush = $flush ?? false;
@endphp

<div class="text-center {{ $flush ? 'py-4' : 'px-5 py-5' }}">
    <span class="w-11 h-11 mx-auto rounded-2xl grid place-items-center text-lg"
          style="color: {{ $ev }}; background: {{ \App\Support\Palette::alpha(\App\Support\Palette::safe($ev), .09) }};">
        <i class="bi {{ $icon }}"></i>
    </span>
    <p class="text-[12.5px] font-semibold text-foreground mt-2.5">{{ $title }}</p>
</div>
