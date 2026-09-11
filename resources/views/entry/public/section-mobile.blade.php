@extends('entry.layout')

{{--
    One section of a public event, on its own page — MOBILE.

    The four doors on `/e/{uuid}` (Draw · Officials · Gallery · Participants)
    each open one of these, exactly as the member page's four door rows open
    their own pages. This is the SHELL for all four: the standard hero band with
    a labelled back pill (Design Rule #6), and the section's body included by
    key from `entry.public.sections.<key>-mobile`.

    One shell rather than four pages, because the four differ only in their
    BODY — and the body partials are the same ones the event page used when
    these were inline sections, so nothing was rewritten to move them here.

    The band's controls are the shared row (partials/event-band-controls) with
    `leading = back`, so this page and the poster differ by exactly one glyph.

    Expects, from App\Http\Controllers\PublicEventController:
      $e            the payload from App\Events\Support\PublicEvent
      $section      'draw' | 'officials' | 'gallery' | 'participants'
      $sectionLabel the band chip's words
      $sectionIcon  its `bi-*` glyph (with `bracket-icon` where it is the draw)
--}}

@push('styles')
@include('entry.public.partials.tokens')
@endpush

@section('body')
@php
    use App\Support\Palette;

    /* The body partials read these — the event's colour, guarded before it
       lands in a style attribute, and the one soft tint they tint plates with. */
    $ev = Palette::safe($e['color']);
    $evSoft = Palette::alpha($ev, .09);

    /* A flag-icons class for a 2-letter code, or null. Same helper the members'
       roster uses; the sheet is loaded by entry.layout. */
    $flagClass = function ($code) {
        $c = strtolower(substr(preg_replace('/[^a-zA-Z]/', '', (string) $code), 0, 2));

        return strlen($c) === 2 ? 'fi fi-'.$c : null;
    };

    /* Seconds as a clock, for a gallery tile's corner. */
    $clock = function ($seconds) {
        $s = max(0, (int) $seconds);

        return $s >= 3600
            ? sprintf('%d:%02d:%02d', intdiv($s, 3600), intdiv($s % 3600, 60), $s % 60)
            : sprintf('%d:%02d', intdiv($s, 60), $s % 60);
    };
@endphp
<div x-data="publicEvent()" class="-mx-4 -mt-4 pb-4">

    {{-- ===== The band (Design Rule #6) ===== --}}
    <header class="m-hero px-5 pt-5 pb-14 text-white relative overflow-hidden"
            style="background: {{ \App\Support\Palette::eventBand($e['color'], '150deg') }};">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        {{-- The SAME control row as the poster and /me/events/{uuid} — see
             partials/event-band-controls.

             This page drew its own until 2026-09-09, and it had all the drift
             that predicts: `justify-between` across three children, which put
             Share in the dead centre of the band instead of beside the other
             trailing control; the 40px circle written out in Tailwind at a
             different alpha to `.ev-ctl`; a back chevron missing its
             `rtl:rotate-180`; `bi-share` where the poster used
             `bi-share-fill`; and a full-screen account SHEET where the poster
             had an anchored dropdown.

             Only the leading control differs from the poster now, which is
             exactly what `leading` is for: there IS somewhere to go back to
             from here. --}}
        <div class="relative z-50">
            @include('partials.event-band-controls', [
                'mode' => 'public',
                'leading' => 'back',
                'backHref' => $backUrl ?? route('events.public', ['event' => $e['key']]),
                'backLabel' => __('personal.event_show_event'),
                'e' => $e,
                'console' => $console ?? null,
                'signedIn' => $signedIn ?? auth()->check(),
                'signedInName' => auth()->user()?->full_name ?? auth()->user()?->name,
                'signOutUrl' => route('events.public.sign-out', ['event' => $e['key']]),
            ])
        </div>

        {{-- Identity: the section's own chip, then the event, then whose it is. --}}
        <div class="relative z-10 mt-6">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur">
                    <i class="bi {{ $sectionIcon }}"></i> {{ $sectionLabel }}
                </span>
                @if(!empty($e['sport_label']))
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wide bg-white/20 backdrop-blur"><i class="bi {{ $e['sport_icon'] ?? 'bi-dribbble' }}"></i> {{ $e['sport_label'] }}</span>
                @endif
            </div>
            <h1 class="text-2xl font-black mt-3 leading-tight">{{ $e['title'] }}</h1>
            @if($e['club'])
                <p class="text-sm text-white/85 mt-1.5 flex items-center gap-1.5">
                    <i class="bi bi-building"></i>{{ $e['club'] }}
                </p>
            @endif
        </div>
    </header>

    {{-- The body rides up over the band's tail, like every other page's does.

         Most sections are ONE panel: a block of prose or a board. The entry
         list is not — it is a list of PEOPLE, and a person on this platform is
         drawn as a card. Neither is the GALLERY: since it came onto the
         standalone template it is a column of broadcast cards, each with its
         own gold edge and shadow, and the panel's `overflow-hidden` clipped
         those shadows while the white ground swallowed the card edges. Cards
         inside a card is a card with cards on it, so a section may opt out of
         the panel and lay out its own surface. It keeps the page's gutters
         either way. --}}
    @php $ownSurface = in_array($section, ['participants', 'gallery'], true); @endphp
    <div class="px-4 -mt-8 relative z-10">
        @if($ownSurface)
            @include('entry.public.sections.'.$section.'-mobile')
        @else
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                @include('entry.public.sections.'.$section.'-mobile')
            </div>
        @endif
    </div>
</div>

{{-- The gallery's player. Harmless on the other three: nothing opens it. --}}
<x-media-lightbox />
@endsection

@push('scripts')
@include('entry.public.partials.page-script')
@endpush
