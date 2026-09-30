<?php

namespace App\Certificates\Support;

use Illuminate\Http\UploadedFile;

/**
 * The two pictures an organiser gives a certificate design — a signature and a
 * full-page background — checked by their REAL bytes and redrawn by GD before
 * they are kept (CLAUDE.md → Image Uploads Must Validate Real Bytes).
 *
 * Redrawing is the point, not a side effect: whatever rode inside the file (a
 * polyglot, metadata, a script in a comment) does not survive being decoded to
 * pixels and encoded again, and the page ends up a sane size for a PDF.
 * Only PNG and JPEG are accepted; SVG never, it can carry script.
 */
class CertificateImages
{
    public const MAX_SIGNATURE_BYTES = 2 * 1024 * 1024;

    public const MAX_ARTWORK_BYTES = 10 * 1024 * 1024;

    /** A4 landscape at 150 dpi — what prints sharply. Advice, not a rule: anything smaller is enlarged. */
    public const MIN_ARTWORK = [1754, 1240];

    private const MIMES = ['image/png', 'image/jpeg', 'image/webp'];

    /** @return array{ok:bool, bytes?:string, mime?:string, error?:string} */
    public function signature(UploadedFile $file): array
    {
        $im = $this->decode($file, self::MAX_SIGNATURE_BYTES);
        if (is_string($im)) {
            return ['ok' => false, 'error' => $im];
        }

        $im = $this->fit($im, 1200, 400);
        imagesavealpha($im, true);
        ob_start();
        imagepng($im, null, 7);
        $bytes = (string) ob_get_clean();
        imagedestroy($im);

        return ['ok' => true, 'bytes' => $bytes, 'mime' => 'image/png'];
    }

    /**
     * Any picture becomes a full A4-landscape page — whatever its size or shape:
     *
     *   wide enough (≥ 1.2 : 1)   fills the page; what overhangs is trimmed evenly
     *   square or tall            shown whole, centred, on a softened, stretched copy of
     *                             itself so the page has no empty bars
     *   small                     enlarged, and the answer says it may print soft
     *
     * A phone photo is turned upright first (EXIF orientation).
     *
     * @return array{ok:bool, bytes?:string, mime?:string, error?:string, warning?:string}
     */
    public function artwork(UploadedFile $file): array
    {
        $im = $this->decode($file, self::MAX_ARTWORK_BYTES);
        if (is_string($im)) {
            return ['ok' => false, 'error' => $im];
        }
        $im = $this->upright($im, $file);

        [$w, $h] = [imagesx($im), imagesy($im)];
        [$tw, $th] = [3508, 2480];
        $page = imagecreatetruecolor($tw, $th);
        imagefill($page, 0, 0, imagecolorallocate($page, 255, 255, 255));

        if ($w / $h >= 1.2) {
            // Cover: scale to fill, trim the overhang evenly.
            $scale = max($tw / $w, $th / $h);
            [$sw, $sh] = [(int) round($tw / $scale), (int) round($th / $scale)];
            imagecopyresampled($page, $im, 0, 0, (int) (($w - $sw) / 2), (int) (($h - $sh) / 2), $tw, $th, $sw, $sh);
        } else {
            // Contain on a blurred copy of itself: nothing of the picture is lost.
            $scale = max($tw / $w, $th / $h);
            [$sw, $sh] = [(int) round($tw / $scale), (int) round($th / $scale)];
            $tiny = imagecreatetruecolor(max(1, (int) ($tw / 40)), max(1, (int) ($th / 40)));
            imagecopyresampled($tiny, $im, 0, 0, (int) (($w - $sw) / 2), (int) (($h - $sh) / 2), imagesx($tiny), imagesy($tiny), $sw, $sh);
            imagecopyresampled($page, $tiny, 0, 0, 0, 0, $tw, $th, imagesx($tiny), imagesy($tiny));
            imagedestroy($tiny);
            imagefilter($page, IMG_FILTER_GAUSSIAN_BLUR);

            $fit = min($tw / $w, $th / $h);
            [$fw, $fh] = [(int) round($w * $fit), (int) round($h * $fit)];
            imagecopyresampled($page, $im, (int) (($tw - $fw) / 2), (int) (($th - $fh) / 2), 0, 0, $fw, $fh, $w, $h);
        }
        $small = max($w, $h) < 1200;
        imagedestroy($im);

        ob_start();
        imagejpeg($page, null, 86);
        $bytes = (string) ob_get_clean();
        imagedestroy($page);

        return ['ok' => true, 'bytes' => $bytes, 'mime' => 'image/jpeg'] + ($small ? ['warning' => __('cert::messages.artwork_small')] : []);
    }

    /** A phone photo stores "which way is up" beside the pixels; apply it. */
    private function upright(\GdImage $im, UploadedFile $file): \GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $im;
        }
        $exif = @exif_read_data($file->getRealPath());
        $angle = match ((int) ($exif['Orientation'] ?? 1)) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
        if ($angle === 0) {
            return $im;
        }
        $turned = imagerotate($im, $angle, 0);
        if ($turned instanceof \GdImage) {
            imagedestroy($im);

            return $turned;
        }

        return $im;
    }

    /** @return \GdImage|string the image, or the reason it was refused */
    private function decode(UploadedFile $file, int $maxBytes): \GdImage|string
    {
        if (! $file->isValid() || $file->getSize() > $maxBytes) {
            return __('cert::messages.image_too_large', ['mb' => (int) ($maxBytes / 1048576)]);
        }
        $bytes = (string) @file_get_contents($file->getRealPath());
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        $info = @getimagesizefromstring($bytes);

        if (! in_array($mime, self::MIMES, true) || ! $info || ! in_array($info['mime'] ?? '', self::MIMES, true)) {
            return __('cert::messages.image_type');
        }
        // Refuse a decompression bomb before GD allocates it.
        if (($info[0] ?? 0) * ($info[1] ?? 0) > 60_000_000) {
            return __('cert::messages.image_too_large', ['mb' => (int) ($maxBytes / 1048576)]);
        }

        $im = @imagecreatefromstring($bytes);

        return $im instanceof \GdImage ? $im : __('cert::messages.image_type');
    }

    private function fit(\GdImage $im, int $maxW, int $maxH): \GdImage
    {
        [$w, $h] = [imagesx($im), imagesy($im)];
        $scale = min(1, $maxW / $w, $maxH / $h);
        if ($scale >= 1) {
            return $im;
        }
        [$tw, $th] = [max(1, (int) ($w * $scale)), max(1, (int) ($h * $scale))];
        $out = imagecreatetruecolor($tw, $th);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled($out, $im, 0, 0, 0, 0, $tw, $th, $w, $h);
        imagedestroy($im);

        return $out;
    }
}
