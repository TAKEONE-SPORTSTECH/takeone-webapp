<?php

namespace App\Printables;

use App\Printables\Models\PrintableAsset;
use App\Printables\Models\PrintableDesign;
use App\Printables\Support\ChromeRenderer;
use App\Printables\Support\Fonts;
use App\Printables\Support\Formats;
use App\Printables\Support\Kinds;
use Illuminate\Support\Facades\Storage;

/**
 * The designer's PUBLIC printing door: a design, filled with one recipient's
 * values, as a PDF (or a PNG for screens that cannot show a PDF).
 *
 *   Printer::render($design, ['name' => 'Sara …', 'qr' => 'data:image/png…'], ['as' => 'pdf'])
 *
 * The page is drawn by the editor's own renderer (resources/js/islands/printables-print.jsx)
 * in headless Chromium, so the paper is what the organiser saw. Nothing is fetched
 * from the network: pictures go in as data: URIs, fonts and code as local files.
 */
final class Printer
{
    /**
     * @param  array<string, string>  $values  text fields by key; picture fields (photo, qr, logo) as data: URIs
     * @param  array{as?: 'pdf'|'png', dpi?: int, stamp?: ?string}  $options
     */
    public static function render(PrintableDesign $design, array $values, array $options = []): string
    {
        $kind = app(Kinds::class)->get($design->kind);
        $owner = $design->owner;
        $fields = $kind && $owner ? $kind->fields($owner, $design->slot ?: null) : [];
        $w = Formats::px((float) $design->width_mm);
        $h = Formats::px((float) $design->height_mm);

        $boot = [
            'document' => $design->document,
            'size' => ['w' => $w, 'h' => $h],
            'fonts' => Fonts::forFiles(),
            'fields' => $fields,
            'assets' => $design->assets()->get()->map(fn (PrintableAsset $a) => ['uuid' => $a->uuid, 'url' => self::dataUri($a)])->filter(fn ($a) => $a['url'])->values(),
            // Every field gets a value — absent ones print empty, never as a sample.
            'values' => collect($fields)->mapWithKeys(fn ($f) => [$f['key'] => (string) ($values[$f['key']] ?? '')])->all() + array_map('strval', $values),
            'stamp' => $options['stamp'] ?? null,
        ];

        $renderer = app(ChromeRenderer::class);
        $html = view('printables::render', [
            'boot' => $boot,
            'widthMm' => (float) $design->width_mm,
            'heightMm' => (float) $design->height_mm,
            'script' => $renderer->entryScript(),
            'styles' => $renderer->entryStyles(),
        ])->render();

        return ($options['as'] ?? 'pdf') === 'png'
            ? $renderer->png($html, $w, $h, (int) ($options['dpi'] ?? 96))
            : $renderer->pdf($html);
    }

    /**
     * One design printed for MANY recipients as one PDF, in the order given — a print
     * run. Instead of a Chromium per recipient, recipients are drawn a batch to a page
     * (one Chromium, fonts and artwork loaded once) and several batches at once, one
     * per core (config printables.batch). Every page is the same page render() draws.
     *
     * @param  list<array{values: array<string, string>, stamp?: ?string}>  $items
     * @param  callable(int $done, int $total): void|null  $progress
     */
    public static function renderMany(PrintableDesign $design, array $items, ?callable $progress = null): string
    {
        if ($items === []) {
            return '';
        }
        $kind = app(Kinds::class)->get($design->kind);
        $owner = $design->owner;
        $fields = $kind && $owner ? $kind->fields($owner, $design->slot ?: null) : [];
        $renderer = app(ChromeRenderer::class);
        $base = [
            'document' => $design->document,
            'size' => ['w' => Formats::px((float) $design->width_mm), 'h' => Formats::px((float) $design->height_mm)],
            'fonts' => Fonts::forFiles(),
            'fields' => $fields,
            'assets' => $design->assets()->get()->map(fn (PrintableAsset $a) => ['uuid' => $a->uuid, 'url' => self::dataUri($a)])->filter(fn ($a) => $a['url'])->values(),
        ];
        $empty = collect($fields)->mapWithKeys(fn ($f) => [$f['key'] => ''])->all();
        $script = $renderer->entryScript();
        $styles = $renderer->entryStyles();

        $chunks = array_chunk($items, max(1, (int) config('printables.batch.per_chromium', 20)));
        $htmls = array_map(fn (array $chunk) => view('printables::render', [
            'boot' => $base + ['items' => array_map(fn ($it) => [
                // Every field gets a value — absent ones print empty, never as a sample.
                'values' => array_map('strval', $it['values']) + $empty,
                'stamp' => $it['stamp'] ?? null,
            ], $chunk)],
            'widthMm' => (float) $design->width_mm,
            'heightMm' => (float) $design->height_mm,
            'script' => $script,
            'styles' => $styles,
        ])->render(), $chunks);

        $total = count($items);
        $done = 0;
        $pdfs = $renderer->pdfMany($htmls, (int) config('printables.batch.parallel', 3), 300, function (int $i) use ($chunks, $total, &$done, $progress) {
            $done += count($chunks[$i]);
            if ($progress) {
                $progress(min($done, $total), $total);
            }
        });

        return self::join(array_map(fn (string $pdf) => fn () => $pdf, $pdfs));
    }

