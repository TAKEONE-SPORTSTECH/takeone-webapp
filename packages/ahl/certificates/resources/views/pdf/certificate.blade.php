{{--
    The certificate, A4 landscape or portrait ($orientation, $page = [w, h] mm), for dompdf. Data (CertificateService::renderView, then Host::viewData):
    $locale, $rtl, $ornament (full|minimal|off), $issuer, $headline?, $date, $date_secondary, $signatures[{name,title,image}],
    $cert[name,title,body,grade,certificate_no,qr,stamp,photo,place], $accent, $metal, $background, $background_image,
    $decorated, $offset[x,y] (mm), $logos[], $seal, $medal, $ribbon, $fonts, $shaper,
    $layout[element => {x,y,w,h (mm), scale, ink, align (start=left|center|end=right), hidden, moved}] (Support\Layout).

    Every element is an absolute box at the place the layout gives it, so the organiser can move
    and resize each one in the editor. dompdf has no flexbox, grid, gradients or bidi: patterns
    arrive as a background image (Support\Backgrounds), a one-line element is set smaller rather
    than cut when it is too long for its box (Support\Fit), and Arabic arrives already joined and
    in visual order (Support\TextShaper) — so no line of shaped text is left for dompdf to wrap.
--}}
<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
<meta charset="utf-8">
@php
    $t = fn ($k, $r = []) => __("certificates::certificates.$k", $r, $locale);
    $orientation = \Ahl\Certificates\Support\Layout::orientation($orientation ?? null);
    [$pageW, $pageH] = $page ?? \Ahl\Certificates\Support\Layout::page($orientation);
    $L = $layout ?? \Ahl\Certificates\Support\Layout::resolve(null, (bool) ($cert['place'] ?? null), $rtl, $orientation);
    $dx = $offset['x'] ?? 0;
    $dy = $offset['y'] ?? 0;
    $frameInk = $metal ?? $accent;
    $rule = $metal ?? '#c9a86a';
    $raster = $raster ?? false;

    // The fonts each element is set in, for the fit-to-box measurement and the CSS.
    $face = [
        'headline' => $rtl ? ['Amiri', 'normal', 'normal', 'Amiri-Regular.ttf'] : ['InterSemi', 'normal', 'normal', 'Inter-SemiBold.ttf'],
        'title' => $rtl ? ['Amiri', 'bold', 'normal', 'Amiri-Bold.ttf'] : ['Cormorant', 'normal', 'normal', 'CormorantGaramond-SemiBold.ttf'],
        'ribbon' => $rtl ? ['Amiri', 'bold', 'normal', 'Amiri-Bold.ttf'] : ['Inter', 'bold', 'normal', 'Inter-Bold.ttf'],
        'lead' => $rtl ? ['Amiri', 'normal', 'normal', 'Amiri-Regular.ttf'] : ['Cormorant', 'normal', 'italic', 'CormorantGaramond-MediumItalic.ttf'],
        'name' => $rtl ? ['Amiri', 'bold', 'normal', 'Amiri-Bold.ttf'] : ['Cormorant', 'bold', 'normal', 'CormorantGaramond-Bold.ttf'],
        'body' => $rtl ? ['Amiri', 'normal', 'normal', 'Amiri-Regular.ttf'] : ['Inter', 'normal', 'normal', 'Inter-Regular.ttf'],
    ];
    $arFace = [
        'headline' => ['Amiri', 'normal', 'normal', 'Amiri-Regular.ttf'], 'title' => ['Amiri', 'bold', 'normal', 'Amiri-Bold.ttf'],
        'ribbon' => ['Amiri', 'bold', 'normal', 'Amiri-Bold.ttf'], 'lead' => ['Amiri', 'normal', 'normal', 'Amiri-Regular.ttf'],
        'name' => ['Amiri', 'bold', 'normal', 'Amiri-Bold.ttf'], 'body' => ['Amiri', 'normal', 'normal', 'Amiri-Regular.ttf'],
    ];
    $caps = ! $rtl;   // headline and ribbon are letter-spaced capitals in Latin script only
    $tracking = ['headline' => $caps ? 2.4 : 0, 'ribbon' => $caps ? 2 : 0];

    // The drawing helpers every page view shares (Support\PageKit).
    $kit = new \Ahl\Certificates\Support\PageKit($L, $rtl, $raster, $shaper, $dx, $dy, $face, $arFace, $tracking);
    $s = $kit->s(...);
    $isAr = $kit->isAr(...);
    $fam = $kit->fam(...);
    $lh = $kit->lh(...);
    $pt = $kit->pt(...);
    $align = $kit->align(...);
    $shows = $kit->shows(...);
    $hug = $kit->hug(...);
    $imgRatio = $kit->imgRatio(...);
    $hugImage = $kit->hugImage(...);
    $band = $kit->band(...);
    $box = $kit->box(...);
    $valignShift = $kit->valignShift(...);
    $line = $kit->line(...);

    $bodyPt = $pt('body');
    // Shaped Arabic is cut into lines here; the width decides how many letters a line holds.
    $bodyRtl = $rtl || \Ahl\Certificates\Support\TextShaper::readsRtl((string) $cert['body']);
    $bodyFace = $isAr($cert['body']) ? $arFace['body'] : $face['body'];
    $bodyLines = $bodyRtl ? $shaper->lines((string) $cert['body'], max(20, (int) floor($L['body']['w'] / ($bodyPt * 0.353 * 0.6)))) : [$shaper->runs((string) $cert['body'])];
    $leadKey = 'lead.'.($cert['place'] ? 'podium' : 'default');
