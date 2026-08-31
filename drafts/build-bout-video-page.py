# -*- coding: utf-8 -*-
"""
Build resources/views/personal/mobile/bout-video.blade.php from the standalone
design in drafts/, swapping Play's data and URLs for ours.

Re-runnable: it always reads the pristine draft and rewrites the blade, so a
later change is an edit HERE, never a hand-patch of the generated file.

    python3 drafts/build-bout-video-page.py
"""
import io, os, sys, re

ROOT = '/var/www/takeone'
SRC  = os.path.join(ROOT, 'drafts', 'Match Video Page - Standalone (11vu7R).html')
OUT  = os.path.join(ROOT, 'resources/views/personal/mobile/bout-video.blade.php')

s = io.open(SRC, encoding='utf-8').read()

def rep(old, new, count=1):
    global s
    n = s.count(old)
    if n != count:
        raise SystemExit('expected %d occurrences, found %d for:\n%s' % (count, n, old[:300]))
    s = s.replace(old, new)

def repall(old, new):
    global s
    if old not in s:
        raise SystemExit('not found: %s' % old[:200])
    s = s.replace(old, new)

def cut(start, end, replacement=''):
    """Remove everything from `start` to `end` inclusive (first match)."""
    global s
    i = s.find(start)
    if i < 0: raise SystemExit('cut start not found: %s' % start[:200])
    j = s.find(end, i)
    if j < 0: raise SystemExit('cut end not found: %s' % end[:200])
    s = s[:i] + replacement + s[j+len(end):]

# ─────────────────────────────────────────────────────────────────────────────
# 0. Blade prologue — everything the markup below reads, resolved once.
# ─────────────────────────────────────────────────────────────────────────────
PROLOGUE = r"""{{--
    Watching a bout back — the standalone "Match Video Page" design, used as it
    was handed over.

    GENERATED. The source of truth is the design file in `drafts/` plus the
    build script `drafts/build-bout-video-page.py`; edit those and re-run it
    rather than patching this file, or the next regeneration will discard the
    patch.

    What changed against the design: its data (Play's match, Play's comments,
    Play's recommendations) is now ours, and every URL that pointed at
    video.takeone.bh points at this platform instead — that host was
    disconnected on 2026-08-27 and nothing here may call it.
--}}
@php
    use Illuminate\Support\Str;

    $play      = $play ?? [];
    $video     = $play['video'] ?? [];
    $red       = $bout['a']['colour'] === 'red' ? $bout['a'] : $bout['b'];
    $blue      = $bout['a']['colour'] === 'blue' ? $bout['a'] : $bout['b'];
    $boutTitle = trim(($bout['a']['name'] ?? '').' vs '.($bout['b']['name'] ?? ''));
    $stage     = $bout['round'] ?: $boutTitle;

    // MM:SS for the highlight rows the design renders server-side.
    $clock = function ($seconds) {
        $whole = max(0, (int) floor((float) $seconds));
        return intdiv($whole, 60).':'.str_pad((string) ($whole % 60), 2, '0', STR_PAD_LEFT);
    };

    // M:SS:mmm — the same instant to the millisecond, for the scoring list.
    // A point is a frame, not a second: two exchanges inside the same second
    // are a different moment to anyone reviewing the tape, and MM:SS printed
    // them as the same time.
    $stamp = function ($seconds) {
        $t = max(0.0, (float) $seconds);
        $whole = (int) floor($t);
        return intdiv($whole, 60)
            .':'.str_pad((string) ($whole % 60), 2, '0', STR_PAD_LEFT)
            .':'.str_pad((string) ((int) round(($t - $whole) * 1000)), 3, '0', STR_PAD_LEFT);
    };
@endphp
"""
s = PROLOGUE + s

# ─────────────────────────────────────────────────────────────────────────────
# 1. Head
# ─────────────────────────────────────────────────────────────────────────────
rep('<meta name="csrf-token" content="RdCfRnlLyiSrkOj5ZPJJiUa25ESl04p9ZAUTENir">',
    '<meta name="csrf-token" content="{{ csrf_token() }}">')

rep('<title>FINALS | Play</title>',
    '<title>{{ $boutTitle }} | {{ $e[\'title\'] }}</title>')

# The Open Graph block described a public Play video. This page is behind an
# authorisation guard that not even every member of the event passes, so it
# advertises nothing to a crawler or a link unfurler.
cut('    <!-- Open Graph meta tags default - will be overridden by page-specific tags -->',
    '<meta name="twitter:image"      content="https://video.takeone.bh/videos/11vu7R/og-image?v=1787436023">',
    '    <meta name="robots" content="noindex, nofollow">')

rep('''    <link rel="icon" type="image/png" href="https://video.takeone.bh/storage/images/logo.png">
    <link rel="apple-touch-icon" href="https://video.takeone.bh/storage/images/logo.png">''',
    '''    <link rel="icon" href="{{ asset('favicon.ico') }}">''')

rep('<link rel="stylesheet" href="https://video.takeone.bh/vendor/flag-icons/css/flag-icons.min.css">',
    '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flag-icons@6.6.6/css/flag-icons.min.css">')

rep('''    <link rel="stylesheet" href="https://video.takeone.bh/css/cropme.min.css">
    <script src="https://video.takeone.bh/js/cropme.min.js"></script>''',
    '''    <link rel="stylesheet" href="https://unpkg.com/cropme@1.4.1/dist/cropme.min.css">
    <script src="https://unpkg.com/cropme@1.4.1/dist/cropme.min.js"></script>''')

# The flag the on-video scoreboard paints comes from the same CDN as the rest.
rep("el.style.backgroundImage = 'url(https://video.takeone.bh/vendor/flag-icons/flags/4x3/' + code + '.svg)';",
    "el.style.backgroundImage = 'url(https://cdn.jsdelivr.net/npm/flag-icons@6.6.6/flags/4x3/' + code + '.svg)';")

# ─────────────────────────────────────────────────────────────────────────────
# 2. Site chrome — header, sidebar, bottom nav.
#    Same markup, our destinations. The search box is gone rather than pointed
#    somewhere that ignores the query: a search field that discards what you
#    typed is worse than no search field.
# ─────────────────────────────────────────────────────────────────────────────
rep('''        <a href="https://video.takeone.bh" class="yt-logo">
            <img src="https://video.takeone.bh/storage/images/logo.png"       alt="Play" class="d-md-none"    style="height:30px;">
            <img src="https://video.takeone.bh/storage/images/fullLogo.png"   alt="Play" class="d-none d-md-block" style="height:30px;">
        </a>''',
    '''        <a href="{{ route('me.videos') }}" class="yt-logo">
            <img src="{{ asset('images/logo.png') }}"     alt="TAKEONE" class="d-md-none"        style="height:30px;">
            <img src="{{ asset('images/fullLogo.png') }}" alt="TAKEONE" class="d-none d-md-block" style="height:30px;">
        </a>''')

cut('''    <div class="yt-header-center d-none d-md-flex">''',
    '''    <div class="yt-header-right">''',
    '''    <div class="yt-header-right">''')

cut('''        <!-- Mobile search -->
        <button type="button" class="yt-icon-btn d-md-none" onclick="toggleMobileSearch()">
            <i class="bi bi-search"></i>
        </button>''', '</header>', '''        <a href="{{ $bout['gallery_url'] }}" class="yt-upload-btn">
                <i class="bi bi-arrow-left"></i>
                <span>{{ __('events.bout_gallery_title') }}</span>
            </a>
            </div>
</header>''')

cut('''    <!-- Mobile Search Overlay -->''', '''    <!-- Sidebar Overlay (Mobile) -->''', '''    <!-- Sidebar Overlay (Mobile) -->''')

