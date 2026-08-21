<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Calculator\CalculatorFormDataCache;
use Illuminate\Http\JsonResponse;

/**
 * Публичные настройки (курс). Только чтение, из кэша справочников формы.
 */
final class PublicSettingsController extends Controller
{
    public function __construct(
        private readonly CalculatorFormDataCache $formDataCache,
    ) {
    }

    public function show(): JsonResponse
    {
        return response()
            ->json($this->formDataCache->publicSettings())
            ->header('Cache-Control', 'private, max-age=300');
    }
}
