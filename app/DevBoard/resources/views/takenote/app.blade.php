{{--
    TakeNote — the board as a phone notebook. A standalone page (the app's
    whole world when installed): the notes list, the capture bar, and the note
    page, which is the shared MarginEditor.

    The page carries no notes: it fetches them from me.takenote.data, behind
    the full member stack and EnsureBoardAccess. Server values are written
    with textContent; the only innerHTML is a note's body, cleaned by the
    server's NoteHtml first (partials/editor).
--}}
@php
    $rtl = app()->getLocale() === 'ar';
    $id0 = '00000000-0000-0000-0000-000000000000';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    @include('devboard::takenote.head')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('devboard::partials.editor-styles')
    @include('devboard::takenote.styles')
    @include('devboard::takenote.phone-styles')
</head>
<body>
<div class="app" id="app">
    {{-- Two screens, as Apple Notes has them (takenote/layout): the Folders screen, and the list you went into. --}}
    <header class="head">
        <div class="head-row nb-home-only">
            <div class="today" id="today"></div>
            <div class="people" id="people"></div>
            <button type="button" class="round" data-friends aria-label="{{ __('devboard::messages.fr_title') }}" title="{{ __('devboard::messages.fr_title') }}"><i class="bi bi-people"></i></button>
            <button type="button" class="round" data-lang aria-label="{{ __('devboard::messages.lg_title') }}" title="{{ __('devboard::messages.lg_title') }}"><i class="bi bi-translate"></i></button>
            <button type="button" class="round" data-fonts aria-label="{{ __('devboard::messages.tn_fonts') }}" title="{{ __('devboard::messages.tn_fonts') }}"><i class="bi bi-fonts"></i></button>
            <form method="POST" action="{{ route('takenote.signout') }}" class="signout" data-signout>@csrf<button type="submit" class="round" aria-label="{{ __('devboard::messages.tn_signout') }}" title="{{ __('devboard::messages.tn_signout') }}"><i class="bi bi-box-arrow-right"></i></button></form>
        </div>
        <div class="head-row nb-list-only">
            <button type="button" class="nb-back" id="nb-back"><i class="bi bi-chevron-left"></i><span id="nb-back-label">TakeNote</span></button>
            <button type="button" class="round" id="nb-folder-more" hidden aria-label="{{ __('devboard::messages.nb_folder_options') }}"><i class="bi bi-three-dots"></i></button>
        </div>
        <h1 id="view-title">TakeNote</h1>
    </header>

    <label class="search" id="search">
        <i class="bi bi-search"></i>
        <input type="search" id="search-input" placeholder="{{ __('devboard::messages.tn_search') }}" aria-label="{{ __('devboard::messages.tn_search') }}" enterkeyhint="search">
    </label>

    <div class="install" id="install" hidden>
        <img src="{{ asset('images/takenote/icon-192.png') }}" alt="">
        <span id="install-text">{{ __('devboard::messages.tn_install') }}</span>
        <button type="button" class="go" id="install-go">{{ __('devboard::messages.tn_install_go') }}</button>
        <button type="button" class="x" id="install-x" aria-label="{{ __('devboard::messages.close') }}"><i class="bi bi-x-lg"></i></button>
    </div>

    {{-- the Folders screen: the three ways to see the notebook, the smart lists, the folders --}}
    <div class="seg nb-home-only" id="seg" role="tablist" aria-label="{{ __('devboard::messages.nb_views') }}">
        <button type="button" role="tab" data-view="notes" aria-selected="true" class="is-on" style="--k:#fff"><i class="bi bi-journal-text"></i><span class="lb">{{ __('devboard::messages.tn_notes') }}</span><span class="n"></span></button>
        <button type="button" role="tab" data-view="map" aria-selected="false" style="--k:#6bb8f0"><i class="bi bi-diagram-2"></i><span class="lb">{{ __('devboard::messages.tn_view_map') }}</span><span class="n"></span></button>
        <button type="button" role="tab" data-view="board" aria-selected="false" style="--k:#f7b64b"><i class="bi bi-kanban"></i><span class="lb">{{ __('devboard::messages.tn_view_board') }}</span><span class="n"></span></button>
    </div>

    <nav class="filters nb-group nb-home-only" id="filters" aria-label="{{ __('devboard::messages.nb_smart') }}">
        <button type="button" class="filter is-on" data-filter="all" hidden><span class="lb">{{ __('devboard::messages.filter_all') }}</span><span class="n"></span></button>
        <button type="button" class="filter nb-line" data-filter="pinned" style="--k:#f7b64b"><i class="bi bi-pin-angle"></i><span class="nb-name">{{ __('devboard::messages.nb_pinned') }}</span><span class="n"></span><i class="bi bi-chevron-right"></i></button>
        <button type="button" class="filter nb-line" data-filter="mine" style="--k:#b3a8ff"><i class="bi bi-person"></i><span class="nb-name">{{ __('devboard::messages.filter_mine') }}</span><span class="n"></span><i class="bi bi-chevron-right"></i></button>
        <button type="button" class="filter nb-line" data-filter="others" style="--k:#34d399"><i class="bi bi-people"></i><span class="nb-name">{{ __('devboard::messages.tn_filter_others') }}</span><span class="n"></span><i class="bi bi-chevron-right"></i></button>
    </nav>

    <nav class="folders nb-home-only" id="folders" aria-label="{{ __('devboard::messages.fo_folders') }}"></nav>

    {{-- the list you went into (or the board, or the map) --}}
    <main class="notes nb-list-only" id="notes" aria-live="polite">
        <div class="tile is-skeleton"></div><div class="tile is-skeleton"></div><div class="tile is-skeleton"></div>
    </main>
    <main class="board nb-list-only" id="board" hidden aria-live="polite"></main>
    <main class="map nb-list-only" id="map" hidden></main>
</div>

{{-- the bottom bar: nothing floats over the list. Folders: a new folder · a new note. A list: a quick voice note · how many · a new note. --}}
<nav class="capture" id="capture">
    <button type="button" class="nb-bar-btn nb-home-only" id="nb-new-folder"><i class="bi bi-folder-plus"></i>{{ __('devboard::messages.fo_new') }}</button>
    <button type="button" class="nb-voice nb-list-only" id="cap-voice" aria-label="{{ __('devboard::messages.nb_quick_voice') }}" title="{{ __('devboard::messages.nb_quick_voice') }}"><i class="bi bi-mic-fill"></i></button>
    <span class="nb-count nb-list-only" id="nb-count" aria-live="polite"></span>
    <button type="button" class="nb-new" id="cap-write" aria-label="{{ __('devboard::messages.nb_new_note') }}" title="{{ __('devboard::messages.nb_new_note') }}"><i class="bi bi-pencil-square"></i></button>
    {{-- photo, video and drawing start from inside a note (its own toolbar) --}}
    <span hidden>
        <button type="button" id="cap-photo">{{ __('devboard::messages.tn_photo') }}</button>
        <button type="button" id="cap-video">{{ __('devboard::messages.tn_video') }}</button>
        <button type="button" id="cap-draw">{{ __('devboard::messages.tn_draw') }}</button>
    </span>
</nav>


<div class="toast" id="toast" role="status" aria-live="polite"></div>

@include('devboard::takenote.config', ['desk' => false])
<script src="{{ asset('vendor/sortablejs/Sortable.min.js') }}"></script>
@include('devboard::partials.recorder')
@include('devboard::partials.ink')
@include('devboard::partials.editor')
@include('devboard::partials.page')
@include('devboard::takenote.script')
</body>
</html>
