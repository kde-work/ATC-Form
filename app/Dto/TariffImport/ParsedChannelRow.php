<?php

declare(strict_types=1);

namespace App\Dto\TariffImport;

use App\Enums\Platform;
use App\Enums\WeightCalculationType;

/**
 * Нормализованная строка тарифа после парсинга листа.
 */
final readonly class ParsedChannelRow
{
    /**
     * @param array<string, mixed>|null $rawData
     * @param numeric-string $fixedFee
     * @param numeric-string|null $perGramFee
     * @param numeric-string|null $perKgFee
     * @param numeric-string|null $volumetricDivisor
     * @param numeric-string|null $minWeightGrams
     * @param numeric-string|null $maxWeightGrams
     * @param numeric-string|null $maxLengthCm
     * @param numeric-string|null $maxSumDimensionsCm
     * @param numeric-string|null $minOrderCostRub
     * @param numeric-string|null $maxOrderCostRub
     * @param numeric-string|null $minOrderCostCny
     * @param numeric-string|null $maxOrderCostCny
     */
    public function __construct(
        public Platform $platform,
        public string $code,
        public string $name,
        public string $currency,
        public string $fixedFee,
        public ?string $perGramFee,
        public ?string $perKgFee,
        public ?int $billingIncrementGrams,
        public ?WeightCalculationType $chargeableWeightType,
        public ?string $volumetricDivisor,
        public ?string $minWeightGrams,
        public ?string $maxWeightGrams,
        public ?string $maxLengthCm,
        public ?string $maxSumDimensionsCm,
        public ?string $minOrderCostRub,
        public ?string $maxOrderCostRub,
        public ?string $minOrderCostCny,
        public ?string $maxOrderCostCny,
        public ?int $importSourceRow,
        public ?string $sheetName,
        public ?array $rawData = null,
        public bool $active = true,
    ) {
    }

    /**
     * Атрибуты для создания DeliveryChannel.
     *
     * @return array<string, mixed>
     */
    public function toChannelAttributes(): array
    {
        return [
            'platform' => $this->platform,
            'code' => $this->code,
            'name' => $this->name,
            'active' => $this->active,
            'currency' => $this->currency,
            'chargeable_weight_type' => $this->chargeableWeightType,
            'fixed_fee' => $this->fixedFee,
            'per_gram_fee' => $this->perGramFee,
            'per_kg_fee' => $this->perKgFee,
            'billing_increment_grams' => $this->billingIncrementGrams,
            'volumetric_divisor' => $this->volumetricDivisor,
            'min_weight_grams' => $this->minWeightGrams,
            'max_weight_grams' => $this->maxWeightGrams,
            'max_length_cm' => $this->maxLengthCm,
            'max_sum_dimensions_cm' => $this->maxSumDimensionsCm,
            'min_order_cost_rub' => $this->minOrderCostRub,
            'max_order_cost_rub' => $this->maxOrderCostRub,
            'min_order_cost_cny' => $this->minOrderCostCny,
            'max_order_cost_cny' => $this->maxOrderCostCny,
            'import_source_row' => $this->importSourceRow,
            'raw_data_json' => $this->rawData,
        ];
    }
}
