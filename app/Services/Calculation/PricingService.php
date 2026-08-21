<?php

declare(strict_types=1);

namespace App\Services\Calculation;

use App\Dto\Calculation\CalculationInput;
use App\Dto\Calculation\EligibilityResult;
use App\Dto\Calculation\PricingResult;
use App\Enums\OrderCostCurrency;
use App\Enums\Platform;
use App\Enums\WeightCalculationType;
use App\Models\DeliveryChannel;
use App\Support\Decimal;
use InvalidArgumentException;
use RuntimeException;

/**
 * Расчёт стоимости по тарифу канала. Ставки и список каналов не зашиты.
 */
final class PricingService
{
    public function __construct(
        private readonly EligibilityValidationService $eligibilityValidationService,
    ) {
    }

    /**
     * @param numeric-string $rubToCnyRate
     */
    public function calculate(DeliveryChannel $channel, CalculationInput $input, string $rubToCnyRate): PricingResult
    {
        if ($channel->platform !== $input->platform) {
            throw new InvalidArgumentException('Delivery channel platform does not match calculation input platform.');
        }

        if ($channel->code !== $input->deliveryChannelCode) {
            throw new InvalidArgumentException('Delivery channel code does not match calculation input.');
        }

        $rate = Decimal::normalize($rubToCnyRate, 8);
        if (Decimal::compare($rate, '0', 8) <= 0) {
            throw new InvalidArgumentException('Exchange rate must be greater than 0.');
        }

        $eligibility = $this->eligibilityValidationService->validate($channel, $input, $rate);

        return match ($input->platform) {
            Platform::Ozon => $this->calculateOzon($channel, $input, $rate, $eligibility),
            Platform::YandexMarket => $this->calculateYandex($channel, $input, $rate, $eligibility),
        };
    }

    /**
     * @param numeric-string $rubToCnyRate
     */
    private function calculateOzon(
        DeliveryChannel $channel,
        CalculationInput $input,
        string $rubToCnyRate,
        EligibilityResult $eligibility,
    ): PricingResult {
        assert($input->lengthCm !== null && $input->widthCm !== null && $input->heightCm !== null);
        assert($input->orderCost !== null && $input->orderCostCurrency !== null);

        $physical = Decimal::normalize($input->physicalWeightGrams, 8);
        $volumetric = null;
        $chargeable = $physical;

        $weightType = $channel->chargeable_weight_type ?? WeightCalculationType::Physical;

        if ($weightType === WeightCalculationType::MaxPhysicalOrVolumetric) {
            $divisor = $channel->volumetric_divisor;
            if ($divisor === null || Decimal::compare((string) $divisor, '0', 4) <= 0) {
                throw new RuntimeException('Volumetric divisor is required for max_physical_or_volumetric tariffs.');
            }

            // volumetric = (L * W * H / divisor) * 1000
            $volume = Decimal::mul(Decimal::mul($input->lengthCm, $input->widthCm, 8), $input->heightCm, 8);
            $volumetric = Decimal::mul(Decimal::div($volume, (string) $divisor, 8), '1000', 8);
            $chargeable = Decimal::max($physical, $volumetric, 8);
        }

        // Без increment в тарифе платный вес Ozon не округляем.
        if ($channel->billing_increment_grams !== null && $channel->billing_increment_grams > 0) {
            $chargeable = Decimal::ceilToIncrement($chargeable, $channel->billing_increment_grams, 8);
        }

        $fixedFee = Decimal::normalize((string) $channel->fixed_fee, 8);
        $perGram = $channel->per_gram_fee !== null
            ? Decimal::normalize((string) $channel->per_gram_fee, 8)
            : '0';

        $finalCost = null;
        if ($eligibility->eligible) {
            $finalCost = Decimal::add($fixedFee, Decimal::mul($perGram, $chargeable, 8), 8);
            $finalCost = Decimal::toApiString($finalCost, 2);
        }

        $orderCostCny = $this->orderCostToCny($input->orderCost, $input->orderCostCurrency, $rubToCnyRate);

        return new PricingResult(
            eligible: $eligibility->eligible,
            platformCode: Platform::Ozon->value,
            channelCode: $channel->code,
            channelName: $channel->name,
            currency: $channel->currency,
            physicalWeightGrams: Decimal::toApiString($physical, 3),
            volumetricWeightGrams: $volumetric !== null ? Decimal::toApiString($volumetric, 3) : null,
            chargeableWeightGrams: Decimal::toApiString($chargeable, 3),
            billedWeightGrams: null,
            fixedFee: Decimal::toApiString($fixedFee, 6),
            ratePerGram: Decimal::toApiString($perGram, 8),
            ratePerKg: null,
            finalCost: $finalCost,
            finalCostCny: $finalCost,
            exchangeRate: null,
            orderCostEntered: Decimal::toApiString($input->orderCost, 4),
            orderCostCurrency: $input->orderCostCurrency->value,
            orderCostCny: Decimal::toApiString($orderCostCny, 4),
            errors: $eligibility->errors,
            warnings: $eligibility->warnings,
        );
    }