cut('''    <!-- Primary section -->''', '''    <!-- Admin section (super_admin only) -->''',
    '''    <!-- Primary section -->
    <div class="yt-sidebar-section">
        <a href="{{ route('me.home') }}" class="yt-sidebar-link">
            <i class="bi bi-house-door-fill"></i>
            <span>{{ __('nav.home') }}</span>
        </a>
        <a href="{{ route('me.videos') }}" class="yt-sidebar-link is-active">
            <i class="bi bi-play-btn-fill"></i>
            <span>{{ __('personal.videos_title') }}</span>
        </a>
        <a href="{{ route('me.events') }}" class="yt-sidebar-link">
            <i class="bi bi-trophy-fill"></i>
            <span>{{ __('nav.events') }}</span>
        </a>
    </div>

    <!-- This event -->
    <div class="yt-sidebar-section">
        <div class="yt-sidebar-section-header">{{ $e['title'] }}</div>
        <a href="{{ $bout['gallery_url'] }}" class="yt-sidebar-link">
            <i class="bi bi-collection-play-fill"></i>
            <span>{{ __('events.bout_gallery_title') }}</span>
        </a>
        <a href="{{ $bout['bout_url'] }}" class="yt-sidebar-link">
            <i class="bi bi-card-list"></i>
            <span>{{ __('events.bout_card_court') }} {{ $bout['court'] }}</span>
        </a>
    </div>

    ''')

cut('''    <!-- YouTube-style Bottom Navigation Bar (Mobile) -->''', '''    </nav>''',
    '''    <!-- Bottom Navigation Bar (Mobile) -->
    <nav class="yt-bottom-nav">
        <a href="{{ route('me.home') }}" class="yt-bottom-nav-item">
            <i class="bi bi-house-door-fill"></i>
            <span>{{ __('nav.home') }}</span>
        </a>
        <a href="{{ route('me.videos') }}" class="yt-bottom-nav-item">
            <i class="bi bi-play-btn-fill"></i>
            <span>{{ __('personal.videos_title') }}</span>
        </a>
        <a href="{{ $bout['gallery_url'] }}" class="yt-bottom-nav-item">
            <i class="bi bi-collection-play-fill"></i>
            <span>{{ __('events.bout_gallery_title') }}</span>
        </a>
        <a href="{{ route('me.events') }}" class="yt-bottom-nav-item">
            <i class="bi bi-trophy-fill"></i>
            <span>{{ __('nav.events') }}</span>
        </a>
        <a href="{{ route('me.profile') }}" class="yt-bottom-nav-item">
            <i class="bi bi-person-fill"></i>
            <span>{{ __('nav.tab_profile') }}</span>
        </a>
    </nav>''')

# ─────────────────────────────────────────────────────────────────────────────
# 3. The player and the data behind it.
#    The design reads three prepared structures — MSB_STATE for the on-video
#    scoreboard, window.matchRounds / window.matchReviews for the highlights
#    panel — so wiring is a matter of handing it ours.
# ─────────────────────────────────────────────────────────────────────────────

# There is no view-progress ledger on this platform; dropping the attribute is
# how the design's own heartbeat turns itself off (`if (!_hbUrl) return`).
rep('''<div class="ytp-wrap " id="ytpWrap"
     data-progress-url="https://video.takeone.bh/videos/11vu7R/view-progress"
     data-video-id="193">''',
    '''<div class="ytp-wrap " id="ytpWrap"
     data-video-id="{{ $bout['match_no'] }}">''')

rep('''const HLS_URL   = "https:\\/\\/video.takeone.bh\\/videos\\/11vu7R\\/hls\\/playlist.m3u8";
const MP4_URL   = "https:\\/\\/video.takeone.bh\\/videos\\/11vu7R\\/stream?v=1786441009";''',
    '''const HLS_URL   = @json($video['hls'] ?? null);
const MP4_URL   = @json($video['mp4'] ?? null);''')

rep('''window._ytpMasterHls  = "https:\\/\\/video.takeone.bh\\/videos\\/11vu7R\\/hls\\/playlist.m3u8";
window._ytpMasterMp4  = "https:\\/\\/video.takeone.bh\\/videos\\/11vu7R\\/stream?v=1786441009";''',
    '''window._ytpMasterHls  = @json($video['hls'] ?? null);
window._ytpMasterMp4  = @json($video['mp4'] ?? null);''')

# The on-video scoreboard.
_msb_line = [l for l in s.split('\n') if l.strip().startswith('const MSB_STATE  =')][0]
rep(_msb_line, '    const MSB_STATE  = @json($play[\'msb\'] ?? []);')
rep('    const VIDEO_ID   = 193;', '    const VIDEO_ID   = @json($bout[\'match_no\']);')

# The highlights panel's own cache. `videoId` is what every request URL below is
# built from — this page addresses a bout by its number inside an event, so the
# request helpers are rewritten further down and this stays a display value.
rep('''        window.videoId = "11vu7R";
        window.isOwner = false;''',
    '''        window.videoId = @json($bout['match_no']);
        // "Owner" here is the design's word for whoever may write on the
        // footage. That is the organiser and the two athletes who fought it —
        // the server decides, and re-decides on every write.
        window.isOwner = @json((bool) $canAnnotate);''')

_rounds_line  = [l for l in s.split('\n') if l.strip().startswith('window.matchRounds = [{')][0]
_reviews_line = [l for l in s.split('\n') if l.strip().startswith('window.matchReviews = [{')][0]
rep(_rounds_line,  '        window.matchRounds = @json($play[\'rounds\'] ?? []);')
rep(_reviews_line, '        window.matchReviews = @json($play[\'reviews\'] ?? []);')

# ─────────────────────────────────────────────────────────────────────────────
# 4. Where the page writes.
#
#    The design was built against Play's routes (/videos/{id}/…, /reviews/{id},
#    /comments/{id}). Every one of them is rewritten to this platform's, which
#    address a bout as a number inside an event and a note or a comment by its
#    uuid — never by a row id. One map, declared before anything reads it.
# ─────────────────────────────────────────────────────────────────────────────
rep('''<body class=" ">''',
    '''<body class=" ">
<script>
/*
 * The bout's own endpoints. Everything below builds its requests from here, so
 * there is exactly one place that knows what this page may ask the server for.
 *
 * A note and a comment travel on their UUID. The numeric `id` the design uses
 * in its markup is a position in the list it rendered — enough for its own
 * in-memory lookups, and deliberately not something that addresses a row.
 */
window.TOB = {
    csrf:        @json(csrf_token()),
    data:        @json(route('me.events.bout.video.data', ['event' => $e['key'], 'matchNo' => $bout['match_no']])),
    notes:       @json(route('me.events.bout.notes.store', ['event' => $e['key'], 'matchNo' => $bout['match_no']])),
    comments:    @json(route('me.events.bout.comments.store', ['event' => $e['key'], 'matchNo' => $bout['match_no']])),
    destroy:     @json($may_delete_video ? $delete_video_url : null),
    download:    @json($video['mp4'] ?? null),
    share:       @json(url()->current()),
    gallery:     @json($bout['gallery_url']),
    note:        function (uuid) { return this.notes + '/' + encodeURIComponent(uuid); },
    comment:     function (uuid) { return this.comments + '/' + encodeURIComponent(uuid); },
    commentLike: function (uuid) { return this.comment(uuid) + '/like'; },
    // A note's uuid from the position the design is holding.
    noteUuid:    function (id) {
        var r = (window.matchReviews || []).find(function (x) { return Number(x.id) === Number(id); });
        return r ? r.uuid : null;
    },
};
</script>''')

# The scoring log is the officiating record replayed against the footage — it is
# not typed here and cannot be edited here, so the design's round/point tools
# never render. Coach notes stay writable by whoever the server says may write.
rep('''        var videoId = window.videoId;
        var isOwner = window.isOwner;''',
    '''        var videoId = window.videoId;
        var isOwner = window.isOwner;
        // Rounds and points come from the mat console's own log. Nobody types
        // them on this page, so the design's editing affordances for them are
        // not rendered — only the coach-note ones, which `isOwner` still gates.
        window.canEditPoints = false;
        var canEditPoints = false;''')

rep('''\n            ${isOwner ? `''', '''\n            ${canEditPoints ? `''')
rep('''                        const actionsHtml = isOwner ? `''',
    '''                        const actionsHtml = canEditPoints ? `''')

