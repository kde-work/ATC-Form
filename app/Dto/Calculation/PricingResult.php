<?php

declare(strict_types=1);

namespace App\Dto\Calculation;

/**
 * Результат расчёта стоимости. Деньги и веса: строки; при not eligible final_cost = null.
 */
final readonly class PricingResult
{
    /**
     * @param list<string> $errors
     * @param list<string> $warnings
     * @param numeric-string|null $physicalWeightGrams
     * @param numeric-string|null $volumetricWeightGrams
     * @param numeric-string|null $chargeableWeightGrams
     * @param numeric-string|null $billedWeightGrams
     * @param numeric-string|null $fixedFee
     * @param numeric-string|null $ratePerGram
     * @param numeric-string|null $ratePerKg
     * @param numeric-string|null $finalCost
     * @param numeric-string|null $finalCostCny
     * @param numeric-string|null $exchangeRate
     * @param numeric-string|null $orderCostEntered
     * @param numeric-string|null $orderCostCny
     */
    public function __construct(
        public bool $eligible,
        public string $platformCode,
        public string $channelCode,
        public string $channelName,
        public string $currency,
        public ?string $physicalWeightGrams,
        public ?string $volumetricWeightGrams,
        public ?string $chargeableWeightGrams,
        public ?string $billedWeightGrams,
        public ?string $fixedFee,
        public ?string $ratePerGram,
        public ?string $ratePerKg,
        public ?string $finalCost,
        public ?string $finalCostCny,
        public ?string $exchangeRate,
        public ?string $orderCostEntered,
        public ?string $orderCostCurrency,
        public ?string $orderCostCny,
        public array $errors,
        public array $warnings,
    ) {
    }
}
