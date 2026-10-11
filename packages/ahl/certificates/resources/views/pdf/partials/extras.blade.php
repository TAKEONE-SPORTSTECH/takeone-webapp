{{--
    The organiser's own text elements, shared by every page view (certificate, badge):
    each wraps inside its box, set in the face of its style, Arabic joined and right to
    left, its lines re-spaced in the PDF to land where the screen shows them. Reads the
    including view's $cert['extras'], $L, $rtl, $shaper and its PageKit helpers.
--}}
    @foreach(($cert['extras'] ?? []) as $xk => $x)
        @if($shows($xk) && trim((string) $x['text']) !== '')
            @php
                $xf = [
                    'heading' => $rtl ? ['Amiri', 'bold', 'normal', 'Amiri-Bold.ttf'] : ['Cormorant', 'normal', 'normal', 'CormorantGaramond-SemiBold.ttf'],
                    'script' => $rtl ? ['Amiri', 'normal', 'normal', 'Amiri-Regular.ttf'] : ['Cormorant', 'normal', 'italic', 'CormorantGaramond-MediumItalic.ttf'],
                ][$x['style']] ?? ($rtl ? ['Amiri', 'normal', 'normal', 'Amiri-Regular.ttf'] : ['Inter', 'normal', 'normal', 'Inter-Regular.ttf']);
                $xRtl = $rtl || \Ahl\Certificates\Support\TextShaper::readsRtl((string) $x['text']);
                if (! $rtl && $isAr($x['text'])) {
                    $xf = $x['style'] === 'heading' ? ['Amiri', 'bold', 'normal', 'Amiri-Bold.ttf'] : ['Amiri', 'normal', 'normal', 'Amiri-Regular.ttf'];
                }
                $xpt = (\Ahl\Certificates\Support\Layout::CUSTOM_STYLES[$x['style']] ?? [11.5, 12])[$rtl ? 1 : 0] * (float) ($L[$xk]['scale'] ?? 1);
                $xlh = $xRtl ? 1.3 : ($x['style'] === 'heading' ? 1.25 : 1.5);
                $xfont = \Ahl\Certificates\Support\Fonts::path($xf[3]);
                $xpad = ! empty($L[$xk]['backing']) ? 6 : 0;
                $xlines = $xRtl ? $shaper->lines((string) $x['text'], max(12, (int) floor($L[$xk]['w'] / ($xpt * 0.353 * 0.6)))) : [$shaper->runs((string) $x['text'])];
                if ($xRtl) {
                    $xw = max(array_map(fn ($l) => \Ahl\Certificates\Support\Fit::widthMm($l, $xfont, $xpt), $xlines ?: ['']));
                    $xn = count($xlines);
                } else {
                    $xt = \Ahl\Certificates\Support\Fit::widthMm((string) $xlines[0], $xfont, $xpt);
                    $xn = max(1, (int) ceil($xt / max(1, $L[$xk]['w'] - $xpad - 2)));
                    $xw = $xn > 1 ? $L[$xk]['w'] : $xt;
                }
                // Where the words sit inside their box, top to bottom (PageKit::valignShift).
                $xth = $xn * $xpt * $xlh * 25.4 / 72 + ($xpad ? 3 : 0);
                $xoff = $valignShift($xk, $xf[3], $xpt, $xpt * $xlh, $xn, $xpad ? 1.5 : 0.0, $xpad ? 1.5 : 0.0);
                $hug($xk, $xw + $xpad + 1, $xth, null, $xoff);
            @endphp
            <div style="{{ $box($xk, sprintf("font-family:'%s'; font-weight:%s; font-style:%s; font-size:%.2fpt; line-height:%.2fpt; color:%s;", $xf[0], $xf[1], $xf[2], $xpt, $lh($xf[3], $xpt, $xpt * $xlh), $L[$xk]['ink']), $xoff) }}">
                {{-- Words measured to fit one line are held on it: the band's shrink-to-fit would otherwise break them. --}}
                <div style="{{ $band($xk) }}{{ ! $xRtl && $xn === 1 && $band($xk) !== '' ? ' white-space: nowrap;' : '' }}">@foreach($xlines as $xl)@if($xRtl)<div style="white-space: nowrap;">{{ $xl }}</div>@else{{ $xl }}@endif @endforeach</div>
            </div>
        @endif
    @endforeach

