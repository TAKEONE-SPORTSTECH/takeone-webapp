{{--
    An official's badge — A6 (105 × 148 mm) upright, or turned (Support\Formats, `badge`).
    Same data as the certificate view (CertificateService::renderDocument → renderView),
    the same shared helpers (Support\PageKit) and the same organiser's texts and erased
    areas; only the page and its elements differ:

      headline  the event · title  what the card says it is ("OFFICIAL")
      photo     the face, 3:4, cut to fill its box · name · role on a band in the event's
      colour · club  who they came with · qr  the code a steward scans · meta  the number
--}}
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
<meta charset="utf-8">
@php
    $orientation = \Ahl\Certificates\Support\Layout::orientation($orientation ?? 'portrait');
    [$pageW, $pageH] = $page ?? \Ahl\Certificates\Support\Layout::page($orientation);
    $L = $layout ?? \Ahl\Certificates\Support\Layout::resolve(null, false, $rtl, $orientation);
    $dx = $offset['x'] ?? 0;
    $dy = $offset['y'] ?? 0;
    $raster = $raster ?? false;
    $frameInk = $accent;

    $face = [
        'headline' => $rtl ? ['Amiri', 'bold', 'normal', 'Amiri-Bold.ttf'] : ['Inter', 'bold', 'normal', 'Inter-Bold.ttf'],
        'title' => $rtl ? ['Amiri', 'bold', 'normal', 'Amiri-Bold.ttf'] : ['InterSemi', 'normal', 'normal', 'Inter-SemiBold.ttf'],
        'name' => $rtl ? ['Amiri', 'bold', 'normal', 'Amiri-Bold.ttf'] : ['Inter', 'bold', 'normal', 'Inter-Bold.ttf'],
        'role' => $rtl ? ['Amiri', 'bold', 'normal', 'Amiri-Bold.ttf'] : ['Inter', 'bold', 'normal', 'Inter-Bold.ttf'],
        'club' => $rtl ? ['Amiri', 'normal', 'normal', 'Amiri-Regular.ttf'] : ['Inter', 'normal', 'normal', 'Inter-Regular.ttf'],
    ];
    $arFace = [
        'headline' => ['Amiri', 'bold', 'normal', 'Amiri-Bold.ttf'], 'title' => ['Amiri', 'bold', 'normal', 'Amiri-Bold.ttf'],
        'name' => ['Amiri', 'bold', 'normal', 'Amiri-Bold.ttf'], 'role' => ['Amiri', 'bold', 'normal', 'Amiri-Bold.ttf'],
        'club' => ['Amiri', 'normal', 'normal', 'Amiri-Regular.ttf'],
    ];
    // The card's title and role read as spaced capitals in Latin script, as a badge does.
    $tracking = $rtl ? [] : ['title' => 2.4, 'role' => 1.6];

    $kit = new \Ahl\Certificates\Support\PageKit($L, $rtl, $raster, $shaper, $dx, $dy, $face, $arFace, $tracking);
    $s = $kit->s(...);
    $isAr = $kit->isAr(...);
    $fam = $kit->fam(...);
    $lh = $kit->lh(...);
    $pt = $kit->pt(...);
    $shows = $kit->shows(...);
    $hug = $kit->hug(...);
    $imgRatio = $kit->imgRatio(...);
    $hugImage = $kit->hugImage(...);
    $band = $kit->band(...);
    $box = $kit->box(...);
    $valignShift = $kit->valignShift(...);
    $line = $kit->line(...);
    $caps = fn (string $k) => isset($tracking[$k]) ? sprintf(' text-transform:uppercase; letter-spacing:%spt;', $tracking[$k]) : '';
