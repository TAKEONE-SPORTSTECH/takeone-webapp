<?php

namespace Ahl\Certificates\Support;

/**
 * The typefaces the certificate is printed in, shipped with the package (SIL OFL, licences
 * beside the files) so a PDF never depends on a font the server happens to have:
 *
 *   Cormorant Garamond — the title and the name
 *   Inter              — everything else
 *   Amiri              — Arabic, with its joined forms (see TextShaper)
 */
class Fonts
{
    public const FAMILIES = [
        ['Cormorant', 'CormorantGaramond-SemiBold.ttf', 'normal', 'normal'],
        ['Cormorant', 'CormorantGaramond-Bold.ttf', 'bold', 'normal'],
        ['Cormorant', 'CormorantGaramond-MediumItalic.ttf', 'normal', 'italic'],
        ['Inter', 'Inter-Regular.ttf', 'normal', 'normal'],
        ['Inter', 'Inter-Bold.ttf', 'bold', 'normal'],
        ['InterSemi', 'Inter-SemiBold.ttf', 'normal', 'normal'],
        ['Amiri', 'Amiri-Regular.ttf', 'normal', 'normal'],
        ['Amiri', 'Amiri-Bold.ttf', 'bold', 'normal'],
    ];

    public static function dir(): string
    {
        return realpath(__DIR__.'/../../resources/fonts') ?: __DIR__.'/../../resources/fonts';
    }

    public static function path(string $file): string
    {
        return self::dir().'/'.$file;
    }

    public static function css(): string
    {
        return collect(self::FAMILIES)->map(fn ($f) => sprintf(
            "@font-face { font-family: '%s'; src: url('%s') format('truetype'); font-weight: %s; font-style: %s; }",
            $f[0], self::path($f[1]), $f[2], $f[3],
        ))->implode("\n");
    }
}
