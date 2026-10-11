<?php

namespace Ahl\Certificates\Support;

use FontLib\Font;

/**
 * Keeps words where the designer put them, in the PDF as on the screen.
 *
 * The page is drawn twice by dompdf: as a picture (GD backend) for the design room and
 * the viewer, and as the PDF (CPDF) that is downloaded and printed. The two measure a
 * font's height differently — GD from the ink of a test string, CPDF from the font's
 * ascender and descender — and dompdf builds every line box from that height times the
 * line-height factor. For Amiri the PDF's height is twice the picture's, so the same
 * markup set a name ~14 mm lower on paper than on screen.
 *
 * In dompdf a line's ink top is   top + 0.8 · (L / S) · H  (+ 0.1 · H / 1.1 in GD only),
 * with S the size, L the line-height and H the backend's font height. forPdf() returns the
 * line-height that gives the PDF the picture's result, so what is arranged on the screen
 * is what prints. Measured against both backends (27 cases, three faces): within 1 pt.
 */
class LineFit
{
    /** dompdf's default Options::$fontHeightRatio. */
    private const RATIO = 1.1;

    /** @var array<string, array{0: float, 1: float}> font file => [pdf, picture] height per point of size */
    private static array $heights = [];

    /** The line-height (pt) to give the PDF so a line sits where the picture shows it. */
    public static function forPdf(string $fontFile, float $sizePt, float $linePt): float
    {
        [$pdf, $picture] = self::heights($fontFile);
        if ($pdf <= 0 || $picture <= 0 || $sizePt <= 0) {
            return $linePt;
        }

        return (0.8 * $linePt * $picture + 0.1 * ($picture / self::RATIO) * $sizePt) / (0.8 * $pdf);
    }

    /** @return array{0: float, 1: float} */
    public static function heights(string $fontFile): array
    {
        return self::$heights[$fontFile] ??= [self::pdfHeight($fontFile), self::pictureHeight($fontFile)];
    }

    /** CPDF: (Ascender − Descender) / 1000, from the same font metrics dompdf writes for it. */
    private static function pdfHeight(string $file): float
    {
        try {
            $afm = tempnam(sys_get_temp_dir(), 'cert-afm');
            $font = Font::load($file);
            $font->parse();
            $font->saveAdobeFontMetrics($afm);
            $font->close();
            $metrics = (string) @file_get_contents($afm);
            @unlink($afm);
            if (preg_match('/^Ascender\s+(-?[\d.]+)/m', $metrics, $a) && preg_match('/^Descender\s+(-?[\d.]+)/m', $metrics, $d)) {
                return ((float) $a[1] - (float) $d[1]) / 1000 * self::RATIO;
            }
        } catch (\Throwable) {
            // unknown metrics: no correction
        }

        return 0.0;
    }

    /** GD: the ink of dompdf's own test string, as GD::get_font_height_actual() measures it. */
    private static function pictureHeight(string $file): float
    {
        $box = @imagettfbbox(100, 0, $file, 'MXjpqytfhl');

        return $box ? ($box[1] - $box[5]) / (100 * 96 / 72) * self::RATIO : 0.0;
    }
}
