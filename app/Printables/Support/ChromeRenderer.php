<?php

namespace App\Printables\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Headless Chromium, as a printer. Each call writes one self-contained page to a
 * private scratch folder, prints it to PDF (or screenshots it to PNG) and removes
 * the folder again.
 *
 * Locked down on purpose: the page may read local files (our own built renderer
 * and fonts) and resolves NO host names, so a design can never make the printer
 * fetch anything — everything it needs is already inside the page.
 */
class ChromeRenderer
{
    private const BINARIES = ['/usr/bin/chromium', '/usr/bin/chromium-browser', '/usr/bin/google-chrome'];

    public function binary(): string
    {
        $configured = (string) config('printables.chrome', '');
        foreach (array_filter([$configured, ...self::BINARIES]) as $bin) {
            if (is_file($bin) && is_executable($bin)) {
                return $bin;
            }
        }
        throw new RuntimeException('No Chromium found to print with — set PRINTABLES_CHROME.');
    }

    /** file:// address of the built print renderer (resources/js/islands/printables-print.jsx). */
    public function entryScript(): string
    {
        return $this->asset($this->entry()['file']);
    }

    /** @return list<string> file:// addresses of its stylesheets */
    public function entryStyles(): array
    {
        // The stylesheet can sit on a shared chunk the entry imports, not on the entry itself.
        $manifest = $this->manifest();
        $css = [];
        $walk = function (array $chunk, array $seen = []) use (&$walk, &$css, $manifest) {
            foreach ($chunk['css'] ?? [] as $file) {
                $css[$file] = true;
            }
            foreach ($chunk['imports'] ?? [] as $key) {
                if (! isset($seen[$key]) && isset($manifest[$key])) {
                    $walk($manifest[$key], $seen + [$key => true]);
                }
            }
        };
        $walk($this->entry());

        return array_map(fn ($file) => $this->asset($file), array_keys($css));
    }

    private function manifest(): array
    {
        return json_decode((string) @file_get_contents(public_path('build/manifest.json')), true) ?: [];
    }

    public function pdf(string $html): string
    {
        return $this->run($html, fn (string $out) => ['--print-to-pdf='.$out, '--no-pdf-header-footer'], 'pdf');
    }

    /**
     * `$transparent` keeps the page's own see-through background in the PNG
     * (an overlay laid onto video, App\Media\BoutShare); without it Chrome
     * paints white behind the page, which is what a printable wants.
     */
    public function png(string $html, float $width, float $height, int $dpi = 96, bool $transparent = false): string
    {
        $scale = max(0.5, min(6, $dpi / 96));

        return $this->run($html, fn (string $out) => [
            '--screenshot='.$out,
            '--window-size='.(int) ceil($width).','.(int) ceil($height),
            '--force-device-scale-factor='.$scale,
            '--hide-scrollbars',
            ...($transparent ? ['--default-background-color=00000000'] : []),
        ], 'png');
    }

    /**
     * Several pages printed to PDF at once — up to $concurrency Chromes side by side,
     * one per CPU core — returned in the order given. A page that fails is drawn once
     * more on its own (run()), exactly as a single print is.
     *
     * @param  list<string>  $htmls
     * @return list<string>
     */
    public function pdfMany(array $htmls, int $concurrency = 3, int $timeout = 300, ?callable $done = null): array
    {
        $args = fn (string $out) => ['--print-to-pdf='.$out, '--no-pdf-header-footer'];
        $concurrency = max(1, $concurrency);
        $queue = array_keys($htmls);
        $running = [];
        $results = [];
        $failed = [];

        while ($queue || $running) {
            while ($queue && count($running) < $concurrency) {
                $i = array_shift($queue);
                $running[$i] = $this->start($htmls[$i], $args, 'pdf', $timeout);
            }
            foreach ($running as $i => $job) {
                try {
                    $job['process']->checkTimeout();
                } catch (\Symfony\Component\Process\Exception\ProcessTimedOutException) {
                    // finish() below reports it as a failed page.
                }
                if ($job['process']->isRunning()) {
                    continue;
                }
                unset($running[$i]);
                try {
                    $results[$i] = $this->finish($job, 'pdf');
                    if ($done) {
                        $done($i);
                    }
                } catch (RuntimeException $e) {
                    $failed[] = $i;
                    Log::warning('printables.render_retry', ['ext' => 'pdf', 'batch' => true, 'error' => Str::limit($e->getMessage(), 200)]);
                }
            }
            if ($running) {
                usleep(50_000);
            }
        }
        foreach ($failed as $i) {
            $results[$i] = $this->runOnce($htmls[$i], $args, 'pdf', $timeout);
            if ($done) {
                $done($i);
            }
        }
        ksort($results);

        return array_values($results);
    }

