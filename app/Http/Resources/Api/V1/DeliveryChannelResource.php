<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\DeliveryChannel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Публичный канал доставки из активной ревизии.
 *
 * @mixin DeliveryChannel
 */
final class DeliveryChannelResource extends JsonResource
{
    /**
     * @return array{
     *     code: string,
     *     name: string,
     *     platform: string,
     *     currency: string
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var DeliveryChannel $channel */
        $channel = $this->resource;

        return [
            'code' => $channel->code,
            'name' => $channel->name,
            'platform' => $channel->platform->value,
            'currency' => $channel->currency,
        ];
    }
}
