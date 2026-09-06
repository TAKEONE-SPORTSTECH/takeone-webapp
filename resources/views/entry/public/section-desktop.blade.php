@extends('entry.layout')

{{--
    One section of a public event, on its own page — DESKTOP.

    The four doors on `/e/{uuid}` (Draw · Officials · Gallery · Participants)
    each open one of these, exactly as the member page's four door rows open
    their own pages. This is the SHELL for all four: the standard hero band with
    a labelled back pill (Design Rule #6), and the section's body included by
    key from `entry.public.sections.<key>-desktop`.

    One shell rather than four pages, because the four differ only in their
    BODY — and the body partials are the same ones the event page used when
    these were inline sections, so nothing was rewritten to move them here.

    Expects, from App\Http\Controllers\PublicEventController:
      $e            the payload from App\Events\Support\PublicEvent
      $section      'draw' | 'officials' | 'gallery' | 'participants'
      $sectionLabel the band chip's words
      $sectionIcon  its `bi-*` glyph (with `bracket-icon` where it is the draw)
--}}

@push('styles')
@include('entry.public.partials.tokens')
@endpush

@section('body-class', 'bg-background text-foreground antialiased')

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

<div x-data="publicEvent()" class="-mx-4 -my-4 px-4 sm:px-6 lg:px-8 py-6">

    {{-- ===== The band — full-bleed, the standard desktop band (Design Rule #6) ===== --}}
    <div class="-mx-4 sm:-mx-6 lg:-mx-8 -mt-6 overflow-hidden shadow-sm mb-6 text-white relative"
         style="background: {{ \App\Support\Palette::eventBand($e['color'], '150deg') }};">
        <div class="absolute -right-10 -top-10 w-44 h-44 rounded-full bg-white/10"></div>
        <div class="absolute right-6 bottom-8 w-24 h-24 rounded-full bg-white/10"></div>

        {{-- Inner padding mirrors the page wrapper's, so the hero text stays on
             the same vertical axis as the content below it. --}}
        <div class="relative px-4 sm:px-6 lg:px-8 py-6 sm:py-8">

            {{-- Control row: labelled back pill left, round actions right. --}}
            <div class="flex items-center justify-between gap-2 mb-4">
                <a href="{{ route('events.public', ['event' => $e['key']]) }}"
                   class="inline-flex items-center w-10 h-10 justify-center rounded-full bg-white/15 border border-white/25 backdrop-blur text-white text-sm font-semibold hover:bg-white/25 transition-colors no-underline"
           aria-label="{{ __('personal.event_show_event') }}" title="{{ __('personal.event_show_event') }}">
                    <i class="bi bi-chevron-left"></i>
                </a>

                <button type="button" @click="share()" aria-label="{{ __('events.public_share') }}"
                        class="w-10 h-10 rounded-full bg-white/15 border border-white/25 backdrop-blur grid place-items-center hover:bg-white/25 transition-colors">
                    <i class="bi bi-share text-base"></i>
                </button>
            </div>

            {{-- Identity: the section's own chip, then the event, then whose it is. --}}
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
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        @include('entry.public.sections.'.$section.'-desktop')
    </div>
</div>

{{-- The gallery's player. Harmless on the other three: nothing opens it. --}}
<x-media-lightbox />
@endsection

@push('scripts')
@include('entry.public.partials.page-script')
@endpush