@endphp
<style>
{!! $fonts ?? '' !!}
@page { margin: 0; }
html, body { margin: 0; padding: 0; }
body { font-family: '{{ $rtl ? 'Amiri' : 'Inter' }}', 'DejaVu Sans', sans-serif; color: #1f1a2e; }
.page { position: relative; width: {{ $pageW }}mm; height: {{ $pageH - 1 }}mm; overflow: hidden; }
.bg { position: absolute; top: 0; left: 0; width: {{ $pageW }}mm; height: {{ $pageH }}mm; }
.frame { position: absolute; top: 7mm; left: 7mm; right: 7mm; bottom: 7mm; border: 1.4pt solid {{ $frameInk }}; }
.frame-inner { position: absolute; top: 9.6mm; left: 9.6mm; right: 9.6mm; bottom: 9.6mm; border: 0.6pt solid {{ $rule }}; }
.corner { position: absolute; font-family: 'DejaVu Sans'; font-size: 11pt; line-height: 11pt; color: {{ $frameInk }}; }
.stamp { position: absolute; top: {{ $pageH / 2 - 25 }}mm; left: 0; right: 0; text-align: center; font-family: 'Inter'; font-weight: bold; font-size: 52pt; color: #b3261e; opacity: 0.2; transform: rotate(-14deg); }
</style>
</head>
<body>
<div class="page">
    @if($background_image)<img class="bg" src="{{ $background_image }}" alt="">@endif

    @if($decorated && $ornament !== 'off')
        <div class="frame"></div>
        @if($ornament === 'full')
            <div class="frame-inner"></div>
            <div class="corner" style="top: 5.1mm; left: 5.3mm;">&#9670;</div>
            <div class="corner" style="top: 5.1mm; right: 5.3mm;">&#9670;</div>
            <div class="corner" style="bottom: 5.4mm; left: 5.3mm;">&#9670;</div>
            <div class="corner" style="bottom: 5.4mm; right: 5.3mm;">&#9670;</div>
        @endif
    @endif

    {{-- A soft panel behind the text, for artwork busy enough to fight it. --}}
    @if($shows('panel'))
        @php \Ahl\Certificates\Support\Drawn::put('panel', $L['panel']['x'] + $dx, $L['panel']['y'] + $dy, $L['panel']['w'], $L['panel']['h']); @endphp
        <div style="{{ $box('panel', sprintf('background:%s; opacity:%.2f; border-radius:4mm;', $L['panel']['ink'], $L['panel']['opacity'] ?? 0.82)) }}"></div>
    @endif

    @if(! empty($cert['photo']) && $shows('photo'))
        @php $hug('photo', $L['photo']['w'], $L['photo']['h']); @endphp
        <div style="{{ $box('photo', 'background:#ffffff; border:0.8pt solid '.$frameInk.';') }}">
            <img src="{{ $cert['photo'] }}" alt="" style="width: {{ $L['photo']['w'] - 2 }}mm; height: {{ $L['photo']['h'] - 2 }}mm; margin: 1mm;">
        </div>
    @endif

    @if($decorated && isset($logos[0]) && $shows('logo_start') && (empty($cert['photo']) || $L['logo_start']['moved']))
        @php $hugImage('logo_start', $imgRatio($logos[0])); @endphp
        <div style="{{ $box('logo_start') }}"><img src="{{ $logos[0] }}" alt="" style="max-width: {{ $L['logo_start']['w'] }}mm; max-height: {{ $L['logo_start']['h'] }}mm;"></div>
    @endif
    @if($decorated && isset($logos[1]) && $shows('logo_end'))
        @php $hugImage('logo_end', $imgRatio($logos[1])); @endphp
        <div style="{{ $box('logo_end') }}"><img src="{{ $logos[1] }}" alt="" style="max-width: {{ $L['logo_end']['w'] }}mm; max-height: {{ $L['logo_end']['h'] }}mm;"></div>
    @endif

    @if($medal && $shows('medal'))
        @php $hug('medal', $L['medal']['h'] * 0.75, $L['medal']['h']); @endphp
        <div style="{{ $box('medal') }}"><img src="{{ $medal }}" alt="" style="height: {{ $L['medal']['h'] }}mm;"></div>
    @endif

    @if($shows('headline'))
        {!! $line('headline', $s($headline ?? $issuer), $caps ? 'text-transform:uppercase; letter-spacing:2.4pt;' : '') !!}
    @endif
    @if($shows('title'))
        {!! $line('title', $s($cert['title'])) !!}
    @endif
    @if($ribbon && $shows('ribbon'))
        @php
            $rt = $s($ribbon);
            $rcaps = $caps && ! $isAr($rt);
            $rf = $caps && ! $rcaps ? $arFace['ribbon'] : $face['ribbon'];
            $rtrk = $rcaps ? $tracking['ribbon'] : 0;
            $rpt = \Ahl\Certificates\Support\Fit::size($rcaps ? mb_strtoupper($rt) : $rt, \Ahl\Certificates\Support\Fonts::path($rf[3]), $pt('ribbon'), $L['ribbon']['w'] - 12, $rtrk);
            $hug('ribbon', \Ahl\Certificates\Support\Fit::widthMm($rcaps ? mb_strtoupper($rt) : $rt, \Ahl\Certificates\Support\Fonts::path($rf[3]), $rpt, $rtrk) + 12);
        @endphp
        {{-- The band hugs its words; the box only says where it sits and how wide it may grow. --}}
        <div style="{{ $box('ribbon', 'white-space:nowrap;') }}">
            <span style="display:inline-block; padding:0 6mm; background:{{ $L['ribbon']['ink'] }}; color:#ffffff; font-family:'{{ $rf[0] }}'; font-weight:{{ $rf[1] }}; font-size:{{ $rpt }}pt; line-height:{{ $lh($rf[3], $rpt, $L['ribbon']['h'] * 72 / 25.4) }}pt;{{ $rcaps ? ' text-transform:uppercase; letter-spacing:2pt;' : '' }}">{{ $rt }}</span>
        </div>
    @endif
    @if($shows('lead'))
        {!! $line('lead', $s($t($leadKey))) !!}
    @endif
    @if($shows('name'))
        {!! $line('name', $s($cert['name'])) !!}
    @endif

    @if($shows('divider'))
        @php \Ahl\Certificates\Support\Drawn::put('divider', $L['divider']['x'] + $dx, $L['divider']['y'] + $dy, $L['divider']['w'], $L['divider']['h']); @endphp
        @php $half = max(4, ($L['divider']['w'] - 8) / 2); @endphp
        <div style="{{ $box('divider', "font-family:'DejaVu Sans'; font-size:7pt; line-height:".$L['divider']['h'].'mm; color:'.$L['divider']['ink'].';') }}">
            <span style="display:inline-block; width:{{ $half }}mm; border-top:0.6pt solid {{ $L['divider']['ink'] }}; vertical-align:middle;"></span>
            &#9670;
            <span style="display:inline-block; width:{{ $half }}mm; border-top:0.6pt solid {{ $L['divider']['ink'] }}; vertical-align:middle;"></span>
        </div>
    @endif

    @if($shows('body'))
        @php
            $bodyFont = \Ahl\Certificates\Support\Fonts::path($bodyFace[3]);
            $bandPad = ! empty($L['body']['backing']) ? 6 : 0;
            $lineMm = $bodyPt * ($bodyRtl ? 1.3 : 1.6) * 25.4 / 72;
            if ($bodyRtl) {
                $bw = max(array_map(fn ($l) => \Ahl\Certificates\Support\Fit::widthMm($l, $bodyFont, $bodyPt), $bodyLines ?: ['']));
                $bn = count($bodyLines);
            } else {
                $total = \Ahl\Certificates\Support\Fit::widthMm((string) $bodyLines[0], $bodyFont, $bodyPt);
                $bn = max(1, (int) ceil($total / max(1, $L['body']['w'] - $bandPad - 2)));
                $bw = $bn > 1 ? $L['body']['w'] : $total;
            }
            $hug('body', $bw + $bandPad + 1, $bn * $lineMm + ($bandPad ? 3 : 0) + (! empty($cert['grade']) ? $lineMm + 2 : 0));
        @endphp
        <div style="{{ $box('body', sprintf("font-family:'%s'; font-size:%.2fpt; line-height:%.2fpt; color:%s;", $bodyFace[0], $bodyPt, $lh($bodyFace[3], $bodyPt, $bodyPt * ($bodyRtl ? 1.3 : 1.6)), $L['body']['ink'])) }}">
            <div style="{{ $band('body') }}">
                @foreach($bodyLines as $bl)@if($bodyRtl)<div style="white-space: nowrap;">{{ $bl }}</div>@else{{ $bl }}@endif @endforeach
                @if(!empty($cert['grade']))@php $gl = $s($t('grade_line').': '.$cert['grade']); @endphp<div style="margin-top: 2mm; font-family: '{{ $fam($gl, $bodyFace[0]) }}';">{{ $gl }}</div>@endif
            </div>
        </div>
    @endif

    {{-- The organiser's own text elements: each wraps inside its box, set in the face of its style. --}}
    @include('certificates::pdf.partials.extras')

    @if($signatures && $shows('signatures'))
        @php \Ahl\Certificates\Support\Drawn::put('signatures', $L['signatures']['x'] + $dx, $L['signatures']['y'] + $dy, $L['signatures']['w'], $L['signatures']['h']); @endphp
        @php $sigPt = $pt('signatures'); @endphp
        <div style="{{ $box('signatures', ! empty($L['signatures']['backing']) ? 'background:'.$L['signatures']['backing'].'; border-radius:2mm;' : '') }}">
            <table style="width: 100%; border-collapse: collapse; height: {{ $L['signatures']['h'] }}mm;"><tr>
                @foreach($rtl ? array_reverse($signatures) : $signatures as $sig)
                    <td style="vertical-align: bottom; text-align: center; padding: 0 3mm; color: {{ $L['signatures']['ink'] }};">
                        @if($sig['image'])<img src="{{ $sig['image'] }}" alt="" style="max-height: {{ max(6, $L['signatures']['h'] - 12) }}mm; max-width: 100%;">@endif
                        <div style="border-top: 0.6pt solid #8d8799; margin-top: 1mm;"></div>
                        @if($sig['name'])<div style="font-family: '{{ $fam($sig['name'], 'Inter') }}'; font-weight: bold; font-size: {{ $sigPt }}pt; margin-top: 1.5mm;">{{ $s($sig['name']) }}</div>@endif
                        @if($sig['title'])<div style="font-family: '{{ $fam($sig['title'], 'Inter') }}'; font-size: {{ $sigPt * 0.88 }}pt; opacity: 0.8; margin-top: 0.5mm;">{{ $s($sig['title']) }}</div>@endif
                    </td>
                @endforeach
            </tr></table>
        </div>
    @endif

    @if($decorated && $seal && $shows('seal'))
        @php $d = min($L['seal']['w'], $L['seal']['h']); $sealInk = $L['seal']['ink']; $hug('seal', $d, $d, 'center'); @endphp
        <div style="{{ $box('seal') }}">
            <div style="width: {{ $d }}mm; height: {{ $d }}mm; margin: 0 auto; border: 1.2pt solid {{ $sealInk }}; border-radius: {{ $d / 2 }}mm; text-align: center; color: {{ $sealInk }};">
                <div style="margin: {{ $d * 0.06 }}mm; width: {{ $d * 0.83 }}mm; height: {{ $d * 0.83 }}mm; border: 0.6pt dashed {{ $sealInk }}; border-radius: {{ $d * 0.415 }}mm;">
                    <div style="font-family: '{{ $fam($seal, 'Cormorant') }}'; font-weight: bold; font-size: {{ $pt('seal') * $d / 26 }}pt; padding-top: {{ $d * 0.27 }}mm;">{{ $s($seal) }}</div>
                </div>
            </div>
        </div>
    @endif

    @if($shows('meta'))
        @php
            $metaLines = array_filter([$s($t('certificate_no')).' '.$cert['certificate_no'], $s($t('issued_on').' '.$date), $date_secondary ? $s($date_secondary) : null, $cert['qr'] ? $s($t('verify_hint')) : null]);
            $metaFont = \Ahl\Certificates\Support\Fonts::path($rtl || $isAr(implode(' ', $metaLines)) ? 'Amiri-Regular.ttf' : 'Inter-Regular.ttf');
            $metaPad = ! empty($L['meta']['backing']) ? 6 : 0;
            $hug('meta', max(array_map(fn ($l) => \Ahl\Certificates\Support\Fit::widthMm($l, $metaFont, $pt('meta')), $metaLines)) + $metaPad + 1,
                count($metaLines) * $pt('meta') * 1.5 * 25.4 / 72 + ($metaPad ? 3 : 0));
        @endphp
        <div style="{{ $box('meta', sprintf('font-size:%.2fpt; line-height:%.2fpt; color:%s;', $pt('meta'), $lh($rtl || $isAr(implode(' ', $metaLines)) ? 'Amiri-Regular.ttf' : 'Inter-Regular.ttf', $pt('meta'), $pt('meta') * 1.5), $L['meta']['ink'])) }}">
            <div style="{{ $band('meta') }}">
                <div>{{ $s($t('certificate_no')) }} <b style="font-family: Inter;">{{ $cert['certificate_no'] }}</b></div>
                @php $ml = $s($t('issued_on').' '.$date); @endphp<div style="font-family: '{{ $fam($ml, 'Inter') }}';">{{ $ml }}</div>
                @if($date_secondary)<div style="font-family: '{{ $fam($date_secondary, 'Inter') }}';">{{ $s($date_secondary) }}</div>@endif
                @if($cert['qr'])<div>{{ $s($t('verify_hint')) }}</div>@endif
            </div>
        </div>
    @endif

    @if($cert['qr'] && $shows('qr'))
        @php $q = min($L['qr']['w'], $L['qr']['h']); $hug('qr', $q, $q); @endphp
        <div style="{{ $box('qr') }}">
            <img src="{{ $cert['qr'] }}" alt="" style="width: {{ $q - 2.4 }}mm; height: {{ $q - 2.4 }}mm; padding: 1.2mm; background: #ffffff; border: 0.5pt solid #e6dfd2;">
        </div>
    @endif

    @if(!empty($cert['stamp']))<div class="stamp">{{ $s($cert['stamp']) }}</div>@endif
</div>
</body>
</html>
