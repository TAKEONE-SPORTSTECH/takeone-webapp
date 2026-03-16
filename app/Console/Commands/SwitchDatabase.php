<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SwitchDatabase extends Command
{
    protected $signature = 'db:switch {--status : Show which database is currently active}';
    protected $description = 'Switch between database.sqlite (original) and database_new.sqlite (new)';

    public function handle(): void
    {
        $envPath = base_path('.env');
        $envContent = file_get_contents($envPath);

        $originalPath = database_path('database.sqlite');
        $newPath = database_path('database_new.sqlite');

        // Detect current active DB_DATABASE line (uncommented)
        preg_match('/^DB_DATABASE=(.*)$/m', $envContent, $matches);
        $current = trim($matches[1] ?? '');

        if ($this->option('status')) {
            $label = str_contains($current, 'database_new') ? 'NEW (database_new.sqlite)' : 'ORIGINAL (database.sqlite)';
            $this->info("Active database: {$label}");
            $this->line("Path: " . ($current ?: $originalPath . ' (default)'));
            return;
        }

        // Toggle
        if (str_contains($current, 'database_new')) {
            $newValue = $originalPath;
            $label = 'ORIGINAL (database.sqlite)';
        } else {
            $newValue = $newPath;
            $label = 'NEW (database_new.sqlite)';
        }

        // Replace or insert the DB_DATABASE line (handling commented-out line)
        if (preg_match('/^DB_DATABASE=.*$/m', $envContent)) {
            // Uncommented line exists — replace it
            $envContent = preg_replace('/^DB_DATABASE=.*$/m', "DB_DATABASE={$newValue}", $envContent);
        } elseif (preg_match('/^#\s*DB_DATABASE=.*$/m', $envContent)) {
            // Only commented line — insert active line after it
            $envContent = preg_replace('/^(#\s*DB_DATABASE=.*)$/m', "$1\nDB_DATABASE={$newValue}", $envContent);
        } else {
            // Not found at all — append after DB_CONNECTION
            $envContent = preg_replace('/^(DB_CONNECTION=.*)$/m', "$1\nDB_DATABASE={$newValue}", $envContent);
        }

        file_put_contents($envPath, $envContent);

        // Clear config cache
        $this->call('config:clear');

        $this->info("Switched to: {$label}");
    }
}