@endphp
<style>
{!! $fonts ?? '' !!}
@page { margin: 0; }
html, body { margin: 0; padding: 0; }
body { font-family: '{{ $rtl ? 'Amiri' : 'Inter' }}', 'DejaVu Sans', sans-serif; color: #1f1a2e; }
.page { position: relative; width: {{ $pageW }}mm; height: {{ $pageH - 0.5 }}mm; overflow: hidden; }
.bg { position: absolute; top: 0; left: 0; width: {{ $pageW }}mm; height: {{ $pageH }}mm; }
.frame { position: absolute; top: 3.5mm; left: 3.5mm; right: 3.5mm; bottom: 3.5mm; border: 0.9pt solid {{ $frameInk }}; border-radius: 3mm; }
.stamp { position: absolute; top: {{ $pageH / 2 - 10 }}mm; left: 0; right: 0; text-align: center; font-family: 'Inter'; font-weight: bold; font-size: 26pt; color: #b3261e; opacity: 0.22; transform: rotate(-14deg); }
</style>
</head>
<body>
<div class="page">
    @if($background_image)<img class="bg" src="{{ $background_image }}" alt="">@endif
    @if($decorated && $ornament !== 'off')<div class="frame"></div>@endif

    @if($shows('panel'))
        @php \Ahl\Certificates\Support\Drawn::put('panel', $L['panel']['x'] + $dx, $L['panel']['y'] + $dy, $L['panel']['w'], $L['panel']['h']); @endphp
        <div style="{{ $box('panel', sprintf('background:%s; opacity:%.2f; border-radius:3mm;', $L['panel']['ink'], $L['panel']['opacity'] ?? 0.82)) }}"></div>
    @endif

    @foreach(['logo_start' => 0, 'logo_end' => 1] as $lk => $li)
        @if($decorated && isset($logos[$li]) && $shows($lk))
            @php $hugImage($lk, $imgRatio($logos[$li])); @endphp
            <div style="{{ $box($lk) }}"><img src="{{ $logos[$li] }}" alt="" style="max-width: {{ $L[$lk]['w'] }}mm; max-height: {{ $L[$lk]['h'] }}mm;"></div>
        @endif
    @endforeach

    @if($shows('headline') && filled($headline ?? $issuer))
        {!! $line('headline', $s($headline ?? $issuer)) !!}
    @endif
    @if($shows('title') && filled($cert['title'] ?? null))
        {!! $line('title', $s($cert['title']), $isAr($cert['title']) ? '' : $caps('title')) !!}
    @endif

    {{-- The face fills its box (the host hands it over already cut to 3:4). --}}
    @if($shows('photo'))
        @php $hug('photo', $L['photo']['w'], $L['photo']['h']); @endphp
        <div style="{{ $box('photo', 'background:#ffffff; border:0.8pt solid '.$frameInk.'; border-radius:2mm; overflow:hidden;') }}">
            @if(! empty($cert['photo']))
                <img src="{{ $cert['photo'] }}" alt="" style="width: {{ $L['photo']['w'] }}mm; height: {{ $L['photo']['h'] }}mm;">
            @endif
        </div>
    @endif

    @if($shows('name') && filled($cert['name'] ?? null))
        {!! $line('name', $s($cert['name'])) !!}
    @endif

    {{-- The role on its band: a filled shape in the event's colour (Contrast leaves fills alone), white words. --}}
    @if($shows('role') && filled($cert['role'] ?? null))
        @php
            $rt = $s($cert['role']);
            $rf = $isAr($rt) && ! $rtl ? $arFace['role'] : $face['role'];
            $rtrk = $isAr($rt) ? 0 : ($tracking['role'] ?? 0);
            $rmeasure = $rtrk > 0 ? mb_strtoupper($rt) : $rt;
            $rpt = \Ahl\Certificates\Support\Fit::size($rmeasure, \Ahl\Certificates\Support\Fonts::path($rf[3]), $pt('role'), $L['role']['w'] - 8, $rtrk);
            $hug('role', $L['role']['w'], $L['role']['h']);
        @endphp
        <div style="{{ $box('role', 'background:'.$L['role']['ink'].'; border-radius:2mm; white-space:nowrap; text-align:center;') }}">
            <span style="color:#ffffff; font-family:'{{ $rf[0] }}'; font-weight:{{ $rf[1] }}; font-size:{{ $rpt }}pt; line-height:{{ $lh($rf[3], $rpt, $L['role']['h'] * 72 / 25.4) }}pt;{{ $rtrk > 0 ? ' text-transform:uppercase; letter-spacing:'.$rtrk.'pt;' : '' }}">{{ $rt }}</span>
        </div>
    @endif

    @if($shows('club') && filled($cert['club'] ?? null))
        {!! $line('club', $s($cert['club'])) !!}
    @endif

    {{-- The organiser's own texts ({name}, {role}, {club}, {event} filled in). --}}
    @include('certificates::pdf.partials.extras')

    @if(! empty($cert['qr']) && $shows('qr'))
        @php $q = min($L['qr']['w'], $L['qr']['h']); $hug('qr', $q, $q); @endphp
        <div style="{{ $box('qr') }}">
            <img src="{{ $cert['qr'] }}" alt="" style="width: {{ $q - 2 }}mm; height: {{ $q - 2 }}mm; padding: 1mm; background: #ffffff; border-radius: 1.5mm;">
        </div>
    @endif

    @if($shows('meta') && filled($cert['certificate_no'] ?? null))
        @php $hug('meta', $L['meta']['w'], $L['meta']['h']); @endphp
        <div style="{{ $box('meta', sprintf("font-family:'%s'; font-size:%.2fpt; line-height:%.2fpt; color:%s;", $fam($cert['certificate_no'], 'Inter'), $pt('meta'), $lh('Inter-Regular.ttf', $pt('meta'), $pt('meta') * 1.4), $L['meta']['ink'])) }}">
            <div style="{{ $band('meta') }}">{{ $s($cert['certificate_no']) }}</div>
        </div>
    @endif

    @if(! empty($cert['stamp']))<div class="stamp">{{ $s($cert['stamp']) }}</div>@endif
</div>
</body>
</html>