    /**
     * @param numeric-string $rubToCnyRate
     */
    private function calculateYandex(
        DeliveryChannel $channel,
        CalculationInput $input,
        string $rubToCnyRate,
        EligibilityResult $eligibility,
    ): PricingResult {
        $increment = $channel->billing_increment_grams;
        if ($increment === null || $increment <= 0) {
            throw new RuntimeException('Billing increment is required for Yandex Market tariffs.');
        }

        if ($channel->per_kg_fee === null) {
            throw new RuntimeException('Per kg fee is required for Yandex Market tariffs.');
        }

        $physical = Decimal::normalize($input->physicalWeightGrams, 8);
        $billed = Decimal::ceilToIncrement($physical, $increment, 8);
        $fixedFee = Decimal::normalize((string) $channel->fixed_fee, 8);
        $perKg = Decimal::normalize((string) $channel->per_kg_fee, 8);

        $finalCostRub = null;
        $finalCostCny = null;
        if ($eligibility->eligible) {
            $weightKg = Decimal::div($billed, '1000', 8);
            $finalCostRub = Decimal::add($fixedFee, Decimal::mul($perKg, $weightKg, 8), 8);
            $finalCostRub = Decimal::toApiString($finalCostRub, 2);
            $finalCostCny = Decimal::toApiString(Decimal::mul($finalCostRub, $rubToCnyRate, 8), 4);
        }

        $orderCostEntered = $input->orderCost !== null
            ? Decimal::toApiString($input->orderCost, 4)
            : null;
        $orderCostCny = null;
        if ($input->orderCost !== null && $input->orderCostCurrency !== null) {
            $orderCostCny = Decimal::toApiString(
                $this->orderCostToCny($input->orderCost, $input->orderCostCurrency, $rubToCnyRate),
                4
            );
        }

        return new PricingResult(
            eligible: $eligibility->eligible,
            platformCode: Platform::YandexMarket->value,
            channelCode: $channel->code,
            channelName: $channel->name,
            currency: $channel->currency,
            physicalWeightGrams: Decimal::toApiString($physical, 3),
            volumetricWeightGrams: null,
            chargeableWeightGrams: null,
            billedWeightGrams: Decimal::toApiString($billed, 3),
            fixedFee: Decimal::toApiString($fixedFee, 6),
            ratePerGram: null,
            ratePerKg: Decimal::toApiString($perKg, 6),
            finalCost: $finalCostRub,
            finalCostCny: $finalCostCny,
            exchangeRate: Decimal::toApiString($rubToCnyRate, 6),
            orderCostEntered: $orderCostEntered,
            orderCostCurrency: $input->orderCostCurrency?->value,
            orderCostCny: $orderCostCny,
            errors: $eligibility->errors,
            warnings: $eligibility->warnings,
        );
    }

    /**
     * @param numeric-string $orderCost
     * @param numeric-string $rubToCnyRate
     * @return numeric-string
     */
    private function orderCostToCny(string $orderCost, OrderCostCurrency $currency, string $rubToCnyRate): string
    {
        if ($currency === OrderCostCurrency::Cny) {
            return Decimal::normalize($orderCost, 8);
        }

        return Decimal::mul($orderCost, $rubToCnyRate, 8);
    }
}
