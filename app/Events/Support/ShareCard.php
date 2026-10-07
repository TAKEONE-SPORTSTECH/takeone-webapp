<?php

namespace App\Events\Support;

use Ahl\Certificates\Support\TextShaper;
use App\Events\Models\ClubEvent;
use App\Support\Modules\ModuleRegistry;
use App\Support\Modules\Surfaces\ProvidesEventArtwork;
use App\Support\Palette;
use App\Support\StoragePath;
use Illuminate\Support\Facades\Storage;

/**
 * What a shared event link looks like in WhatsApp, Instagram, Telegram,
 * iMessage… — its title, description and picture (owner, 2026-10-04).
 *
 * The picture, by what the event has:
 *   · landscape artwork → that artwork, as it is;
 *   · portrait artwork only → a drawn card: the whole poster on the left, the
 *     event's colour on the right with the organiser's logo and "Tap to enroll";
 *   · no artwork → a drawn card: the event's colour, the logo, the event's name
 *     and "Tap to enroll".
 * The date, venue and organiser are in the DESCRIPTION, so the card does not
 * repeat them.
 *
 * Written in the event's own language (its source locale). Drawn cards are
 * cached under cache/share-cards/, named by a hash of everything on them, so a
 * changed event is drawn afresh and an unchanged one is never drawn twice.
 */
final class ShareCard
{
    public const W = 1200;

    public const H = 630;

    private const FONTS = 'packages/ahl/certificates/resources/fonts/';

    /**
     * A link-preview reader rather than a person: the apps that fetch a shared
     * link to draw its card. Only decides which ANSWER it gets — the preview
     * tags it would read off the page anyway — never access.
     */
    public static function isLinkReader(\Illuminate\Http\Request $request): bool
    {
        return (bool) preg_match('/WhatsApp|facebookexternalhit|Facebot|meta-externalagent|Instagram|TelegramBot|Twitterbot|Slackbot|LinkedInBot|Discordbot|Pinterest|SkypeUriPreview|Applebot|Google-PageRenderer|redditbot|Snapchat|vkShare|Viber|Line\//i',
            (string) $request->userAgent());
    }

    /** The tags a page puts in its <head>. */
    public function meta(ClubEvent $event): array
    {
        $locale = $this->locale($event);

        return [
            'title' => $this->title($event, $locale),
            'description' => $this->description($event, $locale),
            'image' => $this->imageUrl($event),
            'locale' => $locale,
        ];
    }

    public function title(ClubEvent $event, string $locale): string
    {
        $status = $this->status($event);

        return trim((string) $event->getRawOriginal('title')).($status ? ' · '.__('events::events.share_status_'.$status, [], $locale) : '');
    }

    public function description(ClubEvent $event, string $locale): string
    {
        $parts = [];
        if ($event->date) {
            $parts[] = \Carbon\Carbon::parse($event->date)->locale($locale)->translatedFormat('D j M Y');
        }
        $venue = trim((string) $event->getRawOriginal('location'));
        $parts[] = $venue !== '' && strtoupper($venue) !== 'TBA' ? $venue : __('events::events.share_venue_tba', [], $locale);
        $host = $event->host()->name;
        $line = implode(' · ', array_filter($parts));
        if ($host !== '') {
            $line .= ' · '.__('events::events.share_organised_by', ['host' => $host], $locale);
        }
        $cta = match ($this->status($event)) {
            'club_open' => __('events::events.share_cta_club', [], $locale),
            'open' => __('events::events.share_cta', [], $locale),
            default => '',
        };

        return trim($line.($cta !== '' ? '. '.$cta : ''));
    }

    /** The picture's address: the landscape artwork, else our drawn card. */
    public function imageUrl(ClubEvent $event): string
    {
        $landscape = $this->artwork($event, 'landscape');
        if ($landscape) {
            return $landscape['url'];
        }

        return route('events.share-card', ['event' => $event->uuid]).'?v='.substr($this->hash($event), 0, 12);
    }

    /** The drawn card's file on the local disk — drawing it first if it is not there. Null if GD cannot. */
    public function file(ClubEvent $event): ?string
    {
        $path = StoragePath::shareCard((string) $event->uuid, $this->hash($event));
        $disk = Storage::disk('local');
        if ($disk->exists($path)) {
            return $disk->path($path);
        }
        if (! function_exists('imagecreatetruecolor') || ! function_exists('imagettftext')) {
            return null;
        }

        $jpeg = $this->draw($event);
        if ($jpeg === null) {
            return null;
        }
        $disk->put($path, $jpeg);

        return $disk->path($path);
    }

    // ── what the card says ─────────────────────────────────────────────────

    /** club_open | open | closed | results | null */
    private function status(ClubEvent $event): ?string
    {
        if ($event->hasEnded() || ! empty($event->results)) {
            return 'results';
        }
        $open = (bool) (app(EntryService::class)->entriesState($event)['open'] ?? false);
        if (! $open) {
            return 'closed';
        }
        $channels = app(EntryChannels::class);
        $clubs = $channels->allows($event, EntryChannels::CLUB);
        // A host that takes its athletes from clubs (a federation) — clubs enter.
        $hostTakesClubs = ! in_array($event->host()->kind, ['club', 'none'], true);

        if ($clubs && ($hostTakesClubs || ! $channels->allows($event, EntryChannels::INDIVIDUAL))) {
            return 'club_open';
        }

        return 'open';
    }

