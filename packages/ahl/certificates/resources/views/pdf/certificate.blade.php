{{--
    The certificate, A4 landscape, for dompdf. Data (CertificateService::renderView, then Host::viewData):
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
    $s = fn ($text) => $rtl ? $shaper->line((string) $text) : (string) $text;
    $L = $layout ?? \Ahl\Certificates\Support\Layout::resolve(null, (bool) ($cert['place'] ?? null), $rtl);
    $dx = $offset['x'] ?? 0;
    $dy = $offset['y'] ?? 0;
    $frameInk = $metal ?? $accent;
    $rule = $metal ?? '#c9a86a';

    // The fonts each element is set in, for the fit-to-box measurement and the CSS.
    $face = [
        'headline' => $rtl ? ['Amiri', 'normal', 'normal', 'Amiri-Regular.ttf'] : ['InterSemi', 'normal', 'normal', 'Inter-SemiBold.ttf'],
        'title' => $rtl ? ['Amiri', 'bold', 'normal', 'Amiri-Bold.ttf'] : ['Cormorant', 'normal', 'normal', 'CormorantGaramond-SemiBold.ttf'],
        'ribbon' => $rtl ? ['Amiri', 'bold', 'normal', 'Amiri-Bold.ttf'] : ['Inter', 'bold', 'normal', 'Inter-Bold.ttf'],
        'lead' => $rtl ? ['Amiri', 'normal', 'normal', 'Amiri-Regular.ttf'] : ['Cormorant', 'normal', 'italic', 'CormorantGaramond-MediumItalic.ttf'],
        'name' => $rtl ? ['Amiri', 'bold', 'normal', 'Amiri-Bold.ttf'] : ['Cormorant', 'bold', 'normal', 'CormorantGaramond-Bold.ttf'],
        'body' => $rtl ? ['Amiri', 'normal', 'normal', 'Amiri-Regular.ttf'] : ['Inter', 'normal', 'normal', 'Inter-Regular.ttf'],
    ];
    $caps = ! $rtl;   // headline and ribbon are letter-spaced capitals in Latin script only
    $tracking = ['headline' => $caps ? 2.4 : 0, 'ribbon' => $caps ? 2 : 0];

    $pt = fn (string $k) => (\Ahl\Certificates\Support\Layout::ELEMENTS[$k][$rtl ? 2 : 1]) * (float) ($L[$k]['scale'] ?? 1);
    $align = fn (string $k) => ['start' => 'left', 'end' => 'right'][$L[$k]['align'] ?? 'center'] ?? 'center';
    $shows = fn (string $k) => isset($L[$k]) && empty($L[$k]['hidden']);
    // Record where an element's ink actually lands (Support\Drawn), so the editor's
    // handles hug the words rather than the whole box they may use.
    $hug = function (string $k, float $cw, ?float $ch = null, ?string $forceAlign = null) use ($L, $dx, $dy) {
        $b = $L[$k];
        $cw = min($cw, $b['w']);
        $a = $forceAlign ?? ($b['align'] ?? 'center');
        $x = $a === 'start' ? $b['x'] : ($a === 'end' ? $b['x'] + $b['w'] - $cw : $b['x'] + ($b['w'] - $cw) / 2);
        \Ahl\Certificates\Support\Drawn::put($k, $x + $dx, $b['y'] + $dy, $cw, $ch ?? $b['h']);
    };
    $imgRatio = function (?string $uri) {
        if (! $uri || ! str_starts_with($uri, 'data:') || ! ($comma = strpos($uri, ','))) {
            return 1.0;
        }
        $info = @getimagesizefromstring(base64_decode(substr($uri, $comma + 1)));

        return $info && $info[1] ? $info[0] / $info[1] : 1.0;
    };
    $hugImage = function (string $k, float $ratio) use ($L, $hug) {
        $b = $L[$k];
        [$w, $h] = $b['w'] / $b['h'] > $ratio ? [$b['h'] * $ratio, $b['h']] : [$b['w'], $b['w'] / $ratio];
        $hug($k, $w, $h);
    };

    // The band hugs its words (an inner box, not the element's whole box).
    $band = fn (string $k) => ! empty($L[$k]['backing']) ? 'display:inline-block; background:'.$L[$k]['backing'].'; border-radius:2mm; padding:1.5mm 3mm;' : '';
    $box = function (string $k, string $extra = '') use ($L, $dx, $dy, $align) {
        $b = $L[$k];

        return sprintf('position:absolute; left:%.2fmm; top:%.2fmm; width:%.2fmm; height:%.2fmm; text-align:%s; %s',
            $b['x'] + $dx, $b['y'] + $dy, $b['w'], $b['h'], $align($k), $extra);
    };
    // One line, set at the size that fits its box (never cut, never off the page).
    $line = function (string $k, string $text, string $extra = '') use ($L, $face, $pt, $tracking, $box, $caps, &$hug) {
        $f = $face[$k];
        $measure = in_array($k, ['headline', 'ribbon'], true) && $caps ? mb_strtoupper($text) : $text;
        $size = \Ahl\Certificates\Support\Fit::size($measure, \Ahl\Certificates\Support\Fonts::path($f[3]), $pt($k), $L[$k]['w'] - 1, $tracking[$k] ?? 0);
        $hug($k, \Ahl\Certificates\Support\Fit::widthMm($measure, \Ahl\Certificates\Support\Fonts::path($f[3]), $size, $tracking[$k] ?? 0) + (! empty($L[$k]['backing']) ? 6 : 0) + 1);

        // On a busy background the words sit on a soft band (Contrast, via the service's backing).
        $inner = ! empty($L[$k]['backing'])
            ? '<span style="display:inline-block; padding:0 3mm; border-radius:1.5mm; background:'.$L[$k]['backing'].';">'.e($text).'</span>'
            : e($text);

        return '<div style="'.$box($k, sprintf("white-space:nowrap; overflow:visible; font-family:'%s'; font-weight:%s; font-style:%s; font-size:%.2fpt; line-height:%.2fmm; color:%s; %s",
            $f[0], $f[1], $f[2], $size, $L[$k]['h'], $L[$k]['ink'], $extra)).'">'.$inner.'</div>';
    };

    $bodyPt = $pt('body');
    // Shaped Arabic is cut into lines here; the width decides how many letters a line holds.
    $bodyLines = $rtl ? $shaper->lines((string) $cert['body'], max(20, (int) floor($L['body']['w'] / ($bodyPt * 0.353 * 0.6)))) : [(string) $cert['body']];
    $leadKey = 'lead.'.($cert['place'] ? 'podium' : 'default');
@endphp
<style>
{!! $fonts ?? '' !!}
@page { margin: 0; }
html, body { margin: 0; padding: 0; }
body { font-family: '{{ $rtl ? 'Amiri' : 'Inter' }}', 'DejaVu Sans', sans-serif; color: #1f1a2e; }
.page { position: relative; width: 297mm; height: 209mm; overflow: hidden; }
.bg { position: absolute; top: 0; left: 0; width: 297mm; height: 210mm; }
.frame { position: absolute; top: 7mm; left: 7mm; right: 7mm; bottom: 7mm; border: 1.4pt solid {{ $frameInk }}; }
.frame-inner { position: absolute; top: 9.6mm; left: 9.6mm; right: 9.6mm; bottom: 9.6mm; border: 0.6pt solid {{ $rule }}; }
.corner { position: absolute; font-family: 'DejaVu Sans'; font-size: 11pt; line-height: 11pt; color: {{ $frameInk }}; }
.stamp { position: absolute; top: 80mm; left: 0; right: 0; text-align: center; font-family: 'Inter'; font-weight: bold; font-size: 52pt; color: #b3261e; opacity: 0.2; transform: rotate(-14deg); }
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
            $rf = $face['ribbon'];
            $rt = $s($ribbon);
            $rpt = \Ahl\Certificates\Support\Fit::size($caps ? mb_strtoupper($rt) : $rt, \Ahl\Certificates\Support\Fonts::path($rf[3]), $pt('ribbon'), $L['ribbon']['w'] - 12, $tracking['ribbon']);
            $hug('ribbon', \Ahl\Certificates\Support\Fit::widthMm($caps ? mb_strtoupper($rt) : $rt, \Ahl\Certificates\Support\Fonts::path($rf[3]), $rpt, $tracking['ribbon']) + 12);
        @endphp
        {{-- The band hugs its words; the box only says where it sits and how wide it may grow. --}}
        <div style="{{ $box('ribbon', 'white-space:nowrap;') }}">
            <span style="display:inline-block; padding:0 6mm; background:{{ $L['ribbon']['ink'] }}; color:#ffffff; font-family:'{{ $rf[0] }}'; font-weight:{{ $rf[1] }}; font-size:{{ $rpt }}pt; line-height:{{ $L['ribbon']['h'] }}mm;{{ $caps ? ' text-transform:uppercase; letter-spacing:2pt;' : '' }}">{{ $rt }}</span>
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
            $bodyFont = \Ahl\Certificates\Support\Fonts::path($face['body'][3]);
            $bandPad = ! empty($L['body']['backing']) ? 6 : 0;
            $lineMm = $bodyPt * ($rtl ? 1.3 : 1.6) * 25.4 / 72;
            if ($rtl) {
                $bw = max(array_map(fn ($l) => \Ahl\Certificates\Support\Fit::widthMm($l, $bodyFont, $bodyPt), $bodyLines ?: ['']));
                $bn = count($bodyLines);
            } else {
                $total = \Ahl\Certificates\Support\Fit::widthMm((string) $cert['body'], $bodyFont, $bodyPt);
                $bn = max(1, (int) ceil($total / max(1, $L['body']['w'] - $bandPad - 2)));
                $bw = $bn > 1 ? $L['body']['w'] : $total;
            }
            $hug('body', $bw + $bandPad + 1, $bn * $lineMm + ($bandPad ? 3 : 0) + (! empty($cert['grade']) ? $lineMm + 2 : 0));
        @endphp
        <div style="{{ $box('body', sprintf("font-family:'%s'; font-size:%.2fpt; line-height:%s; color:%s;", $face['body'][0], $bodyPt, $rtl ? '1.3' : '1.6', $L['body']['ink'])) }}">
            <div style="{{ $band('body') }}">
                @foreach($bodyLines as $bl)@if($rtl)<div style="white-space: nowrap;">{{ $bl }}</div>@else{{ $bl }}@endif @endforeach
                @if(!empty($cert['grade']))<div style="margin-top: 2mm;">{{ $s($t('grade_line').': '.$cert['grade']) }}</div>@endif
            </div>
        </div>
    @endif

    @if($signatures && $shows('signatures'))
        @php \Ahl\Certificates\Support\Drawn::put('signatures', $L['signatures']['x'] + $dx, $L['signatures']['y'] + $dy, $L['signatures']['w'], $L['signatures']['h']); @endphp
        @php $sigPt = $pt('signatures'); @endphp
        <div style="{{ $box('signatures', ! empty($L['signatures']['backing']) ? 'background:'.$L['signatures']['backing'].'; border-radius:2mm;' : '') }}">
            <table style="width: 100%; border-collapse: collapse; height: {{ $L['signatures']['h'] }}mm;"><tr>
                @foreach($rtl ? array_reverse($signatures) : $signatures as $sig)
                    <td style="vertical-align: bottom; text-align: center; padding: 0 3mm; color: {{ $L['signatures']['ink'] }};">
                        @if($sig['image'])<img src="{{ $sig['image'] }}" alt="" style="max-height: {{ max(6, $L['signatures']['h'] - 12) }}mm; max-width: 100%;">@endif
                        <div style="border-top: 0.6pt solid #8d8799; margin-top: 1mm;"></div>
                        @if($sig['name'])<div style="font-family: '{{ $rtl ? 'Amiri' : 'Inter' }}'; font-weight: bold; font-size: {{ $sigPt }}pt; margin-top: 1.5mm;">{{ $s($sig['name']) }}</div>@endif
                        @if($sig['title'])<div style="font-size: {{ $sigPt * 0.88 }}pt; opacity: 0.8; margin-top: 0.5mm;">{{ $s($sig['title']) }}</div>@endif
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
                    <div style="font-family: '{{ $rtl ? 'Amiri' : 'Cormorant' }}'; font-weight: bold; font-size: {{ $pt('seal') * $d / 26 }}pt; padding-top: {{ $d * 0.27 }}mm;">{{ $s($seal) }}</div>
                </div>
            </div>
        </div>
    @endif

    @if($shows('meta'))
        @php
            $metaLines = array_filter([$s($t('certificate_no')).' '.$cert['certificate_no'], $s($t('issued_on').' '.$date), $date_secondary ? $s($date_secondary) : null, $cert['qr'] ? $s($t('verify_hint')) : null]);
            $metaFont = \Ahl\Certificates\Support\Fonts::path($rtl ? 'Amiri-Regular.ttf' : 'Inter-Regular.ttf');
            $metaPad = ! empty($L['meta']['backing']) ? 6 : 0;
            $hug('meta', max(array_map(fn ($l) => \Ahl\Certificates\Support\Fit::widthMm($l, $metaFont, $pt('meta')), $metaLines)) + $metaPad + 1,
                count($metaLines) * $pt('meta') * 1.5 * 25.4 / 72 + ($metaPad ? 3 : 0));
        @endphp
        <div style="{{ $box('meta', sprintf('font-size:%.2fpt; line-height:1.5; color:%s;', $pt('meta'), $L['meta']['ink'])) }}">
            <div style="{{ $band('meta') }}">
                <div>{{ $s($t('certificate_no')) }} <b style="font-family: Inter;">{{ $cert['certificate_no'] }}</b></div>
                <div>{{ $s($t('issued_on').' '.$date) }}</div>
                @if($date_secondary)<div>{{ $s($date_secondary) }}</div>@endif
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
