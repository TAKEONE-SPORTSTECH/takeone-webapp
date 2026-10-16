{{--
    The entry list, as pages to print — App\Events\Support\ParticipantsPdf feeds
    this to headless Chromium (through BracketPdf::render) and returns what it
    prints. Four editions: `list` (the entry list), `checkin` (the desk sheet),
    `checkin_club` (the desk sheet, one block per club, its Club column turned
    into the divisions each athlete entered) and `all` (everybody once, A–Z,
    drawn with the entry list's columns).

    The draw PDF's "Fight night" look — navy and gold, Barlow Condensed, a gold
    diagonal under every band, square edges and hairlines — so the two read as
    one set on the organiser's desk.

    Pagination is done on the server in millimetres, so every row here is a
    FIXED height: a name too long for its column is set smaller until it fits
    (`.pfit`, at the foot), never wrapped or cut.

    Every picture arrives inlined and re-authorised; this page never talks to
    the server and is never served over HTTP. Inline styles throughout: it is a
    print document, and the Tailwind bundle is prebuilt.
--}}
@php
    $c = '#d8b25f';
    $navy = '#16213d';
    $goldInk = '#a8862f';   // gold dark enough to read as type on white
    $rtl = app()->getLocale() === 'ar';
    $byClub = $kind === 'checkin_club';
    $check = $kind === 'checkin' || $byClub;
    $total = count($pages);
    $heading = __('events::events.participants_pdf_'.($kind === 'all' ? 'all_title' : $kind));
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ $heading }}</title>
    {{-- The same two outside sheets the draw PDF loads (grandfathered in
         CLAUDE.md → Self-Host Everything); the flags are read from disk. --}}
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,600;0,700;0,800;0,900;1,800;1,900&family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ $flagCss }}">
    @include('events::participants-pdf-style')
