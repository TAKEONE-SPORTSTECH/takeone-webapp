{{--
    The event's Results tab as a PDF (App\Federations\Services\FederationResultsPdf)
    — the draw report's sheet (events::participants-pdf-style: navy band, gold
    slab, A4 pages) with two parts: the final results, one block per division
    with its podium; and the bout list, one block per mat, every bout numbered.
    Rows are a fixed height; the server paginates by them.
--}}
@php
    $c = '#d8b25f';
    $navy = '#16213d';
    $goldInk = '#a8862f';
    $rtl = app()->getLocale() === 'ar';
    $offset = count($clubPages);
    $total = $offset + count($pages);
    $people = collect($clubPages)->flatten(1);
@endphp
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:ital,wght@0,600;0,700;0,800;0,900;1,800;1,900&family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="{{ $flagCss }}">
    @include('events::participants-pdf-style')
    <style>
        .grid-res { grid-template-columns: 26mm 7.5mm minmax(0, 1fr) 62mm; }
        .grid-bout { grid-template-columns: 7mm 36mm minmax(0, 1fr) 7mm 7mm minmax(0, 1fr) 20mm; }
        .grid-pend { grid-template-columns: minmax(0, 1fr) 24mm; }
        .r.grid-res { height: 12.5mm; }
        .r.grid-bout { height: 11mm; }
        .r.grid-pend { height: 7mm; }
        .r.grid-res.p1 { background: #fbf6e9; }
        .grid-res .an { font-size: 10pt; }
        .grid-res .cl { font-size: 7.6pt; }
        .pl { display: flex; align-items: center; gap: 1.6mm; font-size: 7pt; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; color: {{ $goldInk }}; white-space: nowrap; }
        .pl svg { flex: 0 0 auto; }
        .dv-h .g { width: 2.4mm; height: 2.4mm; border-radius: 50%; background: #9aa3b2; flex: 0 0 auto; }
        .dv-h .g.male { background: #2563eb; } .dv-h .g.female { background: #db2777; }
        .dv-h .ic { color: {{ $goldInk }}; font-size: 10pt; }

        /* A bout: the two corners face each other across the score. */
        .bdv { min-width: 0; display: flex; flex-direction: column; gap: .3mm; }
        .bdv .dn { display: flex; align-items: center; gap: 1.2mm; font-size: 7pt; font-weight: 800; color: {{ $navy }}; white-space: nowrap; overflow: hidden; line-height: 1.35; }
        .bdv .dn i { width: 1.8mm; height: 1.8mm; border-radius: 50%; background: #9aa3b2; flex: 0 0 auto; }
        .bdv .dn i.male { background: #2563eb; } .bdv .dn i.female { background: #db2777; }
        .bdv .rd { font-size: 6.4pt; font-weight: 700; color: #8a93a8; text-transform: uppercase; letter-spacing: .06em; white-space: nowrap; overflow: hidden; }
        .cn { min-width: 0; display: flex; align-items: center; gap: 1.6mm; }
        .cn .bar { width: 1.2mm; height: 7mm; border-radius: .6mm; flex: 0 0 auto; }
        .cn .bar.red { background: #dc2626; } .cn .bar.blue { background: #2563eb; }
        .cn .t { min-width: 0; }
        .cn .an { font-size: 8.6pt; line-height: 1.45; }
        .cn .as { display: flex; align-items: center; gap: 1.2mm; white-space: nowrap; overflow: hidden; text-transform: uppercase; letter-spacing: .03em; font-size: 6.4pt; }
        .cn .as img { width: 3.6mm; height: 3.6mm; object-fit: contain; flex: 0 0 auto; }
        .cn .an .fi { width: 3.6mm; height: 2.7mm; }
        .cn.lost .an { color: #9aa3b2; font-weight: 600; }
        .cn.won .an::after { content: '\2713'; color: #2f7a4f; margin-inline-start: 1mm; }
        .cn.empty .an { color: #b4bac7; font-weight: 600; }
        .sc { text-align: center; font-family: 'Barlow Condensed', 'Inter', sans-serif; font-weight: 900; font-size: 13pt; color: {{ $navy }}; font-variant-numeric: tabular-nums; }
        .sc.lost { color: #b4bac7; }
        .how { justify-self: end; font-size: 6.8pt; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; white-space: nowrap;
               padding: 1mm 2mm; border-radius: .6mm; border: .3mm solid #cfd5e0; color: #6b7590; }
        .how.done { border-color: {{ $c }}; color: {{ $goldInk }}; }
        .pn { display: flex; align-items: center; gap: 1.6mm; font-size: 8.4pt; font-weight: 700; color: {{ $navy }}; white-space: nowrap; overflow: hidden; }
        .pn i { width: 2mm; height: 2mm; border-radius: 50%; background: #9aa3b2; flex: 0 0 auto; }
        .pn i.male { background: #2563eb; } .pn i.female { background: #db2777; }
        .pa { text-align: end; font-size: 7pt; font-weight: 700; color: #8a93a8; text-transform: uppercase; letter-spacing: .06em; }
        @include('events::pdf-club-style')
        @include('federations::partials.club-faces-style')
        .as .kg { flex: 0 0 auto; color: {{ $navy }}; font-weight: 800; text-transform: none; font-variant-numeric: tabular-nums; }
        .as .kg::before { content: '\00b7'; margin-inline-end: 1mm; color: #9aa3b2; }
        .an .kg { flex: 0 0 auto; margin-inline-start: 1.6mm; padding: .2mm 1.6mm; border-radius: 3mm; background: #eef1f6; color: {{ $navy }};
                  font-size: 6.6pt; font-weight: 800; letter-spacing: .02em; font-variant-numeric: tabular-nums; }
        .empty-note { margin-top: 8mm; padding: 8mm; border: .35mm dashed #cfd5e0; border-radius: 1.5mm; text-align: center; font-size: 10pt; color: #6b7590; }
    </style>
</head>
<body>
    @if($coverImage)
        {{-- The organiser's cover page — stretched over the whole A4 page, never numbered. --}}
        <section class="pdf-page" style="background:#fff">
            <img src="{{ $coverImage }}" alt="" style="display:block;width:100%;height:100%;object-fit:fill">
        </section>
    @endif

    {{-- ═══ The clubs: who came with each, and the medals it won ═══ --}}
    @foreach($clubPages as $ci => $cp)
        <section class="pdf-page">
            <div class="band">
                <span class="stripes"></span>
                @if($hostLogo)<span class="hlogo" role="img" aria-label="{{ $host }}"></span>@endif
                <div class="who">
                    <div class="eye">{{ $title }}</div>
                    <h1>{{ __('federations::messages.results_pdf_clubs') }}</h1>
                    @if($date || $host)
                        <div class="sub">{{ collect([$date, $host])->filter()->implode(' · ') }}</div>
                    @endif
                </div>
                @if($ci === 0)
                    <div class="totals">
                        <div><b>{{ $people->reject(fn ($b) => $b['continued'] || $b['none'])->count() }}</b><span>{{ __('events::events.draw_print_clubs') }}</span></div>
                        <div><b>{{ $people->sum(fn ($b) => count($b['people'])) }}</b><span>{{ __('events::events.draw_print_athletes') }}</span></div>
                    </div>
                @endif
                <span class="slab"></span>
            </div>
            <div class="body">
                @include('events::pdf-club-blocks', ['clubs' => $cp, 'sheetIds' => collect()])
            </div>
            <div class="foot">
                <span>{{ $host }}</span>
                <span class="pg">{{ $ci + 1 }} / {{ $total }}</span>
            </div>
        </section>
    @endforeach

    @foreach($pages as $pi => $page)
        @php $isBouts = $page['part'] === 'bouts'; @endphp
        <section class="pdf-page">
            <div class="band">
                <span class="stripes"></span>
                @if($hostLogo)<span class="hlogo" role="img" aria-label="{{ $host }}"></span>@endif
                <div class="who">
                    <div class="eye">{{ $title }}</div>
                    <h1>{{ __('federations::messages.results_pdf_'.($isBouts ? 'bouts' : 'results')) }}</h1>
                    @if($date || $host)
                        <div class="sub">{{ collect([$date, $host])->filter()->implode(' · ') }}</div>
                    @endif
                </div>
                @if($page['first'])
                    <div class="totals">
                        @if($isBouts)
                            <div><b>{{ $totals['bouts'] }}</b><span>{{ __('federations::messages.results_pdf_bouts_n') }}</span></div>
                            <div><b>{{ $totals['done'] }}</b><span>{{ __('federations::messages.results_pdf_decided') }}</span></div>
                        @else
                            <div><b>{{ $totals['divisions'] }}</b><span>{{ __('federations::messages.results_pdf_divisions') }}</span></div>
                            <div><b>{{ $totals['done'] }}</b><span>{{ __('federations::messages.results_pdf_bouts_n') }}</span></div>
                        @endif
                    </div>
                @endif
                <span class="slab"></span>
            </div>

            <div class="body">
                @if($page['blocks'] === [])
                    <div class="empty-note">{{ __('federations::messages.results_pdf_none_'.($isBouts ? 'bouts' : 'results')) }}</div>
                @endif

                @foreach($page['blocks'] as $d)
                    <div class="dv">
                        <div class="dv-h">
                            @if($d['type'] === 'bouts')
                                <i class="bi bi-geo-alt-fill ic"></i>
                            @elseif($d['type'] === 'pending')
                                <i class="bi bi-hourglass-split ic"></i>
                            @else
                                <span class="g {{ $d['gender'] }}"></span>
                            @endif
                            <span class="nm pfit">{{ $d['name'] }}</span>
                            @if($d['continued'])<span class="cont">· {{ __('events::events.draw_print_continued') }}</span>@endif
                            @if($d['type'] !== 'results')<span class="n">{{ $d['total'] }}</span>@endif
                        </div>

                        @if($d['type'] === 'results')
                            <div class="dv-c grid-res">
                                <span>{{ __('federations::messages.results_pdf_col_place') }}</span>
                                <span></span>
                                <span>{{ __('federations::messages.results_pdf_col_athlete') }}</span>
                                <span>{{ __('federations::messages.results_pdf_col_club') }}</span>
                            </div>
                            @foreach($d['rows'] as $p)
                                <div class="r grid-res p{{ $p['place'] }}">
                                    <span class="pl"><x-medal-icon :place="$p['place']" :size="14" />{{ in_array($p['place'], [1, 2, 3], true) ? __('events::events.draw_print_place_'.$p['place']) : $p['place'] }}</span>
                                    <span class="ph">
                                        @if($p['photo'])<img src="{{ $p['photo'] }}" alt="">@else<img class="none" src="{{ $placeholder }}" alt="">@endif
                                    </span>
                                    <div class="an pfit">@if($p['flag'])<span class="fi fi-{{ $p['flag'] }}"></span>@endif<bdi>{{ $p['name'] }}</bdi>@if($p['kg'])<span class="kg">{{ $p['kg'] }} kg</span>@endif</div>
                                    <div class="cl">@if($p['club'])@if($p['logo'])<img src="{{ $p['logo'] }}" alt="">@endif<span class="pfit"><bdi>{{ $p['club'] }}</bdi></span>@else<span style="color:#b4bac7">—</span>@endif</div>
                                </div>
                            @endforeach
                        @elseif($d['type'] === 'pending')
                            <div class="dv-c grid-pend">
                                <span>{{ __('federations::messages.results_pdf_col_division') }}</span>
                                <span style="text-align:end">{{ __('events::events.draw_print_athletes') }}</span>
                            </div>
                            @foreach($d['rows'] as $p)
                                <div class="r grid-pend">
                                    <span class="pn"><i class="{{ $p['gender'] }}"></i><bdi>{{ $p['name'] }}</bdi></span>
                                    <span class="pa">{{ $p['athletes'] }}</span>
                                </div>
                            @endforeach
                        @else
                            <div class="dv-c grid-bout">
                                <span>#</span>
                                <span>{{ __('federations::messages.results_pdf_col_division') }}</span>
                                <span>{{ __('federations::messages.results_pdf_col_red') }}</span>
                                <span></span>
                                <span></span>
                                <span>{{ __('federations::messages.results_pdf_col_blue') }}</span>
                                <span style="text-align:end">{{ __('federations::messages.results_pdf_col_result') }}</span>
                            </div>
                            @foreach($d['rows'] as $m)
                                <div class="r grid-bout">
                                    <span class="no">{{ $m['no'] }}</span>
                                    <div class="bdv">
                                        <div class="dn"><i class="{{ $m['gender'] }}"></i><span class="pfit" style="min-width:0;overflow:hidden">{{ $m['division'] }}</span></div>
                                        <div class="rd">{{ $m['round'] }}</div>
                                    </div>
                                    @foreach(['a' => 'red', 'b' => 'blue'] as $k => $corner)
                                        @php $p = $m[$k]; $state = ! $p ? 'empty' : ($m['w'] === $k ? 'won' : ($m['w'] ? 'lost' : '')); @endphp
                                        @if($k === 'b')
                                            <span class="sc {{ $m['w'] === 'b' ? 'lost' : '' }}">{{ $m['sa'] ?? '' }}</span>
                                            <span class="sc {{ $m['w'] === 'a' ? 'lost' : '' }}">{{ $m['sb'] ?? '' }}</span>
                                        @endif
                                        <div class="cn {{ $state }}">
                                            <span class="bar {{ $corner }}"></span>
                                            <div class="t">
                                                <div class="an pfit">@if($p['flag'] ?? null)<span class="fi fi-{{ $p['flag'] }}"></span>@endif<bdi>{{ $p['name'] ?? '—' }}</bdi></div>
                                                @if($p && ($p['club'] || $p['kg']))<div class="as">@if($p['logo'] && $p['club'])<img src="{{ $p['logo'] }}" alt="">@endif<span class="pfit" style="min-width:0;overflow:hidden"><bdi>{{ $p['club'] }}</bdi></span>@if($p['kg'])<span class="kg">{{ $p['kg'] }} kg</span>@endif</div>@endif
                                            </div>
                                        </div>
                                    @endforeach
                                    <span class="how {{ $m['done'] ? 'done' : '' }}">{{ $m['how'] }}</span>
                                </div>
                            @endforeach
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="foot">
                <span>{{ $host }}</span>
                <span class="pg">{{ $offset + $pi + 1 }} / {{ $total }}</span>
            </div>
        </section>
    @endforeach

    <script>
        document.fonts.ready.then(function () {
            document.querySelectorAll('.pfit, .ath.with-face .n').forEach(function (el) {
                var size = parseFloat(getComputedStyle(el).fontSize);
                while (el.scrollWidth > el.clientWidth + 0.5 && size > 5) { size -= 0.25; el.style.fontSize = size + 'px'; }
            });
        });
    </script>
</body>
</html>
