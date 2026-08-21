<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DeliveryChannelIndexRequest;
use App\Http\Resources\Api\V1\DeliveryChannelResource;
use App\Services\ActiveTariffQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Публичный список каналов активной ревизии.
 */
final class DeliveryChannelController extends Controller
{
    public function __construct(
        private readonly ActiveTariffQuery $activeTariffQuery,
    ) {
    }

    public function index(DeliveryChannelIndexRequest $request): AnonymousResourceCollection
    {
        $channels = $this->activeTariffQuery->activeChannels($request->platform());

        return DeliveryChannelResource::collection($channels);
    }
}
