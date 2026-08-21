<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Dto\Calculation\PricingResult;
use App\Enums\Platform;
use App\Support\Decimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ответ POST /calculations. Деньги и веса строками.
 *
 * @property PricingResult $resource
 */
final class CalculationResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PricingResult $result */
        $result = $this->resource;
        $platform = Platform::from($result->platformCode);

        $payload = [
            'eligible' => $result->eligible,
            'platform' => [
                'code' => $platform->value,
                'name' => $platform->label(),
            ],
            'channel' => [
                'code' => $result->channelCode,
                'name' => $result->channelName,
            ],
            'currency' => $result->currency,
            'physical_weight_grams' => $result->physicalWeightGrams,
            'fixed_fee' => $this->money($result->fixedFee),
            'final_cost' => $result->finalCost,
            'errors' => $result->errors,
            'warnings' => $result->warnings,
        ];

        if ($platform === Platform::Ozon) {
            $payload['volumetric_weight_grams'] = $result->volumetricWeightGrams;
            $payload['chargeable_weight_grams'] = $result->chargeableWeightGrams;
            $payload['rate_per_gram'] = $this->money($result->ratePerGram);
            $payload['order_cost'] = $result->orderCostEntered;
            $payload['order_cost_currency'] = $result->orderCostCurrency;
            $payload['order_cost_cny'] = $result->orderCostCny;
        }

        if ($platform === Platform::YandexMarket) {
            $payload['billed_weight_grams'] = $result->billedWeightGrams;
            $payload['rate_per_kg'] = $this->money($result->ratePerKg);
            $payload['exchange_rate'] = $result->exchangeRate !== null
                ? Decimal::toApiString($result->exchangeRate, 6)
                : null;
            $payload['final_cost_cny'] = $result->finalCostCny;
        }

        return $payload;
    }

    private function money(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Decimal::trimTrailingZeros($value);
    }
}