rep("""            if (pointsContainer) pointsContainer.innerHTML =
                '<div style="text-align: center; padding: 40px; color: var(--text-secondary);">No match data yet. <button class="action-btn action-btn-primary" onclick="openAddRoundModal()">+ Add Round</button></div>';
            if (reviewsContainer) reviewsContainer.innerHTML =
                '<div style="text-align: center; padding: 40px; color: var(--text-secondary);">No coach notes yet. <button class="action-btn action-btn-primary" onclick="openAddReviewModal()">+ Add Note</button></div>';""",
    """            if (pointsContainer) pointsContainer.innerHTML =
                '<div style="text-align: center; padding: 40px; color: var(--text-secondary);">@lang('events.bout_video_no_points')</div>';
            if (reviewsContainer) reviewsContainer.innerHTML =
                '<div style="text-align: center; padding: 40px; color: var(--text-secondary);">@lang('events.bout_video_no_notes')</div>';""")

# ── The endpoints themselves ────────────────────────────────────────────────
rep("const response = await fetch(`/videos/${videoId}/match-data`);",
    "const response = await fetch(window.TOB.data, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' });")

# Coach notes: create and update.
rep("""                const url    = existingId ? `/reviews/${existingId}` : `/videos/${videoId}/reviews`;
                const method = existingId ? 'PUT' : 'POST';""",
    """                const uuid   = existingId ? window.TOB.noteUuid(existingId) : null;
                const url    = uuid ? window.TOB.note(uuid) : window.TOB.notes;
                const method = uuid ? 'PUT' : 'POST';""")

rep("""            const payload = { start_time_seconds: startSecs, end_time_seconds: endSecs, note, coach_name: coach, emoji };""",
    """            const payload = { start_seconds: startSecs, end_seconds: endSecs, note, coach_name: coach, emoji };""")

rep("""                const res = await fetch(`/reviews/${reviewId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    }
                });""",
    """                const uuid = window.TOB.noteUuid(reviewId);
                if (!uuid) { showToast('@lang('shared.something_went_wrong')', 'error'); return; }
                const res = await fetch(window.TOB.note(uuid), {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });""")

# The signed-in reality of this page: it is never reachable signed out, and who
# may write on the footage is the server's answer, not the browser's.
rep("""        const csrfToken = '';
        const isDemoMode = !false;""",
    """        const csrfToken = @json(csrf_token());
        const isDemoMode = @json(! (bool) $canAnnotate);""")

# ─────────────────────────────────────────────────────────────────────────────
# 5. No header strip under the picture.
#
#    The design put a crest, a title line, a meta line and a row of actions
#    between the player and the comments. Everything it said is already on this
#    page — the match card in the About panel names the bout, the athletes,
#    the division, the mat and the officials — so the strip was the same facts
#    twice, and its actions were Play's (subscribe, playlists, MP3).
#
#    Deleting a bout's footage went with it. That is not a capability lost:
#    the gallery tile each of these pages is opened from carries the same
#    control, for the same people, against the same endpoint.
# ─────────────────────────────────────────────────────────────────────────────
cut('''            <!-- ===== EVENT HEADER ===== -->''',
    '''            <!-- ===== END EVENT HEADER ===== -->''', '''''')

# ─────────────────────────────────────────────────────────────────────────────
# 6. The highlights drawer.
#
#    The two tab panels below it are re-rendered in the browser from
#    window.matchRounds / window.matchReviews, so they are handed over empty.
#    The portrait drawer is server-rendered only — these loops are its markup,
#    row for row as the design wrote it.
# ─────────────────────────────────────────────────────────────────────────────
cut('''                    <div class="fsh-list is-on" data-fsh-panel="points">''',
    '''                <div class="tab-header">''',
    '''                    <div class="fsh-list is-on" data-fsh-panel="points">
                        @forelse ($timeline['rounds'] as $round)
                            @php $moments = collect($timeline['moments'])->where('round', $round['number']); @endphp
                            <div class="fsh-rhead">
                                <span class="fsh-rname">{{ $round['name'] }}</span>
                                <span class="fsh-rcount">{{ $moments->count() }} {{ __('events.bout_video_moments') }}</span>
                                <span class="fsh-rrule"></span>
                            </div>

                            @foreach ($moments as $m)
                                <div class="fsh-row" data-mrv-seek="{{ $m['t'] }}">
                                    <span class="fsh-time">{{ $stamp($m['t']) }}</span>
                                    <span class="fsh-bar fsh-bar-{{ $m['side'] }}"></span>
                                    <span class="fsh-what">
                                        <span class="fsh-label">{{ $m['label'] }}</span>
                                        <span class="fsh-who">{{ $m['who'] }}</span>
                                    </span>
                                    <span class="fsh-run">
                                        <span class="fsh-run-red">{{ $m['score_red'] }}</span>
                                        <span class="fsh-run-sep">–</span>
                                        <span class="fsh-run-blue">{{ $m['score_blue'] }}</span>
                                    </span>
                                    <span></span>
                                </div>
                            @endforeach
                        @empty
                            <div class="fsh-rhead">
                                <span class="fsh-rname">{{ $timeline['anchored'] ? __('events.bout_video_no_points') : __('events.bout_video_no_anchor') }}</span>
                                <span class="fsh-rrule"></span>
                            </div>
                        @endforelse
                    </div>

                    <div class="fsh-list" data-fsh-panel="coach">
                        @foreach ($play['reviews'] ?? [] as $r)
                            <div class="fsh-note" data-mrv-seek="{{ $r['start_time_seconds'] }}"
                                 data-mrv-rev="{{ $r['id'] }}"
                                 @if ($r['end_time_seconds'] !== null) data-mrv-end="{{ $r['end_time_seconds'] }}" @endif>
                                <div class="fsh-note-top">
                                    <span class="fsh-note-time">{{ $clock($r['start_time_seconds']) }}</span>
                                    <span class="fsh-note-coach">{{ $r['coach_name'] }}</span>
                                    <span class="fsh-note-tag">{{ $r['emoji'] }}</span>
                                </div>
                                <div class="fsh-note-text">{{ $r['note'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="tab-header">''')

# The two desktop panels: filled by the design's own renderer on first paint.
cut('''                        <div class="event-list" id="officialEvents">''',
    '''                    <!-- Coach Review Tab -->''',
    '''                        <div class="event-list" id="officialEvents"></div>
                    </div>

                    <!-- Coach Review Tab -->''')

cut('''                        <div class="event-list" id="reviewEvents">''',
    '''            </aside>''',
    '''                        <div class="event-list" id="reviewEvents"></div>
                    </div>
                </div>
            </aside>''')

# ─────────────────────────────────────────────────────────────────────────────
# 7. No Up Next.
#
#    The design's rail carried Play's recommendations. This page is reached
#    FROM the event's gallery, which is the list of everything filmed there —
#    a second, shorter list of the same bouts beside the player is a worse copy
#    of the page the reader just came from. The whole container goes, including
#    its autoplay-next machinery; the one thing outside it that reaches in
#    (`if (window._plOnVideoEnd)`, when the video ends) is already guarded, so
#    with nothing defining it the video simply ends.
# ─────────────────────────────────────────────────────────────────────────────
cut('''            <!-- Sidebar - Up Next / Recommendations -->''',
    '''                    </script>
                            </div>''', '''''')

# ─────────────────────────────────────────────────────────────────────────────
# 8. The conversation under the bout.
#
#    The design's comment module is kept whole; what changes is what it talks
#    to and what it reads. Ours addresses a comment by uuid, has no edit
#    endpoint (so the affordance is not rendered), and draws a person as the
#    initials tile the rest of the platform uses rather than an avatar URL.
# ─────────────────────────────────────────────────────────────────────────────
rep('<span class="ytc-count" id="ytcCount">0 Comments</span>',
    '''<span class="ytc-count" id="ytcCount">{{ trans_choice('events.bout_video_comments_count', $commentTotal = collect($comments)->sum(fn ($c) => 1 + count($c['replies'] ?? [])), ['count' => $commentTotal]) }}</span>''')

