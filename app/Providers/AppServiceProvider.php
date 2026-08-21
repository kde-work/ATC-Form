<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\RateLimiter;
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
        // Публичный API отдаёт массивы/объекты без обёртки { "data": ... }.
        JsonResource::withoutWrapping();

        RateLimiter::for('admin-login', function (Request $request): Limit {
            return Limit::perMinute(5)->by($request->ip() ?? 'unknown');
        });

        RateLimiter::for('admin-import-upload', function (Request $request): Limit {
            $key = $request->user()?->getAuthIdentifier() ?? $request->ip() ?? 'unknown';

            return Limit::perMinute(10)->by((string) $key);
        });
    }
}
