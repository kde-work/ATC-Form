<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\DeliveryChannel;
use App\Support\Decimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Полный тариф для admin read-only таблицы.
 *
 * @mixin DeliveryChannel
 */
final class AdminTariffResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var DeliveryChannel $channel */
        $channel = $this->resource;

        return [
            'id' => $channel->id,
            'revision_id' => $channel->tariff_revision_id,
            'platform' => $channel->platform->value,
            'code' => $channel->code,
            'name' => $channel->name,
            'active' => $channel->active,
            'currency' => $channel->currency,
            'chargeable_weight_type' => $channel->chargeable_weight_type?->value,
            'fixed_fee' => Decimal::toApiString($channel->fixed_fee, 6),
            'per_gram_fee' => $this->nullableDecimal($channel->per_gram_fee, 8),
            'per_kg_fee' => $this->nullableDecimal($channel->per_kg_fee, 6),
            'billing_increment_grams' => $channel->billing_increment_grams,
            'volumetric_divisor' => $this->nullableDecimal($channel->volumetric_divisor, 4),
            'min_weight_grams' => $this->nullableDecimal($channel->min_weight_grams, 3),
            'max_weight_grams' => $this->nullableDecimal($channel->max_weight_grams, 3),
            'max_length_cm' => $this->nullableDecimal($channel->max_length_cm, 3),
            'max_sum_dimensions_cm' => $this->nullableDecimal($channel->max_sum_dimensions_cm, 3),
            'min_order_cost_rub' => $this->nullableDecimal($channel->min_order_cost_rub, 4),
            'max_order_cost_rub' => $this->nullableDecimal($channel->max_order_cost_rub, 4),
            'min_order_cost_cny' => $this->nullableDecimal($channel->min_order_cost_cny, 4),
            'max_order_cost_cny' => $this->nullableDecimal($channel->max_order_cost_cny, 4),
            'import_source_row' => $channel->import_source_row,
        ];
    }

    private function nullableDecimal(?string $value, int $scale): ?string
    {
        if ($value === null) {
            return null;
        }

        return Decimal::toApiString($value, $scale);
    }
}
