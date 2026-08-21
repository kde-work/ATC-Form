<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeliveryChannelIndexRequest;
use App\Services\Calculator\CalculatorFormDataCache;
use Illuminate\Http\JsonResponse;

/**
 * Публичный список каналов активной ревизии (из кэша справочников формы).
 */
final class DeliveryChannelController extends Controller
{
    public function __construct(
        private readonly CalculatorFormDataCache $formDataCache,
    ) {
    }

    public function index(DeliveryChannelIndexRequest $request): JsonResponse
    {
        return response()->json(
            $this->formDataCache->deliveryChannels($request->platform()),
        );
    }
}
