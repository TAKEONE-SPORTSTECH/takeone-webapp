<?php

namespace Ahl\Certificates\Support;

/**
 * A medal for a placed certificate — gold, silver, bronze — drawn as a PNG with its
 * numeral on the disc (colour alone is not a distinction for every reader).
 * Place 4+ is not a medal and draws nothing.
 */
class Medal
{
    public const COLOURS = [
        1 => ['disc' => [212, 166, 42], 'rim' => [156, 116, 18], 'shine' => [243, 217, 138], 'ink' => [111, 82, 16]],
        2 => ['disc' => [185, 192, 199], 'rim' => [123, 132, 141], 'shine' => [230, 234, 238], 'ink' => [77, 85, 93]],
        3 => ['disc' => [196, 128, 74], 'rim' => [140, 82, 40], 'shine' => [236, 186, 142], 'ink' => [95, 52, 20]],
    ];

    /** The metal's own ink, for the ribbon and rules around it. */
    public const METAL = [1 => '#b8892b', 2 => '#8a939c', 3 => '#a0612e'];

    public function __construct(private string $fontFile) {}

    public function dataUri(int $place, string $ribbon = '#9f1239'): ?string
    {
        if (! isset(self::COLOURS[$place])) {
            return null;
        }
        $c = self::COLOURS[$place];
        [$w, $h] = [240, 320];
        $im = imagecreatetruecolor($w, $h);
        imagesavealpha($im, true);
        imagealphablending($im, false);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
        imagealphablending($im, true);

        $rib = $this->rgb($ribbon);
        $dark = array_map(fn ($v) => (int) ($v * 0.75), $rib);
        imagefilledpolygon($im, [55, 0, 105, 0, 140, 130, 90, 130], imagecolorallocate($im, ...$rib));
        imagefilledpolygon($im, [135, 0, 185, 0, 150, 130, 100, 130], imagecolorallocate($im, ...$dark));

        [$cx, $cy, $r] = [120, 205, 105];
        imagefilledellipse($im, $cx, $cy, $r * 2 + 8, $r * 2 + 8, imagecolorallocate($im, ...$c['rim']));
        imagefilledellipse($im, $cx, $cy, $r * 2, $r * 2, imagecolorallocate($im, ...$c['disc']));
        imagesetthickness($im, 5);
        imageellipse($im, $cx, $cy, (int) ($r * 1.55), (int) ($r * 1.55), imagecolorallocate($im, ...$c['shine']));

        if (is_file($this->fontFile)) {
            $size = 88;
            $box = imagettfbbox($size, 0, $this->fontFile, (string) $place);
            $tw = $box[2] - $box[0];
            $th = $box[1] - $box[7];
            imagettftext($im, $size, 0, (int) ($cx - $tw / 2 - $box[0]), (int) ($cy + $th / 2), imagecolorallocate($im, ...$c['ink']), $this->fontFile, (string) $place);
        }

        ob_start();
        imagepng($im);
        $png = (string) ob_get_clean();
        imagedestroy($im);

        return 'data:image/png;base64,'.base64_encode($png);
    }

    /** @return array{0:int,1:int,2:int} */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            $hex = '9f1239';
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