    /**
     * Chrome now and then exits with no file, or with half of one, under load.
     * A page is drawn twice before giving up, and only a COMPLETE file counts —
     * a cut-off PDF used to slip through and break the whole bundle at the join.
     */
    private function run(string $html, callable $args, string $ext): string
    {
        try {
            return $this->runOnce($html, $args, $ext);
        } catch (RuntimeException $e) {
            Log::warning('printables.render_retry', ['ext' => $ext, 'error' => Str::limit($e->getMessage(), 200)]);

            return $this->runOnce($html, $args, $ext);
        }
    }

    private function complete(string $bytes, string $ext): bool
    {
        return $ext === 'pdf'
            ? str_starts_with($bytes, '%PDF') && str_contains(substr($bytes, -2048), '%%EOF')
            : str_starts_with($bytes, "\x89PNG") && str_contains(substr($bytes, -64), 'IEND');
    }

    private function runOnce(string $html, callable $args, string $ext, int $timeout = 90): string
    {
        $job = $this->start($html, $args, $ext, $timeout);
        try {
            $job['process']->wait();
        } catch (\Throwable $e) {
            $this->remove($job['dir']);   // a timed-out print leaves no scratch behind
            throw $e;
        }

        return $this->finish($job, $ext);
    }

    /**
     * One page handed to a Chromium of its own, started and not waited for: its
     * private scratch folder, the page, and where the file will appear.
     *
     * @return array{process: Process, dir: string, out: string}
     */
    private function start(string $html, callable $args, string $ext, int $timeout): array
    {
        $dir = storage_path('app/private/printables-render/'.Str::random(24));
        @mkdir($dir, 0770, true);
        $page = $dir.'/page.html';
        $out = $dir.'/out.'.$ext;
        file_put_contents($page, $html);

        try {
            $process = new Process([
                // Lowest CPU priority: a print run must never starve the web pages beside it.
                ...(is_executable('/usr/bin/nice') ? ['/usr/bin/nice', '-n', '19'] : []),
                $this->binary(),
                '--headless=new',
                '--no-sandbox',
                '--disable-gpu',
                '--disable-extensions',
                '--disable-background-networking',
                '--no-first-run',
                '--no-default-browser-check',
                '--allow-file-access-from-files',
                '--host-resolver-rules=MAP * ~NOTFOUND',
                '--user-data-dir='.$dir.'/profile',
                '--virtual-time-budget=15000',
                '--run-all-compositor-stages-before-draw',
                ...$args($out),
                'file://'.$page,
            ], $dir, ['HOME' => $dir, 'XDG_CONFIG_HOME' => $dir, 'XDG_CACHE_HOME' => $dir], null, $timeout);
            $process->start();
        } catch (\Throwable $e) {
            $this->remove($dir);
            throw $e;
        }

        return ['process' => $process, 'dir' => $dir, 'out' => $out];
    }

    /** The file a started page left, checked complete — and its scratch folder removed whatever happened. */
    private function finish(array $job, string $ext): string
    {
        ['process' => $process, 'dir' => $dir, 'out' => $out] = $job;
        try {
            $bytes = is_file($out) ? (string) file_get_contents($out) : '';
            if ($bytes === '' || ! $this->complete($bytes, $ext)) {
                // Chrome's stderr is mostly dbus noise; keep the lines that are not.
                $err = collect(preg_split('/\R/', trim($process->getErrorOutput())))->reject(fn ($l) => str_contains($l, 'dbus'))->implode(' ');
                throw new RuntimeException('The design could not be printed ('.($bytes === '' ? 'no file' : 'incomplete file').', exit '.$process->getExitCode().'): '.Str::limit($err, 300));
            }

            return $bytes;
        } finally {
            $this->remove($dir);
        }
    }

    private function entry(): array
    {
        $manifest = json_decode((string) @file_get_contents(public_path('build/manifest.json')), true) ?: [];
        $entry = $manifest['resources/js/islands/printables-print.jsx'] ?? null;
        if (! $entry) {
            throw new RuntimeException('The print renderer is not built — run the asset build.');
        }

        return $entry;
    }

    private function asset(string $path): string
    {
        return 'file://'.public_path('build/'.ltrim($path, '/'));
    }

    private function remove(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() && ! $item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}