# The login prompt was for a signed-out reader. This page has none: it is behind
# an authorisation guard, and whoever passed it may also speak.
cut('''        <div class="ytc-login-prompt">''', '''    <div id="ytcList">''',
    '''    <div class="ytc-new-form">
        <span class="ytc-avatar-link">
            <span class="ytc-avatar" style="background: {{ \\App\\Models\\BoutComment::tint(auth()->user()->full_name ?: auth()->user()->name) }}; display:grid; place-items:center; font-weight:700; font-size:13px; color:#fff;">{{ \\App\\Models\\BoutComment::initials(auth()->user()->full_name ?: auth()->user()->name) }}</span>
        </span>
        <div class="ytc-input-wrap">
            <textarea class="ytc-textarea" id="ytcTextarea" rows="1" maxlength="1000"
                      placeholder="{{ __('events.bout_video_comment_placeholder') }}"></textarea>
            <div class="ytc-form-actions" id="ytcFormActions">
                <button class="ytc-btn ytc-btn-cancel" id="ytcCancelBtn" type="button">{{ __('shared.cancel') }}</button>
                <button class="ytc-btn ytc-btn-submit" id="ytcSubmitBtn" type="button">{{ __('events.bout_video_comment_send') }}</button>
            </div>
        </div>
    </div>

    <div id="ytcList">''')

cut('''                    <div class="ytc-empty" id="ytcEmpty">''', '''            </div>
            </div>''',
    '''        @forelse ($comments as $c)
            @include('personal.mobile.partials.bout-comment', ['c' => $c, 'isReply' => false])
        @empty
            <div class="ytc-empty" id="ytcEmpty">
                <svg viewBox="0 0 24 24"><path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2zm-2 12H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/></svg>
                <p>{{ __('events.bout_video_no_comments') }}</p>
            </div>
        @endforelse
    </div>''')

rep("""const YTC = {
    videoId:   '11vu7R',
    csrf:      'RdCfRnlLyiSrkOj5ZPJJiUa25ESl04p9ZAUTENir',
    userId:    0,
    deleteId:  null,
    toastTimer: null,
};""",
    """const YTC = {
    videoId:   window.TOB && window.TOB.comments,
    csrf:      window.TOB ? window.TOB.csrf : '',
    // Truthy for anyone who reached this page: it is not reachable signed out.
    userId:    @json((int) auth()->id()),
    deleteId:  null,
    toastTimer: null,
};""")

# A person, drawn the way the rest of the platform draws one.
rep("""function avatarUrl(user) {
    if (!user) return 'https://video.takeone.bh/images/default-avatar.svg';
    return user.avatar_url || 'https://video.takeone.bh/images/default-avatar.svg';
}""",
    """function avatarTile(c, isReply) {
    const bg = (c && c.bg) || '#374151';
    const initials = esc((c && c.initials) || '?');
    return '<span class="ytc-avatar-link"><span class="ytc-avatar' + (isReply ? ' ytc-avatar-sm' : '') +
        '" style="background:' + esc(bg) + ';display:grid;place-items:center;font-weight:700;font-size:13px;color:#fff;">' +
        initials + '</span></span>';
}""")

rep("""    const isOwn  = c.user_id === YTC.userId;
    const avatar = avatarUrl(c.user);
    const name   = esc(c.user?.name || 'User');
    const time   = c.created_at ? 'just now' : '';
    const body   = esc(c.body || '');
    const id     = c.id;""",
    """    const isOwn  = !!c.mine;
    const name   = esc(c.name || 'User');
    const time   = esc(c.when || '');
    const body   = esc(c.text || '');
    const id     = c.key;""")

# No edit endpoint on this platform, so no edit affordance.
rep("""            <div class="ytc-more-menu">
                <button class="ytc-more-item _comment-edit-trigger" data-comment-id="${id}">
                    <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z"/></svg>
                    Edit
                </button>
                <button""",
    """            <div class="ytc-more-menu">
                <button""")

rep("""    const editForm = isOwn ? `
        <div class="ytc-edit-form" id="commentEditWrap${id}" style="display:none">
            <textarea class="ytc-edit-textarea" id="commentEditInput${id}" rows="2">${body}</textarea>
            <div class="ytc-edit-actions">
                <span class="ytc-edit-hint">Press Esc to cancel</span>
                <button class="ytc-btn ytc-btn-cancel _comment-cancel-edit-trigger" data-comment-id="${id}">Cancel</button>
                <button class="ytc-btn ytc-btn-submit _comment-save-edit-trigger" data-comment-id="${id}">Save</button>
            </div>
        </div>` : '';""",
    """    const editForm = '';""")

rep("""        <a class="ytc-avatar-link">
            <img src="${avatar}" class="ytc-avatar${isReply ? ' ytc-avatar-sm' : ''}" alt="${name}">
        </a>
        <div class="ytc-body-wrap">""",
    """        ${avatarTile(c, isReply)}
        <div class="ytc-body-wrap">""")

# ── Comment endpoints ───────────────────────────────────────────────────────
rep("""        const r = await fetch('/videos/' + YTC.videoId + '/comments', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': YTC.csrf },
            body: JSON.stringify({ body: text })
        });""",
    """        const r = await fetch(window.TOB.comments, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': YTC.csrf, 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ body: text })
        });""")

rep("""        const r = await fetch('/videos/' + vid + '/comments', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': YTC.csrf },
            body: JSON.stringify({ body: text, parent_id: parentId })
        });""",
    """        const r = await fetch(window.TOB.comments, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': YTC.csrf, 'Accept': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ body: text, parent: parentId })
        });""")

rep("""            const r = await fetch('/comments/' + id, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': YTC.csrf, 'Content-Type': 'application/json' }
            });""",
    """            const r = await fetch(window.TOB.comment(id), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': YTC.csrf, 'Accept': 'application/json' },
                credentials: 'same-origin',
            });""")

# A uuid is not a number: the old parseInt turned every delete target into NaN.
rep("if (dt) { e.stopPropagation(); openDeleteModal(parseInt(dt.dataset.commentId)); return; }",
    "if (dt) { e.stopPropagation(); openDeleteModal(dt.dataset.commentId); return; }")

rep("""        const r = await fetch('/comments/' + id + '/like', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': YTC.csrf, 'Accept': 'application/json' },
            credentials: 'same-origin',
        });""",
    """        const r = await fetch(window.TOB.commentLike(id), {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': YTC.csrf, 'Accept': 'application/json' },
            credentials: 'same-origin',
        });""")

repall("btn.dataset.count = d.count;", "btn.dataset.count = d.likes;")
repall("if (countEl) countEl.textContent = d.count > 0 ? d.count : '';",
       "if (countEl) countEl.textContent = d.likes > 0 ? d.likes : '';")


# ─────────────────────────────────────────────────────────────────────────────
# 9. The two pieces that carry the athletes: the VS intro over the player, and
#    the match card in the About panel (wide + narrow variants).
# ─────────────────────────────────────────────────────────────────────────────
VS_HEAD = '''<div id="vsScreen" class="vs-screen" role="dialog" aria-label="Match introduction">'''

