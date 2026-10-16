{{--
    The event's officials as a PDF (App\Events\Support\OfficialsPdf) — the
    participants PDF's page and styles (events::participants-pdf-style), with
    the officials' own columns: face, name and flag with their jobs under it,
    club, nationality and gender; the sign-in sheet adds a tick and a line to sign.
    Rows are a fixed height; the server paginates by it.
--}}
@php
    $c = '#d8b25f';
    $navy = '#16213d';
    $goldInk = '#a8862f';
    $rtl = app()->getLocale() === 'ar';
    $sign = $kind === 'signin';
    $byClub = $kind === 'by_club';
    $total = count($pages);
    $heading = __('events::events.officials_pdf_'.$kind);
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · {{ $heading }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,600;0,700;0,800;0,900;1,800;1,900&family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="file://{{ public_path('images/flags/flag-icons.css') }}">
    @include('events::participants-pdf-style')
    <style>
        .grid-off { grid-template-columns: 6mm 7.5mm minmax(0, 1fr) 40mm 26mm 15mm; }
        .grid-sign { grid-template-columns: 7mm 6mm 7.5mm minmax(0, 1fr) 34mm 38mm 14mm; }
        .r.grid-off, .r.grid-sign { height: 12.5mm; }
        .grid-off .an, .grid-sign .an { font-size: 9.5pt; }
        .grid-off .cl, .grid-sign .cl { font-size: 7.2pt; }
        .jb { font-size: 7pt; color: #8a6d20; font-weight: 700; line-height: 1.35; white-space: nowrap; overflow: hidden; }
        .nat { font-size: 7.4pt; font-weight: 600; color: #1e2c4f; white-space: nowrap; overflow: hidden; }
        .sig { height: 7mm; border-bottom: .35mm solid #8a93a8; }
        .tm { height: 6mm; border: .3mm solid #cfd5e0; border-radius: .6mm; }
    </style>
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
                        <div><b>{{ $totals['officials'] }}</b><span>{{ __('events::events.officials_pdf_officials') }}</span></div>
                        @if($totals['clubs'])<div><b>{{ $totals['clubs'] }}</b><span>{{ __('events::events.draw_print_clubs') }}</span></div>@endif
                        <div><b>{{ $totals['jobs'] }}</b><span>{{ __('events::events.officials_pdf_jobs') }}</span></div>
                    </div>
                @endif
                <span class="slab"></span>
            </div>

            <div class="body">
                @foreach($page as $d)
                    @php $grid = $sign ? 'grid-sign' : 'grid-off'; @endphp
                    <div class="dv">
                        <div class="dv-h">
                            @if($byClub && ($d['logo'] ?? null))<img class="lg" src="{{ $d['logo'] }}" alt="">@else<span class="g"></span>@endif
                            <span class="nm pfit">{{ $d['name'] }}</span>
                            @if($d['continued'])<span class="cont">· {{ __('events::events.draw_print_continued') }}</span>@endif
                            <span class="n">{{ $d['total'] }}</span>
                        </div>
                        <div class="dv-c {{ $grid }}">
                            @if($sign)<span class="ctr">{{ __('events::events.participants_pdf_col_here') }}</span>@endif
                            <span></span>
                            <span></span>
                            <span>{{ __('events::events.officials_pdf_col_official') }}</span>
                            <span>{{ __('events::events.participants_pdf_col_club') }}</span>
                            @if($sign)
                                <span>{{ __('events::events.officials_pdf_col_signature') }}</span>
                                <span class="ctr">{{ __('events::events.officials_pdf_col_time') }}</span>
                            @else
                                <span>{{ __('events::events.officials_pdf_col_nationality') }}</span>
                                <span class="ctr">{{ __('events::events.participants_pdf_col_gender') }}</span>
                            @endif
                        </div>
                        @foreach($d['people'] as $i => $p)
                            <div class="r {{ $grid }}">
                                @if($sign)<span class="box"></span>@endif
                                <span class="no">{{ $d['start'] + $i + 1 }}</span>
                                <span class="ph">
                                    @if($p['photo'])<img src="{{ $p['photo'] }}" alt="">@else<img class="none" src="{{ $placeholders[$p['gender']] }}" alt="">@endif
                                </span>
                                <div class="who-c">
                                    <div class="an pfit">@if($p['country'])<span class="fi fi-{{ $p['country'] }}"></span>@endif<bdi>{{ $p['name'] }}</bdi></div>
                                    @if($p['jobs'])<div class="jb pfit"><bdi>{{ implode(' · ', $p['jobs']) }}</bdi></div>@endif
                                </div>
                                <div class="cl">
                                    @if($p['club'])
                                        @if($p['club_logo'])<img src="{{ $p['club_logo'] }}" alt="">@endif
                                        <span class="pfit"><bdi>{{ $p['club'] }}</bdi></span>
                                    @else
                                        <span style="color:#b4bac7">{{ __('events::events.officials_pdf_independent') }}</span>
                                    @endif
                                </div>
                                @if($sign)
                                    <span class="sig"></span>
                                    <span class="tm"></span>
                                @else
                                    <span class="nat pfit">{{ $p['nationality'] ?? '—' }}</span>
                                    <span class="sx {{ $p['sex'] ?? 'none' }}">
                                        @if($p['sex'])<i class="bi bi-gender-{{ $p['sex'] }}"></i>{{ __('events::events.participants_pdf_gender_'.$p['sex']) }}@else—@endif
                                    </span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>

            <div class="foot">
                @if($sign)
                    <span class="staff"><i class="bi bi-pen"></i>{{ __('events::events.officials_pdf_sign_note') }}</span>
                @else
                    <span>{{ $host }}</span>
                @endif
                <span class="pg">{{ $pi + 1 }} / {{ $total }}</span>
            </div>
        </section>
    @endforeach

    <script>
        document.fonts.ready.then(function () {
            document.querySelectorAll('.pfit').forEach(function (el) {
                var size = parseFloat(getComputedStyle(el).fontSize);
                while (el.scrollWidth > el.clientWidth + 0.5 && size > 5) { size -= 0.25; el.style.fontSize = size + 'px'; }
            });
        });
    </script>
</body>
</html>
