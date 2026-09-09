{{-- `$shell` is shared ONLY on the sealed event routes (/e/{uuid}/admin/…), so with
     nothing shared this is the member shell exactly as before. See entry/shell. --}}
@extends($shell ?? 'layouts.personal-mobile')

@section('title', __('events.divisions_page_title'))

{{--
    The weight classes.

    Its own screen since 2026-09-06. It was a slab on the event console, and
    before that a hundred inline rows in the create form — both wrong for the
    same reason: a division is a SUBJECT, not a console tile. Entrants land in
    it, the draw is cut from it, it is scheduled per day and results come out of
    it, all of which happens long after the event was created.

    The page is deliberately thin. Everything on it is <x-event-divisions> in
    server mode — the same component the create form runs in local mode — so the
    editor cannot drift between the two places it is used, and saving here
    re-cuts that division's draw through the owning package.

    Organiser only: the controller asserts it before rendering, so there is no
    read-only state to design for.

    Expects $e (the event view, built with $full = true).
--}}

@section($contentSection ?? 'content')
{{-- ⚠️ The hero band is full-bleed (Design Rule #6), so the page wrapper's
     padding has to be cancelled — but only where there IS one to cancel. Same
     shell-dependent trick as the officials page; cancelling unconditionally
     pushes the member page 16px past both screen edges. --}}
<div class="{{ isset($shell) ? '-mx-4 -mt-4' : '' }}">

    <header class="m-hero px-5 pt-5 pb-8 text-white relative overflow-hidden"
            style="background: {{ \App\Support\Palette::pageBand($e['color'], isset($shell)) }};">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        {{-- Back: the round 40px control with a tail-less chevron and no words
             (Design Rule #6); the destination travels in its aria-label. It
             points at the CONSOLE, which is the screen this is opened from. --}}
        <div class="flex items-center justify-between gap-2 relative z-50">
            <a href="{{ ($sealed ?? false) ? url('/e/'.$e['key'].'/admin/manage') : route('me.events.manage', $e['key']) }}"
               data-shell-link data-route="me.events"
               class="m-press inline-flex items-center w-10 h-10 justify-center rounded-full bg-white/15 border border-white/25 backdrop-blur text-white text-sm font-semibold no-underline"
               aria-label="{{ __('personal.event_manage_title') }}" title="{{ __('personal.event_manage_title') }}">
                <i class="bi bi-chevron-left"></i>
            </a>
        </div>

        {{-- Identity: the count as a chip, then the title, then whose event. --}}
        <div class="relative z-10 mt-6">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi bi-diagram-3 bracket-icon"></i>
                    {{ count($e['division_rows'] ?? []) }}
                </span>
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ __('events.divisions_page_title') }}</h1>
            <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                <i class="bi bi-trophy"></i>{{ $e['title'] }}
            </p>
        </div>
    </header>

    {{-- Content sits BELOW the band, not over it.
         What starts this page is a line of small grey text, and riding that up
         over the tail put half of it on the blue where it could not be read
         (reported 2026-09-06). The rule sizes the overlap to what overlaps it —
         a card may straddle the edge, a sentence may not. --}}
    <div class="px-4 mt-4 relative z-10 pb-6">
        <p class="text-[11px] text-muted-foreground mb-3">{{ __('events.divisions_page_hint') }}</p>

        <x-event-divisions :event="$e['key']" :server="true"
                           :divisions="$e['division_rows'] ?? []"
                           :phases="$e['phase_defs'] ?? []"
                           :days="$e['day_count'] ?? 1"
                           :color="$e['color']" :can-manage="true" />
    </div>
</div>
@endsection
