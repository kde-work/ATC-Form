<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PublicSettingsResource;
use App\Services\ActiveTariffQuery;

/**
 * Публичные настройки (курс). Только чтение.
 */
final class PublicSettingsController extends Controller
{
    public function __construct(
        private readonly ActiveTariffQuery $activeTariffQuery,
    ) {
    }

    public function show(): PublicSettingsResource
    {
        return new PublicSettingsResource($this->activeTariffQuery->exchangeRateSetting());
    }
}
