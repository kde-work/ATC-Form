<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\AppSetting;
use App\Support\Decimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Публичный курс RUB → CNY.
 *
 * @mixin AppSetting
 */
final class PublicSettingsResource extends JsonResource
{
    /**
     * @return array{rub_to_cny_rate: string, updated_at: string}
     */
    public function toArray(Request $request): array
    {
        /** @var AppSetting $setting */
        $setting = $this->resource;

        return [
            'rub_to_cny_rate' => Decimal::toApiString($setting->value, 6),
            'updated_at' => $setting->updated_at?->toIso8601String() ?? now()->toIso8601String(),
        ];
    }
}