cut(VS_HEAD, '''<style>
/* ═══════════════════════════════════════════════════════════════════════
   Fixed 1920×1080 canvas''',
    '''@php
    // A face only where the athlete chose to show it — the same rule the
    // bracket and the board follow. Its absence is silent, never a label.
    $refereeName = collect($officials)->first()['name'] ?? null;
@endphp
<div id="vsScreen" class="vs-screen" role="dialog" aria-label="{{ __('events.bout_video_intro') }}">
    <button type="button" class="vs-skip" id="vsSkip" aria-label="{{ __('events.bout_video_skip') }}">
        {{ __('events.bout_video_skip') }} <span id="vsSkipTimer">6</span>
    </button>

    <div class="vs-stage">

        <div class="vs-panel vs-panel-red">
            <div class="vs-panel-photo vs-panel-photo-red"
                 @if ($red['photo']) style="background-image:url('{{ $red['photo'] }}')" @endif></div>
            <div class="vs-panel-scrim vs-panel-scrim-red"></div>
            <div class="vs-panel-gradient"></div>
        </div>

        <div class="vs-panel vs-panel-blue">
            <div class="vs-panel-photo vs-panel-photo-blue"
                 @if ($blue['photo']) style="background-image:url('{{ $blue['photo'] }}')" @endif></div>
            <div class="vs-panel-scrim vs-panel-scrim-blue"></div>
            <div class="vs-panel-gradient"></div>
        </div>

        <div class="vs-divider"></div>

        <div class="vs-info vs-info-red">
            <div class="vs-corner-tag vs-corner-tag-red">{{ Str::upper($red['corner_label']) }}</div>
            <div class="vs-flag-row vs-nullable-row">
                @if ($red['country'])<span class="vs-flag fi fi-{{ Str::lower($red['country']) }} vs-flag-slot"></span>@endif
                <div class="vs-country vs-country-red vs-nullable">{{ $red['country'] ? Str::upper($red['country']) : '' }}</div>
            </div>
            <div class="vs-name vs-nullable">{{ $red['name'] }}</div>
            <div class="vs-club-row vs-nullable-row">
                <div class="vs-club vs-nullable">{{ $red['club'] }}</div>
            </div>
        </div>

        <div class="vs-info vs-info-blue">
            <div class="vs-corner-tag vs-corner-tag-blue">{{ Str::upper($blue['corner_label']) }}</div>
            <div class="vs-flag-row vs-flag-row-r vs-nullable-row">
                @if ($blue['country'])<span class="vs-flag fi fi-{{ Str::lower($blue['country']) }} vs-flag-slot"></span>@endif
                <div class="vs-country vs-country-blue vs-nullable">{{ $blue['country'] ? Str::upper($blue['country']) : '' }}</div>
            </div>
            <div class="vs-name vs-nullable">{{ $blue['name'] }}</div>
            <div class="vs-club-row vs-club-row-r vs-nullable-row">
                <div class="vs-club vs-nullable">{{ $blue['club'] }}</div>
            </div>
        </div>

        <div class="vs-top">
            <div class="vs-top-event vs-nullable">{{ $e['title'] }}</div>
            <div class="vs-top-stage-row ">
                <div class="vs-top-stage-line vs-top-stage-line-l"></div>
                <div class="vs-top-stage">{{ $bout['round'] }}</div>
                <div class="vs-top-stage-line vs-top-stage-line-r"></div>
            </div>
            <div class="vs-top-weight vs-nullable">{{ $bout['division'] }}</div>
        </div>

        <div class="vs-center">
            <div class="vs-word">VS
                <div class="vs-shine-wrap"><div class="vs-shine"></div></div>
            </div>
        </div>

        <div class="vs-flash"></div>

        <div class="vs-bottom">
            <div class="vs-chip vs-nullable-chip" data-vs-empty="0">
                <span class="vs-chip-label">{{ __('events.bout_card_match') }}</span>
                <span class="vs-chip-value vs-nullable">{{ $bout['match_no'] }}</span>
            </div>
            <div class="vs-diamond vs-diamond-match-court"></div>
            <div class="vs-chip vs-nullable-chip" data-vs-empty="0">
                <span class="vs-chip-label">{{ __('events.bout_card_court') }}</span>
                <span class="vs-chip-value vs-nullable">{{ $bout['court'] }}</span>
            </div>
            <div class="vs-diamond vs-diamond-court-ref"></div>
            <div class="vs-chip vs-nullable-chip" data-vs-empty="0">
                <span class="vs-chip-label">{{ __('events.bout_card_referee') }}</span>
                <span class="vs-chip-value vs-chip-value-name vs-nullable">{{ $refereeName }}</span>
            </div>
        </div>
    </div>
</div>

<style>
/* ═══════════════════════════════════════════════════════════════════════
   Fixed 1920×1080 canvas''')

# ── The match card: wide (.mac) and narrow (.macm) ──────────────────────────
#    The markup moves out to `partials/bout-match-card.blade.php` so the three
#    tab systems can each include it; only its stylesheet stays here, which is
#    where the design put it and where one copy is all that is wanted.
cut('''<div class="mac-card">''', '''<style>
/* ── Match about card ─''', '''<style>
/* ── Match about card ─''')

# The full-bleed corrector measured the card against the About panel it no
# longer sits in. In a tab panel there is nothing to correct.
cut('''<script>
/*
 * Pull the mobile card flush to the inside of .vdb-wrap.''',
    '''})();
</script>''', '''''')

# ─────────────────────────────────────────────────────────────────────────────
# 10. What Play had and this platform does not.
#
#     Playlists, its fingerprinting script, its own delete-by-title dialog and
#     its member search all belong to the host that was disconnected. They are
#     removed rather than left pointing at it — a control that cannot work is a
#     dead end, and this page must never call that host.
# ─────────────────────────────────────────────────────────────────────────────
rep('''    <script src="https://video.takeone.bh/fp.js" async></script>
''', '')

cut('''    <!-- Add to Playlist Modal - Available for all users (shows login prompt if not authenticated) -->''',
    '''    <!-- Share Modal - Available on all pages -->''',
    '''    <!-- Share Modal - Available on all pages -->''')

# Play's delete dialog asked for the video's title and a 2FA code against its
# own endpoint. Ours is the two-step button beside the header, and the server
# is the only thing that decides who may press it.
cut('''        // Delete video modal functions''', '''    </script>''', '''    </script>''')

# The share sheet's "send to members" list was Play's user directory. The button
# that opens it is already hidden (no endpoint is passed), and this makes sure
# nothing can reach for that host even if it were shown.
cut('''                fetch('https://video.takeone.bh/users/search?q=' + encodeURIComponent(q), {''',
    '''                .catch(function () {});''',
    '''                if (memberResults) memberResults.style.display = 'none';''')


rep('const videoId = window.videoId || "11vu7R";', 'const videoId = window.videoId;')


# Play's stale session token, baked into the snapshot. Nothing here may carry a
# token from another host — and the one place still calling for it (channel
# subscriptions) does not exist on this platform.
cut('''        // Subscribe Toggle Function
        function toggleSubscribe(userId) {''', '''        // These live on `window` so SPA navigation''',
    '''        // These live on `window` so SPA navigation''')

repall("_token:  'RdCfRnlLyiSrkOj5ZPJJiUa25ESl04p9ZAUTENir',", "_token:  window.TOB.csrf,")
repall("body.append('_token', 'RdCfRnlLyiSrkOj5ZPJJiUa25ESl04p9ZAUTENir');", "body.append('_token', window.TOB.csrf);")


