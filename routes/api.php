<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Публичные и admin-маршруты появятся на этапах 5 и 7.
});
