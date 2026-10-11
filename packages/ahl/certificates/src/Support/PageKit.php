<?php

namespace Ahl\Certificates\Support;

/**
 * The drawing helpers every page view shares — a certificate, a badge, whatever format
 * comes next — so a fix lands once and every printed page gets it.
 *
 * A view builds one with its resolved layout and the faces its elements are set in,
 * then takes the helpers as callables (`$box = $kit->box(...)`):
 *
 *   box()    an element's absolute box, aligned as arranged
 *   line()   one line of words, set at the size that fits its box, never cut
 *   hug()    where the ink actually landed (Support\Drawn), for the editor's handles
 *   s()      Arabic joined and ordered, on any page (Support\TextShaper)
 *   fam()    Amiri for Arabic, the given face otherwise
 *   lh()     the line-height that puts the PDF's lines where the screen's are (LineFit)
 *   band()   the soft band behind words on a busy picture (Contrast's backing)
 */
final class PageKit
{
    /**
     * @param  array<string, array>  $L  the resolved, inked layout (Layout::resolve + the service's inks)
     * @param  array<string, array{0:string,1:string,2:string,3:string}>  $face  element => [family, weight, style, file]
     * @param  array<string, array{0:string,1:string,2:string,3:string}>  $arFace  the same for Arabic words on a Latin page
     * @param  array<string, float>  $tracking  letter-spaced (and capitalised) elements => spacing in pt
     */
    public function __construct(
        private array $L,
        private bool $rtl,
        private bool $raster,
        private TextShaper $shaper,
        private float $dx = 0,
        private float $dy = 0,
        private array $face = [],
        private array $arFace = [],
        private array $tracking = [],
    ) {}

    public function s($text): string
    {
        return $this->shaper->visual((string) $text, $this->rtl);
    }

    public function isAr($text): bool
    {
        return TextShaper::isArabicScript((string) $text);
    }

    public function fam($text, string $latin): string
    {
        return ($this->rtl || $this->isAr($text)) ? 'Amiri' : $latin;
    }

    /** The line-height (pt): as asked on the screen, re-spaced in the PDF so its lines land where the screen's do. */
    public function lh(string $fontFile, float $sizePt, float $linePt): float
    {
        return $this->raster ? $linePt : LineFit::forPdf(Fonts::path($fontFile), $sizePt, $linePt);
    }

    /** The type size of an element: its format's base size (LTR or RTL) times the organiser's scale. */
    public function pt(string $k): float
    {
        return (Layout::elements()[$k][$this->rtl ? 2 : 1] ?? 10) * (float) ($this->L[$k]['scale'] ?? 1);
    }

    public function align(string $k): string
    {
        return ['start' => 'left', 'end' => 'right'][$this->L[$k]['align'] ?? 'center'] ?? 'center';
    }

    public function shows(string $k): bool
    {
        return isset($this->L[$k]) && empty($this->L[$k]['hidden']);
    }

    /** Record where an element's ink actually lands, so the editor's handles hug the words rather than the box. */
    public function hug(string $k, float $cw, ?float $ch = null, ?string $forceAlign = null, float $offY = 0.0): void
    {
        $b = $this->L[$k];
        $cw = min($cw, $b['w']);
        $a = $forceAlign ?? ($b['align'] ?? 'center');
        $x = $a === 'start' ? $b['x'] : ($a === 'end' ? $b['x'] + $b['w'] - $cw : $b['x'] + ($b['w'] - $cw) / 2);
        Drawn::put($k, $x + $this->dx, $b['y'] + $this->dy + $offY, $cw, $ch ?? $b['h']);
    }

    public function imgRatio(?string $uri): float
    {
        if (! $uri || ! str_starts_with($uri, 'data:') || ! ($comma = strpos($uri, ','))) {
            return 1.0;
        }
        $info = @getimagesizefromstring(base64_decode(substr($uri, $comma + 1)));

        return $info && $info[1] ? $info[0] / $info[1] : 1.0;
    }

    public function hugImage(string $k, float $ratio): void
    {
        $b = $this->L[$k];
        [$w, $h] = $b['w'] / $b['h'] > $ratio ? [$b['h'] * $ratio, $b['h']] : [$b['w'], $b['w'] / $ratio];
        $this->hug($k, $w, $h);
    }

    /** The band hugs its words (an inner box, not the element's whole box). */
    public function band(string $k): string
    {
        return ! empty($this->L[$k]['backing']) ? 'display:inline-block; background:'.$this->L[$k]['backing'].'; border-radius:2mm; padding:1.5mm 3mm;' : '';
    }

