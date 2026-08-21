<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\AppSetting;
use App\Models\User;
use App\Support\Decimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin settings: курс и аудит последнего изменения.
 *
 * @mixin AppSetting
 */
final class AdminSettingsResource extends JsonResource
{
    /**
     * @return array{
     *     rub_to_cny_rate: string,
     *     updated_at: string,
     *     updated_by: array{id: int, name: string, email: string}|null
     * }
     */
    public function toArray(Request $request): array
    {
        /** @var AppSetting $setting */
        $setting = $this->resource;

        /** @var User|null $updatedBy */
        $updatedBy = $setting->relationLoaded('updatedBy')
            ? $setting->updatedBy
            : $setting->updatedBy()->first();

        return [
            'rub_to_cny_rate' => Decimal::toApiString($setting->value, 6),
            'updated_at' => $setting->updated_at?->toIso8601String() ?? now()->toIso8601String(),
            'updated_by' => $updatedBy === null ? null : [
                'id' => $updatedBy->id,
                'name' => $updatedBy->name,
                'email' => $updatedBy->email,
            ],
        ];
    }
}