# ─────────────────────────────────────────────────────────────────────────────
# 12. The highlights page (.mrv) — four tabs.
#
#     This is what "Highlights" opens on a phone: the player moves into
#     .mrv-view and this pane becomes the page. The design shipped it with two
#     tabs and — because the snapshot was of somebody else's bout — with that
#     bout's rounds and notes baked into the markup. Both are fixed here: the
#     rows are ours, and the tabs are Match / Points / Coach review / Comments.
#
#     Match opens first. Somebody who taps Highlights on a bout they have just
#     found wants to know WHOSE bout it is before they read a scoring log.
# ─────────────────────────────────────────────────────────────────────────────
MRV = """                    <div class="mrv-tabs">
                        <button type="button" class="mrv-tab is-on" data-mrv-tab="match">{{ __('events.bout_video_tab_match') }}</button>
                        <button type="button" class="mrv-tab" data-mrv-tab="points">{{ __('events.bout_video_tab_points') }}</button>
                        <button type="button" class="mrv-tab" data-mrv-tab="coach">{{ __('events.bout_video_tab_coach') }}</button>
                        <button type="button" class="mrv-tab" data-mrv-tab="comments">{{ __('events.bout_video_tab_comments') }}</button>
                    </div>

                    <div class="mrv-pane mrv-pane-flush is-on" data-mrv-panel="match">
                        @include('personal.mobile.partials.bout-match-card')
                    </div>

                    <div class="mrv-pane" data-mrv-panel="points">
                        <div class="mrv-scroll">
                            @forelse ($timeline['rounds'] as $round)
                                @php $moments = collect($timeline['moments'])->where('round', $round['number']); @endphp
                                <div class="mrv-rhead">
                                    <div class="mrv-rhead-l">
                                        <span class="mrv-rname">{{ $round['name'] }}</span>
                                        <span class="mrv-rcount">{{ $moments->count() }} {{ __('events.bout_video_moments') }}</span>
                                    </div>
                                </div>

                                @foreach ($moments as $m)
                                    <div class="hlp-row mrv-hlp" data-mrv-seek="{{ $m['t'] }}">
                                        <button type="button" class="hlp-time mrv-hlp-time" data-mrv-seek="{{ $m['t'] }}">{{ $stamp($m['t']) }}</button>
                                        <span class="hlp-bar hlp-bar-{{ $m['side'] }}"></span>
                                        <span class="hlp-what">
                                            <span class="hlp-label">{{ $m['label'] }}</span>
                                            <span class="hlp-who">{{ $m['who'] }}</span>
                                        </span>
                                        <span class="hlp-run">
                                            <span class="hlp-run-red">{{ $m['score_red'] }}</span>
                                            <span class="hlp-run-sep">–</span>
                                            <span class="hlp-run-blue">{{ $m['score_blue'] }}</span>
                                        </span>
                                        <span></span>
                                    </div>
                                @endforeach
                            @empty
                                <div class="mrv-rhead">
                                    <div class="mrv-rhead-l">
                                        <span class="mrv-rname">{{ $timeline['anchored'] ? __('events.bout_video_no_points') : __('events.bout_video_no_anchor') }}</span>
                                    </div>
                                </div>
                            @endforelse
                        </div>
                    </div>

                    <div class="mrv-pane" data-mrv-panel="coach">
                        <div class="mrv-scroll">
                            @forelse ($play['reviews'] ?? [] as $r)
                                <div class="mrv-note-card"
                                     data-mrv-seek="{{ $r['start_time_seconds'] }}"
                                     data-mrv-rev="{{ $r['id'] }}"
                                     @if ($r['end_time_seconds'] !== null) data-mrv-end="{{ $r['end_time_seconds'] }}" @endif>
                                    <div class="mrv-note-top">
                                        <span class="mrv-ava">{{ \\App\\Models\\BoutComment::initials($r['coach_name']) }}</span>
                                        <span class="mrv-who">
                                            <span class="mrv-coach">{{ $r['coach_name'] }}</span>
                                            <button type="button" class="mrv-at" data-mrv-seek="{{ $r['start_time_seconds'] }}">{{ $clock($r['start_time_seconds']) }}</button>
                                        </span>
                                        <span class="mrv-tag">{{ $r['emoji'] }}</span>
                                    </div>
                                    <div class="mrv-note-text">{{ $r['note'] }}</div>
                                </div>
                            @empty
                                <div class="mrv-rhead">
                                    <div class="mrv-rhead-l">
                                        <span class="mrv-rname">{{ __('events.bout_video_no_notes') }}</span>
                                    </div>
                                </div>
                            @endforelse

                            @if ($canAnnotate)
                                <button type="button" class="mrv-at" style="margin-top:12px;" onclick="beginReviewCapture()">
                                    + {{ __('events.bout_video_add_note') }}
                                </button>
                            @endif
                        </div>
                    </div>

                    {{-- The conversation lives in ONE place in the document and is
                         moved into whichever comments slot the reader opened; see
                         the mount helper further down. --}}
                    <div class="mrv-pane" data-mrv-panel="comments">
                        <div class="mrv-scroll" data-bout-comments-slot></div>
                    </div>
                </div>
        </div>

        <!-- Sidebar - Match Highlights -->"""

cut('                    <div class="mrv-tabs">', '        <!-- Sidebar - Match Highlights -->', MRV)


# ─────────────────────────────────────────────────────────────────────────────
# 13. The fullscreen drawer (.fsh) — the same four tabs.
#
#     A tab that exists on the portrait page and not in the drawer is exactly
#     the drift this codebase keeps paying for. Same names, same order, same
#     comments slot.
# ─────────────────────────────────────────────────────────────────────────────
FSH_HEAD = """                    <div class="fsh-head">
                        <button type="button" class="fsh-tab is-on" data-fsh-tab="match">{{ __('events.bout_video_tab_match') }}</button>
                        <button type="button" class="fsh-tab" data-fsh-tab="points">{{ __('events.bout_video_tab_points') }}</button>
                        <button type="button" class="fsh-tab" data-fsh-tab="coach">{{ __('events.bout_video_tab_coach') }}</button>
                        <button type="button" class="fsh-tab" data-fsh-tab="comments">{{ __('events.bout_video_tab_comments') }}</button>
                        <button type="button" class="fsh-x" aria-label="{{ __('shared.close') }}"
                                onclick="document.getElementById('matchHighlightsToggle')?.click()">&times;</button>
                    </div>

                    <div class="fsh-list is-on" data-fsh-panel="match">
                        @include('personal.mobile.partials.bout-match-card')
                    </div>

                    <div class="fsh-list" data-fsh-panel="points">"""

cut('                    <div class="fsh-head">', '                    <div class="fsh-list is-on" data-fsh-panel="points">', FSH_HEAD)

FSH_TAIL = """                        @endforeach
                        @if ($canAnnotate)
                            <button type="button" class="fsh-note" style="width:100%;border:0;font:inherit;color:#8ab4f8;text-align:center;cursor:pointer;"
                                    onclick="beginReviewCapture()">+ {{ __('events.bout_video_add_note') }}</button>
                        @endif
                    </div>

                    <div class="fsh-list" data-fsh-panel="comments">
                        <div data-bout-comments-slot></div>
                    </div>
                </div>

                <div class="tab-header">"""

cut("""                        @endforeach
                    </div>
                </div>

                <div class="tab-header">""", '<div class="tab-header">', FSH_TAIL)


# ─────────────────────────────────────────────────────────────────────────────
# 14. The wide pane — the same four tabs again.
#
#     The "+ Note" control is deliberately NOT a .tab-button any more: the
#     design's tab handler clears every panel for whatever .tab-button was
#     clicked, so a button in that row with no `data-tab` blanked the pane.
# ─────────────────────────────────────────────────────────────────────────────
PANE = """                <div class="tab-header">
                    <button class="tab-button active" data-tab="match">{{ __('events.bout_video_tab_match') }}</button>
                    <button class="tab-button" data-tab="official">{{ __('events.bout_video_tab_points') }}</button>
                    <button class="tab-button" data-tab="review">{{ __('events.bout_video_tab_coach') }}</button>
                    <button class="tab-button" data-tab="comments">{{ __('events.bout_video_tab_comments') }}</button>

                    <button type="button" class="hl-fs-close" aria-label="{{ __('shared.close') }}"
                            onclick="document.getElementById('matchHighlightsToggle')?.click()">&times;</button>
                </div>
                <div class="tab-panels">
                    <!-- Match Tab -->
                    <div class="tab-panel active" id="tab-match">
                        @include('personal.mobile.partials.bout-match-card')
                    </div>

                    <!-- Points Tab -->
                    <div class="tab-panel" id="tab-official">
                        <div class="event-list" id="officialEvents"></div>
                    </div>

                    <!-- Coach Review Tab -->
                    <div class="tab-panel" id="tab-review">
                        <div class="event-list" id="reviewEvents"></div>
                        @if ($canAnnotate)
                            <button type="button" class="event-action-btn" style="margin-top:12px;"
                                    onclick="beginReviewCapture()">+ {{ __('events.bout_video_add_note') }}</button>
                        @endif
                    </div>

                    <!-- Comments Tab -->
                    <div class="tab-panel" id="tab-comments">
                        <div data-bout-comments-slot></div>
                    </div>
                </div>
            </aside>"""

cut('                <div class="tab-header">', '            </aside>', PANE)


# ─────────────────────────────────────────────────────────────────────────────
# 15. The About box under the video goes.
#
#     Its one panel is now the "Match" tab, so what is left below the player is
#     a tab strip with nothing behind it and Play's own view count and
#     description. The wrapper stays in the document, emptied and hidden,
#     because the match card's stylesheet lives inside it — a <style> element
#     applies whether or not its ancestors are drawn.
# ─────────────────────────────────────────────────────────────────────────────
cut('''<div class="vdb-wrap" id="vdbWrap">''',
    '''    <div class="vdb-panel active" id="vdb-about">''',
    '''{{-- Emptied: its content is the "Match" tab now. Kept, and hidden, so the
     stylesheet it carries still reaches the card wherever that tab renders. --}}
<div class="vdb-wrap" id="vdbWrap" style="display:none" aria-hidden="true">
    <div class="vdb-panel active" id="vdb-about">''')

cut('''                <div class="vdb-meta">''', '''            </div>

    </div>''', '''    </div>
</div>''')


