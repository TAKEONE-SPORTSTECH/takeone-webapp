<?php

namespace App\Providers;

use App\Models\Tenant;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Allow {club} route parameter to be resolved by slug OR numeric ID
        Route::bind('club', function ($value) {
            return is_numeric($value)
                ? Tenant::findOrFail($value)
                : Tenant::where('slug', $value)->firstOrFail();
        });
    }
}
