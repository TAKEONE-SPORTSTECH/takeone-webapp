<?php

namespace App\Console\Commands;

use App\Models\ActivityCatalog;
use App\Services\Ai\AiManager;
use App\Traits\StoresBase64Images;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Batch-generate hero images for directory activities from their image_prompt
 * via the configured image provider (OpenAI Images). Skips entries that already
 * have an image unless --force. Best-effort per entry; failures are reported.
 *
 *   php artisan activities:generate-images            # only entries missing an image
 *   php artisan activities:generate-images --force    # regenerate all
 *   php artisan activities:generate-images --only=taekwondo-wt,judo
 */
class GenerateActivityImages extends Command
{
    use StoresBase64Images;

    protected $signature = 'activities:generate-images {--force : Regenerate even if an image exists} {--only= : Comma-separated slugs}';

    protected $description = 'Generate and attach hero images for directory activities via the configured image provider';

    public function handle(AiManager $ai): int
    {
        try {
            $driver = $ai->image(); // fails fast if no provider configured
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $query = ActivityCatalog::query()->whereNotNull('image_prompt')->where('image_prompt', '!=', '');

        if ($only = $this->option('only')) {
            $query->whereIn('slug', array_filter(array_map('trim', explode(',', $only))));
        }
        if (! $this->option('force')) {
            $query->where(fn ($q) => $q->whereNull('picture_url')->orWhere('picture_url', ''));
        }

        $entries = $query->orderBy('name')->get();
        if ($entries->isEmpty()) {
            $this->info('Nothing to generate — all matching entries already have an image (use --force to regenerate).');

            return self::SUCCESS;
        }

        $this->info("Generating {$entries->count()} image(s)…");
        $ok = 0;
        $fail = 0;

        foreach ($entries as $entry) {
            $this->line("• {$entry->name} … ");
            try {
                $dataUri = $driver->generate($entry->image_prompt);
                $path = $this->storeBase64Image($dataUri, 'activity-catalog/'.$entry->uuid, 'hero_'.substr(md5($entry->image_prompt), 0, 8));
                if ($path === null) {
                    throw new \RuntimeException('store failed');
                }

                $oldPath = $entry->picture_url;
                $entry->picture_url = $path;
                $entry->save();
                if ($oldPath && $oldPath !== $path && Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }

                $ok++;
                $this->info('  ✓ attached');
            } catch (\Throwable $e) {
                $fail++;
                $this->error('  ✗ '.$e->getMessage());
            }
        }

        $this->newLine();
        $this->info("Done. {$ok} generated, {$fail} failed.");

        return self::SUCCESS;
    }
}
