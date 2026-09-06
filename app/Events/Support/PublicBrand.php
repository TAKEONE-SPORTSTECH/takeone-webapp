<?php

namespace App\Events\Support;

use App\Models\ClubEvent;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * The identity a shared event link wears.
 *
 * A public event link is not a page on somebody else's website. It is opened
 * from an Instagram story or a WhatsApp message by a person who does not know
 * what TAKEONE is and has no reason to care: what they were sent is THE GULF
 * OPEN, and that is what has to be on the tab, the home-screen icon, the splash
 * and the share card. A platform logo in that footer tells them they landed
 * somewhere else, which is the one thing a shared link must never do.
 *
 * So the standalone shell is white-labelled, and this class is the single place
 * that decides what it is white-labelled AS: the event, backed by its host club.
 * Every surface of it — the manifest, the icons, the head tags, the footer —
 * asks here, so they cannot drift apart.
 *
 * This is presentation only. It grants nothing: whether a stranger may see the
 * page at all is still App\Events\Support\PublicEvent's decision, and every
 * caller here has already asked it.
 */
class PublicBrand
{
    /** The icon sizes the manifest declares, and the only ones the route serves. */
    public const ICON_SIZES = [180, 192, 512];

    /**
     * The app's page ground — what the phone's status bar is tinted with.
     *
     * The white the section pages, the entry form and the sealed management
     * screens sit on — everything except the cover, which paints itself black
     * and says so with its own theme-color.
     */
    public const GROUND = '#ffffff';

    /** How long a generated icon is worth keeping. It changes when a logo does. */
    private const ICON_TTL = 60 * 60 * 24 * 14;

    /**
     * Bumped whenever drawIcon() itself changes what it draws.
     *
     * The version hash is otherwise built from the logo and the colour alone,
     * so a change to the DRAWING would never reach a device that already cached
     * the URL — the home screen would keep the old mark until the club happened
     * to re-upload its logo. 2 = logo drawn at full tile width (was inset 62%).
     * 3 = the tile behind the logo is gone; the mark is drawn on transparency.
     */
    private const ICON_RENDER = 3;

    /**
     * @return array<string, mixed>
     */
    public function of(ClubEvent $event): array
    {
        $club = $event->tenant;

        return [
            'name' => $event->title,
            // Home-screen labels are clipped hard — roughly a dozen characters
            // on iOS. A truncated event title is a better badge than a
            // truncated platform name.
            'short_name' => \Illuminate\Support\Str::limit($event->title, 18, ''),
            'host' => $club?->club_name,
            'host_logo' => $club?->logo ? file_url($club->logo) : null,
            'color' => $this->color($event),
            'icon' => fn (int $size) => route('events.public.icon', [
                'event' => $event->uuid,
                'size' => $size,
                // A logo swap must reach a home screen that cached the old one.
                'v' => $this->version($event),
            ]),
        ];
    }

    /**
     * The icon URLs, keyed by size — the shape a view wants.
     *
     * Given to the page through PublicEvent's payload rather than resolved in
     * Blade, so no template ever reaches for a model of its own.
     *
     * @return array<int, string>
     */
    public function iconUrls(ClubEvent $event): array
    {
        $version = $this->version($event);

        return collect(self::ICON_SIZES)
            ->mapWithKeys(fn (int $s) => [$s => route('events.public.icon', [
                'event' => $event->uuid, 'size' => $s, 'v' => $version,
            ])])
            ->all();
    }