# ─────────────────────────────────────────────────────────────────────────────
# 16. The conversation becomes a tab.
#
#     There is one comment module in the document — one #ytcList, one #ytcCount,
#     one set of handlers — and three tab systems that could show it. So it is
#     parked in a hidden home and MOVED into whichever comments slot the reader
#     opened. Moving the node keeps every listener and every id intact, which
#     rendering it three times would not.
# ─────────────────────────────────────────────────────────────────────────────
rep('''            <div class="ytc" id="ytcSection">''',
    '''            <div id="ytcHome" style="display:none">
            <div class="ytc" id="ytcSection">''')

# Close the home wrapper where the comment module ends.
rep('''<div class="ytc-modal" id="ytcDeleteModal">''',
    '''</div>

<div class="ytc-modal" id="ytcDeleteModal">''')

BOUT_TABS = """<script>
/*
 * Tabs, across the three panes that have them.
 *
 * Two jobs, both small:
 *
 *   • Move the one comment module into the comments slot of whichever pane the
 *     reader is looking at. Appending a node re-parents it — listeners, scroll
 *     position and ids all survive, which is why there is one module and not
 *     three.
 *
 *   • Keep a control that is NOT a tab out of the tab handlers. The design's
 *     own handlers act on any .tab-button / .fsh-tab that is clicked, so an
 *     "+ Note" button wearing that class blanked the pane. They are guarded
 *     here, in the capture phase, so the check happens before those handlers
 *     see the event rather than being duplicated inside each of them.
 */
(function () {
    if (window.__boutTabsInit) return;
    window.__boutTabsInit = true;

    function mount(slot) {
        var sec = document.getElementById('ytcSection');
        if (sec && slot && sec.parentElement !== slot) slot.appendChild(sec);
    }

    document.addEventListener('click', function (e) {
        var t = e.target.closest('[data-tab], [data-mrv-tab], [data-fsh-tab]');
        if (!t) return;
        var name = t.dataset.tab || t.dataset.mrvTab || t.dataset.fshTab;
        if (name !== 'comments') return;
        // The pane this tab belongs to owns the slot the module goes into.
        var pane = t.closest('.tab-header, .mrv-tabs, .fsh-head');
        var root = pane ? pane.parentElement : document;
        // The handler that reveals the panel runs after this one, so wait a
        // frame before measuring anything that depends on it being visible.
        requestAnimationFrame(function () {
            mount(root.querySelector('[data-bout-comments-slot]'));
        });
    });

    // A button in a tab row that carries no tab name is an action, not a tab.
    document.addEventListener('click', function (e) {
        var b = e.target.closest('.tab-button, .fsh-tab, .mrv-tab');
        if (!b) return;
        if (b.dataset.tab || b.dataset.mrvTab || b.dataset.fshTab) return;
        e.stopPropagation();
    }, true);
})();
</script>

    </main>"""

rep("""    </main>""", BOUT_TABS)


# ─────────────────────────────────────────────────────────────────────────────
# 17. No site chrome. The bout IS the page.
#
#     The header, the drawer it opened and the bottom tab bar were Play's shell.
#     This page is opened from the gallery and goes back to it; a second set of
#     navigation stacked on top of the app's own is noise, and on a phone it
#     costs 112px of a screen whose whole job is to show a video.
#
#     Their scripts go with them. Two of those were live faults rather than dead
#     weight: `restoreSidebarState()` and the resize handler dereference the
#     drawer without checking it exists, and a document-wide click listener
#     called a `closeNotifPanel()` that the snapshot never defined — so every
#     tap on the page threw.
# ─────────────────────────────────────────────────────────────────────────────
cut('<header class="yt-header">', '</header>', '')
cut('    <!-- Sidebar Overlay (Mobile) -->', '</nav>', '')
cut('    <!-- Bottom Navigation Bar (Mobile) -->', '</nav>', '')
cut('        // Mobile search toggle function', '    </script>', '    </script>')

# The page body was inset by 56px top and bottom to clear chrome that is no
# longer there.
rep('</head>', """<style>
    /* This page carries no header and no tab bar, so nothing is reserved for
       them. Written here rather than by editing the design's own rules, which
       stay as they were handed over. */
    #main.yt-main { margin-top: 0 !important; margin-left: 0 !important; min-height: 100vh; }

    @media (max-width: 991px) {
        #main.yt-main { top: 0 !important; bottom: 0 !important; }
    }

    /* The Highlights chip exists for fullscreen and nowhere else.
       On the page the pane is not a drawer — it IS the page under the video —
       so a control that "opens" it would toggle a state the reader is already
       in. In fullscreen it genuinely is a drawer over the picture, and there
       is no other way to reach it. */
    .match-highlights-toggle { display: none !important; }
    .ytp-wrap.ytp-fullscreen .match-highlights-toggle { display: flex !important; }

    /* The match card IS the Match tab.
       Every pane that holds it gives up its padding and its background, so the
       card meets all four edges instead of sitting in a lighter box inside a
       darker one. It scrolls itself rather than through a wrapper. */
    .mrv-pane-flush { padding: 0 !important; overflow-y: auto; -webkit-overflow-scrolling: touch; overscroll-behavior: contain; }

    /* A card taller than its pane was being SQUEEZED rather than scrolled.
       These panes are column flex containers, and a flex item shrinks before it
       overflows — so the card compressed to the available height and clipped
       its own contents, with nothing for the scroller to scroll. Telling the
       card to keep its natural height gives the pane something to scroll. */
    .mrv-pane-flush > *,
    .fsh-list[data-fsh-panel="match"] > *,
    #tab-match > * { flex: 0 0 auto !important; }
    .mrv-pane-flush,
    .fsh-list[data-fsh-panel="match"],
    #tab-match { padding: 0 !important; background: #000 !important; }

    /* One official per row.
       Several names wrapping across a line read as a single run of text; a
       column gives each their own line, which is how a scoresheet lists them. */
    .macm-refs,
    .mac-refs { flex-direction: column !important; align-items: flex-start !important; }
    .macm-foot,
    .mac-foot { align-items: flex-start !important; }

    /* The portrait beside each name. 3:4 already, from the design — but it was
       sized for several officials sharing a line. On its own row it has the
       width, so it grows to the height of the label-plus-name beside it. */
    .macm-ref-pic { width: 10cqw !important; }
    .mac-ref-pic { width: 5.6cqw !important; }

    /* The name beside it grows with it. The design's type was cut for a portrait
       a little over half this size, sharing a line with four other officials;
       against the larger picture on its own row it read as a caption. */
    .macm-ref { gap: 3cqw !important; }
    .macm-ref-lbl { font-size: 2.9cqw !important; letter-spacing: .55cqw !important; }
    .macm-ref-name { font-size: 4.6cqw !important; }
    .macm-ref-txt { gap: .6cqw !important; }

    /* ── The review tabs ──────────────────────────────────────────────────
       The design's row was page-sized type sitting directly under a video: a
       14px label per tab, four of them, competing with the picture above.
       These are a segmented control instead — small, capitalised, on their own
       dark rail, with the active one filled rather than underlined so it reads
       at a glance without a rule crossing the screen. */
    .mrv-tabs {
        gap: 4px !important;
        padding: 8px 10px calc(8px + env(safe-area-inset-bottom, 0px)) !important;
        background: #0c0e11;
        border-bottom: 1px solid rgba(255, 255, 255, .06);
    }
    .mrv-tab {
        flex: 1 1 0 !important;
        min-width: 0;
        border: 0 !important;
        border-radius: 999px !important;
        padding: 7px 6px !important;
        background: rgba(255, 255, 255, .04) !important;
        color: #7b8494 !important;
        font-size: 11px !important;
        font-weight: 600 !important;
        letter-spacing: .04em;
        line-height: 1.2;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        transition: background .15s ease, color .15s ease;
    }
    .mrv-tab.is-on {
        background: #e8eaed !important;
        color: #0c0e11 !important;
        font-weight: 700 !important;
    }

    /* ── The Points list ──────────────────────────────────────────────────
       One line per moment, as the design draws it, full width: the scroller
       gives up its side padding and the row takes it, so the tap target and
       the divider under it run edge to edge rather than floating in a channel.

       The running score is what a reader scans for, so it is the one large
       thing on the row. The timestamp stays small beside it — it is a label
       for the tap, not the headline. Its column sizes to its own content
       rather than the design's fixed 44px, which was cut for MM:SS and is too
       narrow for the millisecond stamp. */
    .mrv-pane[data-mrv-panel="points"] .mrv-scroll {
        padding-left: 0 !important;
        padding-right: 0 !important;
    }
    .mrv-hlp {
        grid-template-columns: auto 4px 1fr auto auto !important;
        border-radius: 0 !important;
        padding: 12px calc(14px + env(safe-area-inset-right)) 12px calc(14px + env(safe-area-inset-left)) !important;
    }
    .mrv-hlp-time {
        font-size: 11px !important;
        font-weight: 600 !important;
        font-variant-numeric: tabular-nums;
        /* The row's grid gap is one value for every column, so the breathing
           room between the stamp and the corner bar is a margin on the stamp
           rather than a wider gap everywhere. */
        margin-right: 8px !important;
    }
    .mrv-hlp .hlp-run {
        font-size: 22px !important;
        font-weight: 700 !important;
        line-height: 1;
        gap: 5px !important;
        align-items: baseline;
        font-variant-numeric: tabular-nums;
    }
    .mrv-hlp .hlp-run-sep { font-size: 14px; }

    /* The corner bar reads as the row's colour, so it runs most of the row's
       height rather than sitting as a short tick beside it. */
    .mrv-hlp .hlp-bar { height: 32px !important; }

    /* The round header cancelled the scroller's side padding with a matching
       negative margin — and that padding is gone now, so it was hanging 16px
       off the left edge. It gets its own indent instead, a little deeper than
       the rows beneath it so it reads as the heading of the group. */
    .mrv-pane[data-mrv-panel="points"] .mrv-rhead {
        margin-left: 0 !important;
        margin-right: 0 !important;
        padding-left: calc(18px + env(safe-area-inset-left)) !important;
        padding-right: calc(18px + env(safe-area-inset-right)) !important;
    }

    .macm,
    .mac-card,
    .mac-shell {
        margin: 0 !important;
        width: 100% !important;
        max-width: none !important;
        border-left: 0 !important;
        border-right: 0 !important;
        border-radius: 0 !important;
        background: #000 !important;
    }
</style>
</head>""")


