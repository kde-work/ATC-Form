<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CalculateRequest;
use App\Http\Resources\Api\V1\CalculationResource;
use App\Services\Calculation\PublicCalculationService;

/**
 * Публичный расчёт стоимости доставки.
 */
final class CalculationController extends Controller
{
    public function __construct(
        private readonly PublicCalculationService $publicCalculationService,
    ) {
    }

    public function store(CalculateRequest $request): CalculationResource
    {
        $result = $this->publicCalculationService->calculate($request->calculationInput());

        return new CalculationResource($result);
    }
}