    private function locale(ClubEvent $event): string
    {
        $src = method_exists($event, 'sourceLocale') ? (string) $event->sourceLocale() : 'en';

        return in_array($src, ['en', 'ar'], true) ? $src : 'en';
    }

    /** @return array{url: string, disk: string, path: string}|null */
    private function artwork(ClubEvent $event, string $shape): ?array
    {
        // The event's own first image, when it is that shape.
        $own = is_array($event->images) ? ($event->images[0] ?? null) : null;
        if (is_string($own) && $own !== '') {
            $rel = preg_replace('#^(storage/|public/)#', '', ltrim($own, '/'));
            $disk = Storage::disk('public');
            if ($disk->exists($rel)) {
                $size = @getimagesize($disk->path($rel));
                $isLandscape = $size && $size[0] > $size[1];
                if (($shape === 'landscape') === (bool) $isLandscape) {
                    return ['url' => file_url($own), 'disk' => 'public', 'path' => $rel];
                }
            }

            return null;   // the event has its own artwork; a host's is not consulted
        }

        foreach (app(ModuleRegistry::class)->contributingTo(ProvidesEventArtwork::class) as $module) {
            $art = rescue(fn () => $module->eventArtwork($event, $shape), null, report: true);
            if (is_array($art) && ! empty($art['url']) && ! empty($art['path'])) {
                return $art;
            }
        }

        return null;
    }

    /** @return array{disk: string, path: string}|null */
    private function logoFile(ClubEvent $event): ?array
    {
        if ($event->tenant_id && $event->tenant?->logo) {
            return ['disk' => 'public', 'path' => preg_replace('#^(storage/|public/)#', '', ltrim((string) $event->tenant->logo, '/'))];
        }
        foreach (app(ModuleRegistry::class)->contributingTo(ProvidesEventArtwork::class) as $module) {
            $logo = rescue(fn () => $module->eventLogoFile($event), null, report: true);
            if (is_array($logo) && ! empty($logo['path'])) {
                return $logo;
            }
        }

        return null;
    }

    private function hash(ClubEvent $event): string
    {
        $portrait = $this->artwork($event, 'portrait');
        $logo = $this->logoFile($event);

        return hash('sha256', json_encode([
            'v' => 1,
            'status' => $this->status($event),
            'title' => (string) $event->getRawOriginal('title'),
            'color' => (string) $event->color,
            'locale' => $this->locale($event),
            'portrait' => $portrait['path'] ?? null,
            'logo' => $logo['path'] ?? null,
        ]));
    }

    // ── drawing ────────────────────────────────────────────────────────────

    private function draw(ClubEvent $event): ?string
    {
        $locale = $this->locale($event);
        $im = imagecreatetruecolor(self::W, self::H);
        imagealphablending($im, true);
        imagesavealpha($im, false);

        $portrait = $this->artwork($event, 'portrait');
        $panelX = 0;
        if ($portrait) {
            $src = $this->load($portrait);
            if ($src) {
                $panelX = (int) round(self::W * 0.42);
                $this->cover($im, $src, 0, 0, $panelX, self::H);
                imagedestroy($src);
            }
        }

        // The event's colour, lightened toward its tail (#hex → #hex + b0 over white).
        [$r, $g, $b] = $this->rgb(Palette::safe((string) ($event->color ?: '#1e2c4f')));
        $pw = self::W - $panelX;
        for ($i = 0; $i <= $pw + self::H; $i++) {
            $t = $i / ($pw + self::H);
            $mix = 0.31 * $t;
            $c = imagecolorallocate($im, (int) ($r + (255 - $r) * $mix), (int) ($g + (255 - $g) * $mix), (int) ($b + (255 - $b) * $mix));
            imageline($im, $panelX + $i, 0, $panelX + $i - self::H, self::H, $c);
        }
        // Repaint the poster over any diagonal that ran into it.
        if ($portrait && ($src = $this->load($portrait))) {
            $this->cover($im, $src, 0, 0, $panelX, self::H);
            imagedestroy($src);
        }
        // The two soft circles of the hero band.
        $soft = imagecolorallocatealpha($im, 255, 255, 255, 116);
        imagefilledellipse($im, self::W - 40, 20, 420, 420, $soft);
        imagefilledellipse($im, self::W - 170, self::H - 90, 220, 220, $soft);

        $cx = $panelX + (int) ($pw / 2);
        $logo = $this->logoFile($event);
        // "Tap to enroll" while it can be entered; otherwise what a tap now leads to.
        $tap = __(match ($this->status($event)) {
            'results' => 'events::events.share_tap_results',
            'closed' => 'events::events.share_tap_view',
            default => 'events::events.share_tap',
        }, [], $locale);

        if ($portrait) {
            // Poster card: the logo, centred and large, then "Tap to enroll". No name —
            // the title right under the picture already says it.
            $y = (int) (self::H / 2 - 150);
            if ($logo) {
                $y = $this->logo($im, $logo, $cx, $y, 220) + 46;
            } else {
                $y = (int) (self::H / 2 - 30);
            }
            $this->text($im, $tap, $cx, $y, 52, true, [255, 255, 255, 0], $pw - 80);
        } else {
            $y = 120;
            if ($logo) {
                $y = $this->logo($im, $logo, $cx, 90, 170) + 34;
            }
            $y = $this->text($im, (string) $event->getRawOriginal('title'), $cx, $y, 58, true, [255, 255, 255, 0], self::W - 180, 2) + 22;
            $this->text($im, $tap, $cx, $y, 32, false, [255, 255, 255, 30], self::W - 180);
        }

        ob_start();
        imagejpeg($im, null, 86);
        imagedestroy($im);

        return ob_get_clean() ?: null;
    }

