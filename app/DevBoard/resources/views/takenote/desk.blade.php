{{--
    TakeNote on a desk — the same notebook as the phone app, laid out for a
    wide screen the way the notes apps are: the folders, the list of notes, and
    the open note (or the board, or the map) in the pane beside them. /me/notes, full screen — a website of its own, with no
    site navbar; the round arrow in the corner goes back to TAKEONE.

    It is the phone app's script and partials, unchanged in what they do: the
    same ids, the same config (takenote/config, with desk on), the same note
    page (opened inside #pane instead of over the screen). The page carries no
    notes — everything comes from me.takenote.data behind EnsureBoardAccess.
--}}
@php $rtl = app()->getLocale() === 'ar'; @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    @include('devboard::takenote.head')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('devboard::partials.editor-styles')
    @include('devboard::takenote.styles')
    @include('devboard::takenote.desk-styles')
</head>
<body class="dk">
<div class="dk-shell">
    {{-- 1. The folder column: the views, the smart lists, the folders. Folds to a strip (takenote/layout). --}}
    <aside class="dk-rail" aria-label="TakeNote">
        <header class="dk-head">
            <a class="dk-back" href="{{ url('/me') }}" aria-label="TAKEONE" title="TAKEONE"><i class="bi bi-chevron-left"></i></a>
            <div class="dk-brand">
                <img src="{{ asset('images/takenote/icon-192.png') }}" alt="">
                <span class="lb">TakeNote</span>
            </div>
            <button type="button" class="dk-icon" data-rail-toggle aria-label="{{ __('devboard::messages.nb_hide_folders') }}"><i class="bi bi-layout-sidebar"></i></button>
        </header>

        <p class="dk-sec">{{ __('devboard::messages.nb_views') }}</p>
        <div class="seg" id="seg" role="tablist" aria-label="{{ __('devboard::messages.nb_views') }}">
            <button type="button" role="tab" data-view="notes" aria-selected="true" class="is-on" title="{{ __('devboard::messages.tn_notes') }}"><i class="bi bi-journal-text"></i><span class="lb">{{ __('devboard::messages.tn_notes') }}</span><span class="n"></span></button>
            <button type="button" role="tab" data-view="map" aria-selected="false" title="{{ __('devboard::messages.tn_view_map') }}"><i class="bi bi-diagram-2"></i><span class="lb">{{ __('devboard::messages.tn_view_map') }}</span><span class="n"></span></button>
            <button type="button" role="tab" data-view="board" aria-selected="false" title="{{ __('devboard::messages.tn_view_board') }}"><i class="bi bi-kanban"></i><span class="lb">{{ __('devboard::messages.tn_view_board') }}</span><span class="n"></span></button>
        </div>

        <p class="dk-sec">{{ __('devboard::messages.nb_smart') }}</p>
        <nav class="filters" id="filters" aria-label="{{ __('devboard::messages.nb_smart') }}">
            <button type="button" class="filter is-on" data-filter="all" hidden><span class="lb">{{ __('devboard::messages.filter_all') }}</span><span class="n"></span></button>
            <button type="button" class="filter" data-filter="pinned" title="{{ __('devboard::messages.nb_pinned') }}"><i class="bi bi-pin-angle"></i><span class="lb">{{ __('devboard::messages.nb_pinned') }}</span><span class="n"></span></button>
            <button type="button" class="filter" data-filter="mine" title="{{ __('devboard::messages.filter_mine') }}"><i class="bi bi-person"></i><span class="lb">{{ __('devboard::messages.filter_mine') }}</span><span class="n"></span></button>
            <button type="button" class="filter" data-filter="others" title="{{ __('devboard::messages.tn_filter_others') }}"><i class="bi bi-people"></i><span class="lb">{{ __('devboard::messages.tn_filter_others') }}</span><span class="n"></span></button>
        </nav>

        <nav class="folders" id="folders" aria-label="{{ __('devboard::messages.fo_folders') }}"></nav>

        <footer class="dk-foot">
            <div class="people" id="people"></div>
            <span class="today" id="today"></span>
            <button type="button" class="dk-icon" data-friends aria-label="{{ __('devboard::messages.fr_title') }}" title="{{ __('devboard::messages.fr_title') }}"><i class="bi bi-people"></i></button>
            <button type="button" class="dk-icon" data-lang aria-label="{{ __('devboard::messages.lg_title') }}" title="{{ __('devboard::messages.lg_title') }}"><i class="bi bi-translate"></i></button>
            <button type="button" class="dk-icon" data-fonts aria-label="{{ __('devboard::messages.tn_fonts') }}" title="{{ __('devboard::messages.tn_fonts') }}"><i class="bi bi-fonts"></i></button>
            <form method="POST" action="{{ route('takenote.signout') }}" class="signout" data-signout>@csrf<button type="submit" class="dk-icon" aria-label="{{ __('devboard::messages.tn_signout') }}" title="{{ __('devboard::messages.tn_signout') }}"><i class="bi bi-box-arrow-right"></i></button></form>
        </footer>
        {{-- the column's edge: drag to size it, double-click for the usual width (takenote/layout) --}}
        <div class="dk-grip" id="rail-grip" role="separator" aria-orientation="vertical" tabindex="0" aria-label="{{ __('devboard::messages.nb_resize') }}" title="{{ __('devboard::messages.nb_resize') }}"></div>
    </aside>

    {{-- 2. The list: where you are, one way to start a note, search, the rows. --}}
    <section class="dk-list" aria-labelledby="view-title">
        <header class="dk-lhead">
            <div class="dk-lt">
                <h1 id="view-title">{{ __('devboard::messages.tn_notes') }}</h1>
                <p id="list-sub"></p>
            </div>
            <div class="dk-capture" id="capture">
                <button type="button" class="dk-new" id="cap-write" aria-label="{{ __('devboard::messages.nb_new_note') }}" title="{{ __('devboard::messages.nb_new_note') }} (N)"><i class="bi bi-pencil-square"></i></button>
                {{-- the other ways to start live in the note's own toolbar; these are what the empty pane's big buttons press --}}
                <span hidden>
                    <button type="button" id="cap-voice">{{ __('devboard::messages.tn_voice') }}</button>
                    <button type="button" id="cap-photo">{{ __('devboard::messages.tn_photo') }}</button>
                    <button type="button" id="cap-video">{{ __('devboard::messages.tn_video') }}</button>
                    <button type="button" id="cap-draw">{{ __('devboard::messages.tn_draw') }}</button>
                </span>
            </div>
        </header>
        <label class="search" id="search">
            <i class="bi bi-search"></i>
            <input type="search" id="search-input" placeholder="{{ __('devboard::messages.tn_search') }}" aria-label="{{ __('devboard::messages.tn_search') }}">
            <kbd>/</kbd>
        </label>
        <main class="notes" id="notes" aria-live="polite">
            <div class="tile is-skeleton"></div><div class="tile is-skeleton"></div><div class="tile is-skeleton"></div>
        </main>
        {{-- its edge drags too (takenote/layout) --}}
        <div class="dk-grip" id="list-grip" role="separator" aria-orientation="vertical" tabindex="0" aria-label="{{ __('devboard::messages.nb_resize') }}" title="{{ __('devboard::messages.nb_resize') }}"></div>
    </section>

    <section class="dk-pane" id="pane">
        <div class="dk-empty" id="pane-empty">
            <svg class="dk-squiggle" viewBox="0 0 120 46" aria-hidden="true"><path d="M4 30c10-18 22-22 30-10s16 16 26 2 20-20 30-6 14 14 26 4" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
            <b>{{ __('devboard::messages.tn_desk_empty') }}</b>
            <p>{{ __('devboard::messages.tn_desk_empty_hint') }}</p>
            <div class="dk-starts">
                <button type="button" data-cap="cap-write" style="--k:#a78bfa"><i class="bi bi-pencil"></i>{{ __('devboard::messages.tn_write') }}</button>
                <button type="button" data-cap="cap-voice" style="--k:#ff7aa2"><i class="bi bi-mic-fill"></i>{{ __('devboard::messages.tn_voice') }}</button>
                <button type="button" data-cap="cap-photo" style="--k:#f7b64b"><i class="bi bi-camera"></i>{{ __('devboard::messages.tn_photo') }}</button>
                <button type="button" data-cap="cap-video" style="--k:#3fd3c1"><i class="bi bi-camera-video-fill"></i>{{ __('devboard::messages.tn_video') }}</button>
                <button type="button" data-cap="cap-draw" style="--k:#6bb8f0"><i class="bi bi-brush-fill"></i>{{ __('devboard::messages.tn_draw') }}</button>
            </div>
        </div>
        <main class="board" id="board" hidden aria-live="polite"></main>
        <main class="map" id="map" hidden></main>
    </section>
</div>

<div class="toast" id="toast" role="status" aria-live="polite"></div>

@include('devboard::takenote.config', ['desk' => true])
<script src="{{ asset('vendor/sortablejs/Sortable.min.js') }}"></script>
@include('devboard::partials.recorder')
@include('devboard::partials.ink')
@include('devboard::partials.editor')
@include('devboard::partials.page')
@include('devboard::takenote.script')
<script>
(function () {
    // The empty pane's buttons are the side's capture buttons, in a bigger place.
    document.querySelectorAll('[data-cap]').forEach(function (b) {
        b.addEventListener('click', function () { document.getElementById(b.dataset.cap).click(); });
    });
    // "/" finds a note and "N" starts one, the way a desk app does — never while typing.
    document.addEventListener('keydown', function (e) {
        if ((e.key !== '/' && e.key !== 'n' && e.key !== 'N') || e.ctrlKey || e.metaKey || e.altKey) return;
        var a = document.activeElement;
        if (a && (a.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName))) return;
        if (document.querySelector('.ss, .mp.is-open .ed[contenteditable=true]')) return;
        e.preventDefault();
        if (e.key === '/') document.getElementById('search-input').focus();
        else document.getElementById('cap-write').click();
    });
})();
</script>
</body>
</html>
