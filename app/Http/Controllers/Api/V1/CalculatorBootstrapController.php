<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Calculator\CalculatorFormDataCache;
use Illuminate\Http\JsonResponse;

/**
 * Презагрузка всех справочников публичной формы калькулятора.
 */
final class CalculatorBootstrapController extends Controller
{
    public function __construct(
        private readonly CalculatorFormDataCache $formDataCache,
    ) {
    }

    public function show(): JsonResponse
    {
        return response()->json($this->formDataCache->bootstrap());
    }
}
