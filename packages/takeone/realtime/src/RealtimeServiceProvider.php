<?php

namespace Takeone\Realtime;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Takeone\Realtime\Contracts\Publisher;
use Takeone\Realtime\Mqtt\PhpMqttPublisher;

class RealtimeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/realtime.php', 'realtime');

        $this->app->singleton(RealtimeManager::class, fn ($app) => new RealtimeManager($app));
        $this->app->bind(Publisher::class, PhpMqttPublisher::class);
    }

    public function boot(): void
    {
        // Migrations (realtime_settings) load automatically — no publish step needed.
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Plugin views, namespaced as realtime::*
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'realtime');

        $this->registerRoutes();

        // Allow host apps to publish & tweak the config / views / migrations.
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/realtime.php' => config_path('realtime.php'),
            ], 'realtime-config');

            $this->publishes([
                __DIR__ . '/../resources/js/realtime.js' => resource_path('js/realtime.js'),
            ], 'realtime-assets');

            $this->publishes([
                __DIR__ . '/../docker' => base_path('docker/realtime'),
            ], 'realtime-docker');
        }
    }

    private function registerRoutes(): void
    {
        $admin = config('realtime.admin');

        // Admin plugin management screen (super-admin guarded).
        Route::middleware($admin['middleware'])
            ->prefix($admin['route_prefix'])
            ->name($admin['route_name'] . '.')
            ->group(__DIR__ . '/../routes/admin.php');

        // Browser token endpoint — any authenticated user may mint their own token.
        Route::middleware(['web', 'auth'])
            ->group(__DIR__ . '/../routes/web.php');
    }
}
