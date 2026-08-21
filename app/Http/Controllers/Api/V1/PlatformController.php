<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Calculator\CalculatorFormDataCache;
use Illuminate\Http\JsonResponse;

/**
 * Публичный список платформ калькулятора.
 */
final class PlatformController extends Controller
{
    public function __construct(
        private readonly CalculatorFormDataCache $formDataCache,
    ) {
    }

    public function index(): JsonResponse
    {
        return response()
            ->json($this->formDataCache->platforms())
            ->header('Cache-Control', 'private, max-age=300');
    }
}