    /** Whether print runs go through renderMany() (config printables.batch.enabled; OFF by default). */
    public static function batching(): bool
    {
        return (bool) config('printables.batch.enabled', false);
    }

    /**
     * Small pages (badges) laid out on A4 sheets for printing — as many to a sheet
     * as fit (four A6), with dashed cut lines — and joined into one PDF. Each page
     * is drawn as a 300 dpi picture first, so the sheet is exactly the design.
     * A page too big to share a sheet is printed on its own, at its own size.
     *
     * @param  list<array{0: PrintableDesign, 1: array<string, string>}>  $pages  design + values, in order
     */
    public static function sheets(array $pages, ?callable $progress = null): string
    {
        $first = $pages[0][0] ?? null;
        if (! $first) {
            return '';
        }
        [$w, $h] = [(float) $first->width_mm, (float) $first->height_mm];
        $cols = (int) floor(210 / max(1, $w));
        $rows = (int) floor(297 / max(1, $h));
        if ($cols * $rows < 2) {
            return self::join(array_map(fn ($p) => fn () => self::render($p[0], $p[1]), $pages), $progress);
        }
        $per = $cols * $rows;
        $total = count($pages);
        $done = 0;
        $sheets = [];
        foreach (array_chunk($pages, $per) as $chunk) {
            $sheets[] = function () use ($chunk, $w, $h, $cols, $rows, $total, &$done, $progress) {
                $cells = '';
                foreach ($chunk as [$design, $values]) {
                    $png = self::render($design, $values, ['as' => 'png', 'dpi' => 300]);
                    $cells .= '<div class="c"><img src="data:image/png;base64,'.base64_encode($png).'" alt=""></div>';
                    $done++;
                    if ($progress) {
                        $progress($done, $total);
                    }
                }
                $gw = $cols * $w;
                $gh = $rows * $h;
                $html = '<!doctype html><html><head><meta charset="utf-8"><style>'
                    .'@page{size:210mm 297mm;margin:0}html,body{margin:0;padding:0}'
                    .'.g{position:absolute;left:'.round((210 - $gw) / 2, 2).'mm;top:'.round((297 - $gh) / 2, 2).'mm;width:'.$gw.'mm;height:'.$gh.'mm;'
                    .'display:grid;grid-template-columns:repeat('.$cols.','.$w.'mm);grid-auto-rows:'.$h.'mm}'
                    .'.c{position:relative;width:'.$w.'mm;height:'.$h.'mm;box-sizing:border-box;outline:0.2mm dashed #9aa3b2;outline-offset:-0.1mm}'
                    .'.c img{display:block;width:100%;height:100%}'
                    .'</style></head><body><div class="g">'.$cells.'</div></body></html>';

                return app(ChromeRenderer::class)->pdf($html);
            };
        }

        return self::join($sheets);
    }

    /**
     * Several printed PDFs as one, in order (poppler's pdfunite). Each item is a
     * callable returning one PDF, so pages are drawn one at a time and only file
     * paths are held, never every PDF in memory. $progress(done, total).
     *
     * @param  list<callable(): string>  $pages
     */
    public static function join(array $pages, ?callable $progress = null): string
    {
        $dir = storage_path('app/private/printables-render/join-'.\Illuminate\Support\Str::random(16));
        @mkdir($dir, 0770, true);
        try {
            $files = [];
            foreach (array_values($pages) as $i => $page) {
                $files[] = $file = sprintf('%s/%05d.pdf', $dir, $i);
                file_put_contents($file, $page());
                if ($progress) {
                    $progress($i + 1, count($pages));
                }
            }
            if (count($files) === 1) {
                return (string) file_get_contents($files[0]);
            }
            $out = $dir.'/all.pdf';
            $process = new \Symfony\Component\Process\Process(['pdfunite', ...$files, $out], $dir, null, null, 600);
            $process->run();
            if (! is_file($out)) {
                throw new \RuntimeException('The PDFs could not be joined: '.trim($process->getErrorOutput()));
            }

            return (string) file_get_contents($out);
        } finally {
            foreach (glob($dir.'/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($dir);
        }
    }

    private static function dataUri(PrintableAsset $a): ?string
    {
        $disk = Storage::disk($a->disk ?: 'local');

        return $disk->exists($a->path) ? 'data:'.$a->mime.';base64,'.base64_encode((string) $disk->get($a->path)) : null;
    }
}