    private function load(array $file): ?\GdImage
    {
        try {
            $bytes = Storage::disk($file['disk'])->get($file['path']);
        } catch (\Throwable) {
            return null;
        }
        $img = $bytes ? @imagecreatefromstring($bytes) : false;

        return $img ?: null;
    }

    /** Draw $src to fill the box, cropping the overflow (centred across, kept to the TOP — a poster's name is at its top). */
    private function cover(\GdImage $dst, \GdImage $src, int $x, int $y, int $w, int $h): void
    {
        $sw = imagesx($src);
        $sh = imagesy($src);
        $scale = max($w / $sw, $h / $sh);
        $cw = (int) round($w / $scale);
        $ch = (int) round($h / $scale);
        imagecopyresampled($dst, $src, $x, $y, (int) (($sw - $cw) / 2), 0, $w, $h, $cw, $ch);
    }

    /** The logo, centred on $cx with its top at $top, fitted in a $box square. Returns its bottom. */
    private function logo(\GdImage $im, array $file, int $cx, int $top, int $box): int
    {
        $src = $this->load($file);
        if (! $src) {
            return $top;
        }
        $sw = imagesx($src);
        $sh = imagesy($src);
        $scale = min($box / $sw, $box / $sh);
        $w = (int) round($sw * $scale);
        $h = (int) round($sh * $scale);
        imagealphablending($im, true);
        imagecopyresampled($im, $src, $cx - (int) ($w / 2), $top + (int) (($box - $h) / 2), 0, 0, $w, $h, $sw, $sh);
        imagedestroy($src);

        return $top + $box;
    }

    /**
     * Centred text, wrapped to $maxWidth, at most $maxLines lines (the last one
     * shrinks the size rather than cutting a name off). Arabic is joined and
     * ordered by the certificates' TextShaper. Returns the bottom of the block.
     */
    private function text(\GdImage $im, string $text, int $cx, int $top, int $size, bool $bold, array $rgba, int $maxWidth, int $maxLines = 1): int
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
        if ($text === '') {
            return $top;
        }
        $arabic = TextShaper::hasArabic($text);
        $font = base_path(self::FONTS.($arabic ? ($bold ? 'Amiri-Bold.ttf' : 'Amiri-Regular.ttf') : ($bold ? 'Inter-Bold.ttf' : 'Inter-Regular.ttf')));
        $color = imagecolorallocatealpha($im, $rgba[0], $rgba[1], $rgba[2], $rgba[3]);

        // Shrink until it fits in $maxLines.
        for ($s = $size; $s >= 20; $s -= 2) {
            $lines = $this->wrap($text, $font, $s, $maxWidth, $arabic);
            if (count($lines) <= $maxLines) {
                break;
            }
        }
        $lineH = (int) round($s * 1.25);
        $y = $top;
        foreach ($lines as $line) {
            $box = imagettfbbox($s, 0, $font, $line);
            $w = abs($box[2] - $box[0]);
            imagettftext($im, $s, 0, $cx - (int) ($w / 2), $y + $s, $color, $font, $line);
            $y += $lineH;
        }

        return $y;
    }

    /** @return list<string> lines in VISUAL order, ready to draw left to right */
    private function wrap(string $text, string $font, int $size, int $max, bool $arabic): array
    {
        if ($arabic) {
            // TextShaper wraps by characters and returns visual lines; widen until they fit.
            $shaper = new TextShaper;
            for ($chars = 60; $chars >= 8; $chars -= 4) {
                $lines = $shaper->lines($text, $chars);
                $fits = collect($lines)->every(fn ($l) => abs(imagettfbbox($size, 0, $font, $l)[2]) <= $max);
                if ($fits) {
                    return $lines;
                }
            }

            return $shaper->lines($text, 8);
        }

        $lines = [];
        $cur = '';
        foreach (explode(' ', $text) as $word) {
            $try = $cur === '' ? $word : $cur.' '.$word;
            if ($cur !== '' && abs(imagettfbbox($size, 0, $font, $try)[2]) > $max) {
                $lines[] = $cur;
                $cur = $word;
            } else {
                $cur = $try;
            }
        }
        if ($cur !== '') {
            $lines[] = $cur;
        }

        return $lines;
    }

    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