</head>
<body>
    @foreach($pages as $pi => $page)
        <section class="pdf-page">
            <div class="band">
                <span class="stripes"></span>
                @if($hostLogo)<span class="hlogo" role="img" aria-label="{{ $host }}"></span>@endif
                <div class="who">
                    <div class="eye">{{ $title }}</div>
                    <h1>{{ $heading }}</h1>
                    @if($date || $host)
                        <div class="sub">{{ collect([$date, $host])->filter()->implode(' · ') }}</div>
                    @endif
                </div>
                @if($pi === 0)
                    <div class="totals">
                        <div><b>{{ $totals['athletes'] }}</b><span>{{ __('events::events.draw_print_athletes') }}</span></div>
                        @if($totals['clubs'])<div><b>{{ $totals['clubs'] }}</b><span>{{ __('events::events.draw_print_clubs') }}</span></div>@endif
                        <div><b>{{ $totals['divisions'] }}</b><span>{{ __('events::events.draw_print_divisions') }}</span></div>
                    </div>
                @endif
                <span class="slab"></span>
            </div>

            <div class="body">
                @foreach($page as $d)
                    @php $grid = $check ? ('grid-checkin'.($byClub ? ' by-club' : '')) : 'grid-list'; @endphp
                    <div class="dv">
                        <div class="dv-h">
                            @if($byClub)
                                @if($d['logo'] ?? null)<img class="lg" src="{{ $d['logo'] }}" alt="">@endif
                            @else
                                <span class="g {{ $d['gender'] }}"></span>
                            @endif
                            <span class="nm pfit">{{ $d['name'] }}</span>
                            @if($d['continued'])<span class="cont">· {{ __('events::events.draw_print_continued') }}</span>@endif
                            <span class="n">{{ $d['total'] }}</span>
                        </div>
                        <div class="dv-c {{ $grid }}">
                            @if($check)
                                <span class="ctr">{{ __('events::events.participants_pdf_col_here') }}</span>
                                <span></span>
                                <span></span>
                                <span>{{ __('events::events.participants_pdf_col_athlete') }}</span>
                                <span>{{ __('events::events.participants_pdf_col_'.($byClub ? 'division' : 'club')) }}</span>
                                <span class="ctr">{{ __('events::events.participants_pdf_col_gender') }}</span>
                                <span class="ctr">{{ __('events::events.participants_pdf_col_age') }}</span>
                                <span class="ctr">{{ __('events::events.participants_pdf_col_belt') }}</span>
                                <span class="ctr">{{ __('events::events.participants_pdf_col_weight') }}</span>
                                <span class="ctr">{{ __('events::events.participants_pdf_col_paid') }}</span>
                            @else
                                <span></span>
                                <span></span>
                                <span>{{ __('events::events.participants_pdf_col_athlete') }}</span>
                                <span>{{ __('events::events.participants_pdf_col_club') }}</span>
                                <span class="ctr">{{ __('events::events.participants_pdf_col_gender') }}</span>
                                <span class="ctr">{{ __('events::events.participants_pdf_col_age') }}</span>
                                <span class="ctr">{{ __('events::events.participants_pdf_col_weight') }}</span>
                                <span class="ctr">{{ __('events::events.participants_pdf_col_belt') }}</span>
                            @endif
                        </div>
                        @foreach($d['people'] as $i => $p)
                            <div class="r {{ $grid }}">
                                @if($check)
                                    <span class="box"></span>
                                @endif
                                <span class="no">{{ $d['start'] + $i + 1 }}</span>
                                <span class="ph">
                                    @if($p['photo'])<img src="{{ $p['photo'] }}" alt="">@else<img class="none" src="{{ $placeholders[$p['gender']] }}" alt="">@endif
                                </span>
                                <div class="who-c">
                                    <div class="an pfit">@if($p['country'])<span class="fi fi-{{ $p['country'] }}"></span>@endif<bdi>{{ $p['name'] }}</bdi></div>
                                    @if(! $check && $p['stage'])
                                        <div class="as">{{ $p['stage'] }}</div>
                                    @endif
                                </div>
                                @if($byClub)
                                <div class="dvn">
                                    @forelse($p['divnames'] as $dn)<span class="pfit"><bdi>{{ $dn }}</bdi></span>@empty<span style="color:#b4bac7">—</span>@endforelse
                                </div>
                                @else
                                <div class="cl">
                                    @if($p['club'])
                                        @if($p['club_logo'])<img src="{{ $p['club_logo'] }}" alt="">@endif
                                        <span class="pfit"><bdi>{{ $p['club'] }}</bdi></span>
                                    @else
                                        <span style="color:#b4bac7">—</span>
                                    @endif
                                </div>
                                @endif
                                <span class="sx {{ $p['sex'] ?? 'none' }}">
                                    @if($p['sex'])<i class="bi bi-gender-{{ $p['sex'] }}"></i>{{ __('events::events.participants_pdf_gender_'.$p['sex']) }}@else—@endif
                                </span>
                                <span class="ag">{{ $p['age'] ?? '—' }}</span>
                                @unless($check)
                                    <span class="ag">@if($p['weight'] !== null){{ $p['weight'] }}<small class="kg">kg</small>@else—@endif</span>
                                @endunless
                                <span class="bt">@if($p['belt'])<x-belt-icon :belt="$p['belt']" :title="false" />@endif</span>
                                @if($check)
                                    {{-- Only the reading in the box — the border says whose it is:
                                         solid gold weighed at the scale, dashed declared on the form. --}}
                                    <span class="wt {{ $p['weight'] === null ? '' : ($p['signed'] ? 'signed' : 'declared') }}">
                                        @if($p['weight'] !== null){{ $p['weight'] }}<small>kg</small>@endif
                                    </span>
                                    <span class="box {{ $p['paid'] ? 'on' : '' }}">@if($p['paid'])<i class="bi bi-check-lg"></i>@endif</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>

            <div class="foot">
                @if($check)
                    <span class="staff"><i class="bi bi-lock-fill"></i>{{ __('events::events.participants_pdf_staff_only') }}</span>
                    <span class="lg"><i></i>{{ __('events::events.participants_pdf_legend_signed') }}</span>
                    <span class="lg"><i class="dash"></i>{{ __('events::events.participants_pdf_legend_declared') }}</span>
                @else
                    <span>{{ $host }}</span>
                @endif
                <span class="pg">{{ $pi + 1 }} / {{ $total }}</span>
            </div>
        </section>
    @endforeach

    <script>
        // One line each, every row the same height: a long name or club is set
        // smaller until it fits its column, never wrapped or cut.
        document.fonts.ready.then(function () {
            document.querySelectorAll('.pfit').forEach(function (el) {
                var size = parseFloat(getComputedStyle(el).fontSize);
                while (el.scrollWidth > el.clientWidth + 0.5 && size > 5) { size -= 0.25; el.style.fontSize = size + 'px'; }
            });
        });
    </script>
</body>
</html>
