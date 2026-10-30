{{--
    Margin — the development board, mobile.

    A standalone page (outside the app shell, like the bout pages) so the
    drag-and-drop owns the whole viewport: no shell transform to trap fixed
    layers, no shell swap to re-run scripts under Sortable's feet.

    Rendering is plain JS that owns its own DOM (partials/script) — SortableJS
    moves real nodes, and a framework re-rendering the same nodes underneath it
    is the classic source of ghost cards. Every value reaches the page through
    textContent, never innerHTML.
--}}
@php
    $rtl = in_array(app()->getLocale(), ['ar'], true);
    $noteKey = 'ffffffff-ffff-ffff-ffff-ffffffffffff'; // placeholder the script swaps for a note's uuid
    $people = collect($payload['people'])->map(fn ($p) => $p['id'] === $payload['me']['id'] ? __('devboard::messages.you') : $p['name'])->all();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#160f24">
    <title>{{ __('devboard::messages.title') }} · TAKEONE</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    @include('devboard::partials.styles')
    @include('devboard::partials.editor-styles')
    @include('devboard::partials.notes-styles')
</head>
<body>
<div class="desk" id="desk">

    {{-- Hero band (Design Rule #6, compact tail: the lane rail straddles it). --}}
    <header class="hero">
        <span class="hero-orb hero-orb-a"></span>
        <span class="hero-orb hero-orb-b"></span>

        <div class="hero-controls">
            <a href="{{ $backUrl }}" class="round" aria-label="{{ __('devboard::messages.back') }}" title="{{ __('devboard::messages.back') }}">
                <i class="bi bi-chevron-left flip"></i>
            </a>
        </div>

        <div class="hero-identity">
            <div class="chips">
                <span class="chip"><i class="bi bi-vector-pen"></i> {{ __('devboard::messages.chip') }}</span>
                <span class="chip" id="hero-count"></span>
            </div>
            <h1>{{ __('devboard::messages.title') }}</h1>
            <p class="owner"><i class="bi bi-people"></i>{{ implode(' & ', $people) }}</p>
        </div>
    </header>

    {{-- Lane rail: the pipeline at a glance. Tap to go, long-press to reorder. --}}
    <nav class="rail-wrap" aria-label="Lanes">
        <div class="rail" id="rail" role="tablist"></div>
    </nav>

    <div class="filters" id="filters">
        <button type="button" class="filter is-on" data-filter="all">{{ __('devboard::messages.filter_all') }}</button>
        <button type="button" class="filter" data-filter="mine">{{ __('devboard::messages.filter_mine') }}</button>
        <button type="button" class="filter" data-filter="for_me">{{ __('devboard::messages.filter_for_me') }}</button>
        <button type="button" class="filter" data-filter="urgent"><i class="bi bi-lightning-charge-fill"></i>{{ __('devboard::messages.filter_urgent') }}</button>
        <span class="filters-gap"></span>
        <button type="button" class="filter bin-chip" id="bin-chip"><i class="bi bi-trash3"></i><span id="bin-count"></span></button>
    </div>

    {{-- One lane per screen; swipe between them. --}}
    <main class="track" id="track"></main>
</div>

{{-- The lane dock rises while a card is held: drop on a pad to move it there. --}}
<div class="dock" id="dock" aria-hidden="true">
    <p class="dock-hint">{{ __('devboard::messages.dock_hint') }}</p>
    <div class="dock-pads" id="dock-pads"></div>
</div>

{{-- Sheets (Design Rule #8) — built by the script, one backdrop for all. --}}
<div class="backdrop" id="backdrop"></div>
<section class="sheet" id="card-sheet" role="dialog" aria-modal="true" aria-labelledby="card-sheet-title"></section>
<section class="sheet" id="bin-sheet" role="dialog" aria-modal="true" aria-labelledby="bin-sheet-title"></section>
<section class="sheet is-note" id="note-sheet" role="dialog" aria-modal="true" aria-labelledby="note-sheet-title"></section>

<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script id="board-data" type="application/json">{!! json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script id="board-config" type="application/json">{!! json_encode([
    'urls' => [
        'data' => route('me.margin.data'),
        'store' => route('me.margin.cards.store'),
        'update' => route('me.margin.cards.update', ['card' => '__ID__']),
        'move' => route('me.margin.cards.move', ['card' => '__ID__']),
        'destroy' => route('me.margin.cards.destroy', ['card' => '__ID__']),
        'restore' => route('me.margin.cards.restore', ['uuid' => '00000000-0000-0000-0000-000000000000']),
        'bin' => route('me.margin.bin'),
        'laneUpdate' => route('me.margin.lanes.update', ['lane' => '__ID__']),
        'laneMove' => route('me.margin.lanes.move', ['lane' => '__ID__']),
        'notes' => route('me.margin.notes', ['card' => '__ID__']),
        'noteStore' => route('me.margin.notes.store', ['card' => '__ID__']),
        'noteUpdate' => route('me.margin.notes.update', ['card' => '__ID__', 'note' => $noteKey]),
        'noteMove' => route('me.margin.notes.move', ['card' => '__ID__', 'note' => $noteKey]),
        'noteDestroy' => route('me.margin.notes.destroy', ['card' => '__ID__', 'note' => $noteKey]),
        'noteRestore' => route('me.margin.notes.restore', ['card' => '__ID__', 'note' => $noteKey]),
        'pictureStore' => route('me.margin.pictures.store', ['card' => '__ID__']),
        'picture' => route('me.margin.pictures.show', ['asset' => '__ID__']),
        'mediaStore' => route('me.margin.media.store', ['card' => '__ID__']),
        'media' => route('me.margin.media.show', ['asset' => '__ID__']),
        'mediaStatus' => route('me.margin.media.status', ['asset' => '__ID__']),
        'inkStore' => route('me.margin.ink.store', ['card' => '__ID__']),
        'inkShow' => route('me.margin.ink.show', ['asset' => '__ID__']),
        'videoLink' => route('me.margin.video-link'),
        'markdown' => route('me.margin.markdown'),
    ],
    'imagePx' => (int) config('devboard.limits.image_px', 2400),
    'limits' => ['voice' => (int) config('devboard.limits.voice_seconds'), 'video' => (int) config('devboard.limits.video_seconds'), 'mb' => (int) config('devboard.limits.media_mb')],
    'locale' => app()->getLocale() === 'en' ? 'en-GB' : app()->getLocale(),
    'rtl' => $rtl,
    't' => trans('devboard::messages'),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script src="{{ asset('vendor/sortablejs/Sortable.min.js') }}"></script>
@include('devboard::partials.recorder')
@include('devboard::partials.ink')
@include('devboard::partials.editor')
@include('devboard::partials.page')
@include('devboard::partials.script')
</body>
</html>