    public function box(string $k, string $extra = '', float $shiftY = 0.0): string
    {
        $b = $this->L[$k];

        return sprintf('position:absolute; left:%.2fmm; top:%.2fmm; width:%.2fmm; height:%.2fmm; text-align:%s; %s',
            $b['x'] + $this->dx, $b['y'] + $this->dy + $shiftY, $b['w'], $b['h'], $this->align($k), $extra);
    }

    /**
     * How far (mm) to move an element's words so they sit at the top, middle or bottom
     * of its box, as the organiser chose (`valign`). Measured on the INK — capital top
     * to last baseline — because dompdf's line box puts a line's letters well below its
     * middle; centring the line box would still look low. No choice made: 0, so a design
     * arranged before this existed stays exactly where it was.
     *
     * dompdf sets a line's baseline at  top + 0.8 · L · H + 0.1 · H · S / 1.1  (H the
     * picture backend's height per point, LineFit); the PDF is corrected onto the same
     * baseline by LineFit::forPdf, so one answer serves both.
     */
    public function valignShift(string $k, string $fontFile, float $sizePt, float $linePt, int $lines = 1, float $padTop = 0.0, float $padBottom = 0.0): float
    {
        $v = $this->L[$k]['valign'] ?? null;
        if (! in_array($v, ['top', 'middle', 'bottom'], true)) {
            return 0.0;
        }
        $path = Fonts::path($fontFile);
        $pic = LineFit::heights($path)[1];
        $mm = 25.4 / 72;
        $base = (0.8 * $linePt * $pic + 0.1 * $pic * $sizePt / 1.1) * $mm + $padTop;
        $top = $base - self::capHeight($path) * $sizePt * $mm;
        $bottom = $base + max(0, $lines - 1) * $linePt * $mm;
        $h = (float) $this->L[$k]['h'];

        return match ($v) {
            'top' => -$top + $padTop,
            'middle' => $h / 2 - ($top + $bottom) / 2,
            'bottom' => $h - $bottom - $padBottom,
        };
    }

    /** @var array<string, float> */
    private static array $caps = [];

    /** A face's capital height, per point of size (from GD's own measure of an "H"). */
    private static function capHeight(string $path): float
    {
        return self::$caps[$path] ??= (($b = @imagettfbbox(100, 0, $path, 'H')) ? ($b[1] - $b[5]) / (100 * 96 / 72) : 0.7);
    }

    /** One line, set at the size that fits its box (never cut, never off the page). */
    public function line(string $k, string $text, string $extra = ''): string
    {
        $L = $this->L;
        // Arabic on a Latin page: Amiri, and no letter-spacing (it would pull the joined letters apart).
        $caps = ! $this->rtl;
        $ar = $caps && $this->isAr($text);
        $f = $ar ? ($this->arFace[$k] ?? ['Amiri', 'normal', 'normal', 'Amiri-Regular.ttf']) : $this->face[$k];
        $trk = $ar ? 0 : ($this->tracking[$k] ?? 0);
        if ($ar) {
            $extra .= ' text-transform:none; letter-spacing:0;';
        }
        // Letter-spaced elements are capitals in Latin script, and are measured as such.
        $measure = ($this->tracking[$k] ?? 0) > 0 && $caps && ! $ar ? mb_strtoupper($text) : $text;
        $size = Fit::size($measure, Fonts::path($f[3]), $this->pt($k), $L[$k]['w'] - 1, $trk);
        $this->hug($k, Fit::widthMm($measure, Fonts::path($f[3]), $size, $trk) + (! empty($L[$k]['backing']) ? 6 : 0) + 1);

        // On a busy background the words sit on a soft band (Contrast, via the service's backing).
        $inner = ! empty($L[$k]['backing'])
            ? '<span style="display:inline-block; padding:0 3mm; border-radius:1.5mm; background:'.$L[$k]['backing'].';">'.e($text).'</span>'
            : e($text);

        $line = $L[$k]['h'] * 72 / 25.4;
        $shift = $this->valignShift($k, $f[3], $size, $line);

        return '<div style="'.$this->box($k, sprintf("white-space:nowrap; overflow:visible; font-family:'%s'; font-weight:%s; font-style:%s; font-size:%.2fpt; line-height:%.2fpt; color:%s; %s",
            $f[0], $f[1], $f[2], $size, $this->lh($f[3], $size, $L[$k]['h'] * 72 / 25.4), $L[$k]['ink'], $extra), $shift).'">'.$inner.'</div>';
    }
}