    /** The event's own colour, whitelisted — it lands in CSS and in JSON. */
    public function color(ClubEvent $event): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', (string) $event->color) ? $event->color : '#7c3aed';
    }

    /**
     * A cache-buster that changes when the mark would.
     *
     * Deliberately not a timestamp: the icon is installed on somebody's home
     * screen, and a URL that changes on every edit re-downloads it forever.
     */
    public function version(ClubEvent $event): string
    {
        return substr(md5(($event->tenant?->logo ?? '').'|'.$this->color($event).'|'.self::ICON_RENDER), 0, 8);
    }

    /**
     * The installable manifest — what makes the link behave like its own app.
     *
     * @return array<string, mixed>
     */
    public function manifest(ClubEvent $event): array
    {
        $brand = $this->of($event);
        $icon = $brand['icon'];

        return [
            'name' => $brand['name'],
            'short_name' => $brand['short_name'],
            'description' => \Illuminate\Support\Str::limit(strip_tags((string) $event->description), 180) ?: $brand['name'],
            // Opens on the event, not on a platform home page it has no
            // business showing.
            'start_url' => route('events.public', $event->uuid),
            'scope' => route('events.public', $event->uuid),
            'display' => 'standalone',
            'orientation' => 'portrait',
            // The splash the installed app shows while it opens. The same
            // white the pages sit on and the same the status bar is tinted
            // with — it used to be a near-black blue slab, which is a
            // background behind an icon that is deliberately drawn on none.
            'background_color' => self::GROUND,
            // The INSTALLED app's status bar. Not the event's colour: that
            // painted the top of the handset in the accent on every screen.
            // The app's own page ground, matching the theme-color meta the
            // browser reads (entry/layout).
            'theme_color' => self::GROUND,
            'icons' => array_map(fn (int $s) => [
                'src' => $icon($s),
                'sizes' => $s.'x'.$s,
                'type' => 'image/png',
                'purpose' => 'any',
            ], self::ICON_SIZES),
        ];
    }

    /**
     * The app icon: the club's mark, on nothing.
     *
     * PNG bytes, generated once and cached. A home-screen icon must be a real
     * square raster at a declared size — a transparent logo of arbitrary shape
     * is not installable, and an installer that silently refuses is exactly the
     * failure "it should feel like its own app" cannot survive.
     *
     * This endpoint is open, so it is cheap by construction: a fixed size
     * allowlist, and the work happens once per event per size.
     */
    public function icon(ClubEvent $event, int $size): string
    {
        abort_unless(in_array($size, self::ICON_SIZES, true), 404);

        return Cache::remember(
            "event-icon:{$event->uuid}:{$size}:{$this->version($event)}",
            self::ICON_TTL,
            fn () => $this->drawIcon($event, $size),
        );
    }

    /* ==================== Internals ==================== */

    private function drawIcon(ClubEvent $event, int $size): string
    {
        $canvas = imagecreatetruecolor($size, $size);

        /*
         * Transparent ground, always.
         *
         * A club logo is a transparent PNG of somebody's own shape (Design
         * Rule #5) and it is never shown on a filled tile anywhere else on the
         * platform — the tab icon and the home-screen icon are not the one
         * exception. The tile used to be painted in the event's colour, which
         * put a coloured square behind every mark.
         *
         * ⚠️ Order matters: blending OFF while the transparent fill is laid
         * down (otherwise GD composites it onto the black a truecolor canvas
         * starts as and nothing is transparent), then ON to draw the logo, and
         * imagesavealpha so the alpha channel survives imagepng().
         */
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefilledrectangle($canvas, 0, 0, $size, $size, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagealphablending($canvas, true);

        $logo = $this->logo($event);

        if ($logo !== null) {
            // Contained, never cropped — a club logo is a shape somebody chose,
            // and a home-screen icon that cuts it in half is worse than none.
            //
            // The box is the WHOLE tile, not an inset of it. What an aspect
            // that is not square leaves over is now transparent rather than the
            // event's colour, so the mark is the icon and nothing else is.
            $lw = imagesx($logo);
            $lh = imagesy($logo);
            $box = $size;
            $scale = min($box / $lw, $box / $lh);
            $w = max(1, (int) round($lw * $scale));
            $h = max(1, (int) round($lh * $scale));

            imagecopyresampled($canvas, $logo, (int) (($size - $w) / 2), (int) (($size - $h) / 2), 0, 0, $w, $h, $lw, $lh);
            imagedestroy($logo);
        } else {
            /*
             * No logo is not an error — but a letter drawn on transparency is
             * invisible against a light home screen and against a dark one
             * both, so the FALLBACK keeps its coloured tile. The rule the user
             * asked for is about the club's mark, and here there isn't one.
             */
            [$r, $g, $b] = $this->rgb($this->color($event));
            imagealphablending($canvas, false);
            imagefilledrectangle($canvas, 0, 0, $size, $size, imagecolorallocate($canvas, $r, $g, $b));
            imagealphablending($canvas, true);

            $this->drawInitial($canvas, $size, $event->title ?? '?');
        }

        ob_start();
        imagepng($canvas, null, 6);
        $bytes = (string) ob_get_clean();
        imagedestroy($canvas);

        return $bytes;
    }

    /** The club's logo as a GD image, or null if there isn't a usable one. */
    private function logo(ClubEvent $event): ?\GdImage
    {
        $path = $event->tenant?->logo;

        if (! $path || str_contains($path, '://')) {
            return null;
        }

        $path = preg_replace('#^(storage/|public/)#', '', ltrim($path, '/'));

        try {
            if (! Storage::disk('local')->exists($path)) {
                return null;
            }
            $image = @imagecreatefromstring(Storage::disk('local')->get($path));
        } catch (\Throwable) {
            return null;
        }

        return $image ?: null;
    }

    private function drawInitial(\GdImage $canvas, int $size, string $title): void
    {
        $letter = mb_strtoupper(mb_substr(trim($title), 0, 1)) ?: '?';
        $white = imagecolorallocatealpha($canvas, 255, 255, 255, 30);

        // GD's built-in font only, so this never depends on a TTF being
        // installed on whichever machine happens to serve the request.
        $font = 5;
        $scale = max(1, (int) round($size / 24));
        $tile = imagecreatetruecolor(imagefontwidth($font), imagefontheight($font));
        imagefill($tile, 0, 0, imagecolorallocatealpha($tile, 0, 0, 0, 127));
        imagesavealpha($tile, true);
        imagestring($tile, $font, 0, 0, $letter, $white);

        $w = imagesx($tile) * $scale;
        $h = imagesy($tile) * $scale;
        imagecopyresized($canvas, $tile, (int) (($size - $w) / 2), (int) (($size - $h) / 2), 0, 0, $w, $h, imagesx($tile), imagesy($tile));
        imagedestroy($tile);
    }

    /** @return array{0:int,1:int,2:int} */
    private function rgb(string $hex): array
    {
        return [
            (int) hexdec(substr($hex, 1, 2)),
            (int) hexdec(substr($hex, 3, 2)),
            (int) hexdec(substr($hex, 5, 2)),
        ];
    }
}