# ─────────────────────────────────────────────────────────────────────────────
# 18. It opens ON the highlights view.
#
#     The design treated the review page as something you toggle into, and
#     deliberately never restored it on a phone. Here it is the destination: a
#     bout opened from the gallery lands on the player with its Match / Points /
#     Coach review / Comments tabs already under it, rather than on a bare
#     player with a chip to find first.
#
#     Same code path as the toggle — nothing new to keep in step — and the
#     toggle still closes it.
# ─────────────────────────────────────────────────────────────────────────────
rep("""                        if (localStorage.getItem(`highlights_${videoId}`) === 'open' && !isMobile()) {
                            setOpen(true);
                        }""",
    """                        setOpen(true);""")


# ─────────────────────────────────────────────────────────────────────────────
# 19. No Highlights toggle.
#
#     The page opens on the highlights view and stays there, so a chip that
#     turns it on is a control for a state the reader is already in. It goes,
#     and the two "×" controls that closed the view go with it: without the chip
#     there was nothing to bring it back, which is the shape of dead end this
#     codebase has been bitten by before.
#
#     The initialiser was written around that button — it bailed out entirely if
#     it was missing, taking the auto-open with it. It now keys off the SIDEBAR,
#     which is the thing it actually sets up, and treats the button as optional
#     so a design that brings it back needs no change here.
# ─────────────────────────────────────────────────────────────────────────────
cut('''    <button class="match-highlights-toggle" id="matchHighlightsToggle" title="Toggle Match Highlights">''',
    '''</button>''', '')

rep('''                    if (toggleBtn && toggleBtn._hlBound && sidebar && toggleBtn._hlSidebar === sidebar) {
                        return; // Already bound to this exact sidebar node — nothing to do
                    }

                    if (toggleBtn && sidebar) {
                        // Wipe any prior click listener bound to a stale sidebar
                        if (toggleBtn._hlHandler) toggleBtn.removeEventListener('click', toggleBtn._hlHandler);
                        toggleBtn._hlBound = true;
                        toggleBtn._hlSidebar = sidebar;''',
    '''                    // The flag lives on the SIDEBAR, which is what this sets up.
                    // A fresh node after an SPA swap carries none, so it rebinds;
                    // the same node twice does nothing. The toggle button is
                    // optional — this page does not render one.
                    if (sidebar && sidebar._hlBound) {
                        return; // Already set up for this exact sidebar node
                    }

                    if (sidebar) {
                        // Wipe any prior click listener bound to a stale sidebar
                        if (toggleBtn && toggleBtn._hlHandler) toggleBtn.removeEventListener('click', toggleBtn._hlHandler);
                        sidebar._hlBound = true;''')

rep('''                            toggleBtn.classList.toggle('expanded', open);''',
    '''                            toggleBtn?.classList.toggle('expanded', open);''')

rep('''                        toggleBtn._hlHandler = () => setOpen(!sidebar.classList.contains('show'));
                        toggleBtn.addEventListener('click', toggleBtn._hlHandler);''',
    '''                        if (toggleBtn) {
                            toggleBtn._hlHandler = () => setOpen(!sidebar.classList.contains('show'));
                            toggleBtn.addEventListener('click', toggleBtn._hlHandler);
                        }''')

rep('''                                    toggleBtn.classList.add('expanded');''',
    '''                                    toggleBtn?.classList.add('expanded');''')

# The two close controls, now that nothing could reopen the view.
cut('''                        <button type="button" class="fsh-x" aria-label="{{ __('shared.close') }}"''',
    '''&times;</button>''', '')
cut('''                    <button type="button" class="hl-fs-close" aria-label="{{ __('shared.close') }}"''',
    '''&times;</button>''', '')


# ─────────────────────────────────────────────────────────────────────────────
# 20. Fullscreen: a way into the drawer, and no way out but the button.
#
#     Fullscreen is the one place the highlights pane is a DRAWER over the
#     picture rather than the page itself, so it needs a control — the page's
#     own layout has none because the pane is always open there. The design's
#     chip comes back for exactly that state and is invisible everywhere else.
#
#     And nothing on this page may drop fullscreen behind the user's back. The
#     one thing left that could was the mini player: it works by adopting the
#     <video> element into a floating box, and re-parenting the element that IS
#     the fullscreen element makes the browser exit immediately. It now refuses
#     while fullscreen is on.
# ─────────────────────────────────────────────────────────────────────────────
rep('''    <video id="videoPlayer" playsinline preload="auto" autoplay muted></video>''',
    '''    <video id="videoPlayer" playsinline preload="auto" autoplay muted></video>

    {{-- Only ever seen in fullscreen; see the rule in the page's override
         block. The initialiser treats it as optional either way. --}}
    <button class="match-highlights-toggle" id="matchHighlightsToggle"
            title="{{ __('events.bout_video_tab_highlights') }}">
        <span>{{ __('events.bout_video_tab_highlights') }}</span>
    </button>''')

rep('''        function activate(title, url, mode) {
            if (_mode !== null) return false; /* already active — prevent re-entry */''',
    '''        function activate(title, url, mode) {
            if (_mode !== null) return false; /* already active — prevent re-entry */
            /*
             * Never while fullscreen. This works by adopting the <video> into a
             * floating box, and re-parenting the element that IS the fullscreen
             * element makes the browser leave fullscreen on the spot. Fullscreen
             * ends when the viewer says it ends, not because something else on
             * the page wanted the player.
             */
            if (document.fullscreenElement || document.webkitFullscreenElement) return false;''')

# ─────────────────────────────────────────────────────────────────────────────
# 99. Write it out.
# ─────────────────────────────────────────────────────────────────────────────
io.open(OUT, 'w', encoding='utf-8').write(s)
left = s.count('video.takeone.bh')
print('written:', OUT, '| lines:', s.count('\n') + 1, '| video.takeone.bh refs left:', left)
