<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\Admin\AuthController;
use App\Http\Controllers\Api\V1\Admin\ImportController;
use App\Http\Controllers\Api\V1\Admin\SettingsController;
use App\Http\Controllers\Api\V1\Admin\TariffController;
use App\Http\Controllers\Api\V1\CalculationController;
use App\Http\Controllers\Api\V1\CalculatorBootstrapController;
use App\Http\Controllers\Api\V1\DeliveryChannelController;
use App\Http\Controllers\Api\V1\PlatformController;
use App\Http\Controllers\Api\V1\PublicSettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('calculator/bootstrap', [CalculatorBootstrapController::class, 'show']);
    Route::get('platforms', [PlatformController::class, 'index']);
    Route::get('delivery-channels', [DeliveryChannelController::class, 'index']);
    Route::get('settings/public', [PublicSettingsController::class, 'show']);
    Route::post('calculations', [CalculationController::class, 'store']);

    Route::prefix('admin')->group(function (): void {
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:admin-login');

        Route::middleware(['auth:sanctum', 'admin'])->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout']);
            Route::get('me', [AuthController::class, 'me']);

            Route::get('settings', [SettingsController::class, 'show']);
            Route::put('settings/exchange-rate', [SettingsController::class, 'updateExchangeRate']);

            Route::get('tariffs', [TariffController::class, 'index']);

            Route::get('imports', [ImportController::class, 'index']);
            Route::post('imports', [ImportController::class, 'store'])
                ->middleware('throttle:admin-import-upload');
            Route::get('imports/{import}', [ImportController::class, 'show']);
            Route::get('imports/{import}/download', [ImportController::class, 'download']);
            Route::post('imports/{import}/activate', [ImportController::class, 'activate']);
            Route::post('imports/{import}/rollback', [ImportController::class, 'rollback']);
        });
    });
});
