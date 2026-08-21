<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\CalculationController;
use App\Http\Controllers\Api\V1\DeliveryChannelController;
use App\Http\Controllers\Api\V1\PlatformController;
use App\Http\Controllers\Api\V1\PublicSettingsController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('platforms', [PlatformController::class, 'index']);
    Route::get('delivery-channels', [DeliveryChannelController::class, 'index']);
    Route::get('settings/public', [PublicSettingsController::class, 'show']);
    Route::post('calculations', [CalculationController::class, 'store']);
});
