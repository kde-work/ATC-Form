<?php

declare(strict_types=1);

namespace App\Services\Calculation;

use App\Dto\Calculation\CalculationInput;
use App\Dto\Calculation\EligibilityResult;
use App\Enums\OrderCostCurrency;
use App\Enums\Platform;
use App\Models\DeliveryChannel;
use App\Support\Decimal;
use InvalidArgumentException;

/**
 * Проверяет лимиты тарифа. Возвращает все нарушения сразу.
 * Сообщения на английском для API/UI.
 */
final class EligibilityValidationService
{
    /**
     * @param numeric-string $rubToCnyRate активный курс для конвертации order cost RUB → CNY
     */
    public function validate(DeliveryChannel $channel, CalculationInput $input, string $rubToCnyRate): EligibilityResult
    {
        if ($channel->platform !== $input->platform) {
            throw new InvalidArgumentException('Delivery channel platform does not match calculation input platform.');
        }

        $errors = [];

        $this->validateWeightLimits($channel, $input, $errors);

        if ($input->platform === Platform::Ozon) {
            $this->validateOzonDimensions($channel, $input, $errors);
            $this->validateOrderCost($channel, $input, $rubToCnyRate, $errors);
        } else {
            // Yandex: лимиты только если заданы в тарифе; фиктивные не подставляем.
            $this->validateOptionalDimensions($channel, $input, $errors);
            if ($input->orderCost !== null && $input->orderCostCurrency !== null) {
                $this->validateOrderCost($channel, $input, $rubToCnyRate, $errors);
            }
        }

        return EligibilityResult::fromErrors($errors);
    }

    /**
     * @param list<string> $errors
     */
    private function validateWeightLimits(DeliveryChannel $channel, CalculationInput $input, array &$errors): void
    {
        $weight = $input->physicalWeightGrams;

        if ($channel->min_weight_grams !== null
            && Decimal::compare($weight, (string) $channel->min_weight_grams, 3) < 0
        ) {
            $errors[] = sprintf(
                'Physical weight is below the minimum allowed weight of %s g.',
                Decimal::formatDisplay((string) $channel->min_weight_grams, 3)
            );
        }

        if ($channel->max_weight_grams !== null
            && Decimal::compare($weight, (string) $channel->max_weight_grams, 3) > 0
        ) {
            $errors[] = sprintf(
                'Physical weight exceeds the maximum allowed weight of %s g.',
                Decimal::formatDisplay((string) $channel->max_weight_grams, 3)
            );
        }
    }

    /**
     * @param list<string> $errors
     */
    private function validateOzonDimensions(DeliveryChannel $channel, CalculationInput $input, array &$errors): void
    {
        assert($input->lengthCm !== null && $input->widthCm !== null && $input->heightCm !== null);

        $this->validateDimensionLimits(
            $channel,
            $input->lengthCm,
            $input->widthCm,
            $input->heightCm,
            $errors
        );
    }

    /**
     * @param list<string> $errors
     */
    private function validateOptionalDimensions(DeliveryChannel $channel, CalculationInput $input, array &$errors): void
    {
        if ($input->lengthCm === null || $input->widthCm === null || $input->heightCm === null) {
            return;
        }

        $this->validateDimensionLimits(
            $channel,
            $input->lengthCm,
            $input->widthCm,
            $input->heightCm,
            $errors
        );
    }

    /**
     * @param numeric-string $lengthCm
     * @param numeric-string $widthCm
     * @param numeric-string $heightCm
     * @param list<string> $errors
     */
    private function validateDimensionLimits(
        DeliveryChannel $channel,
        string $lengthCm,
        string $widthCm,
        string $heightCm,
        array &$errors,
    ): void {
        if ($channel->max_length_cm !== null) {
            $maxSide = Decimal::max(Decimal::max($lengthCm, $widthCm, 3), $heightCm, 3);
            if (Decimal::compare($maxSide, (string) $channel->max_length_cm, 3) > 0) {
                $errors[] = sprintf(
                    'Parcel length exceeds the maximum allowed length of %s cm.',
                    Decimal::formatDisplay((string) $channel->max_length_cm, 3)
                );
            }
        }

        if ($channel->max_sum_dimensions_cm !== null) {
            $sum = Decimal::add(Decimal::add($lengthCm, $widthCm, 3), $heightCm, 3);
            if (Decimal::compare($sum, (string) $channel->max_sum_dimensions_cm, 3) > 0) {
                $errors[] = sprintf(
                    'Sum of dimensions exceeds the maximum allowed value of %s cm.',
                    Decimal::formatDisplay((string) $channel->max_sum_dimensions_cm, 3)
                );
            }
        }
    }

    /**
     * @param numeric-string $rubToCnyRate
     * @param list<string> $errors
     */
    private function validateOrderCost(
        DeliveryChannel $channel,
        CalculationInput $input,
        string $rubToCnyRate,
        array &$errors,
    ): void {
        if ($input->orderCost === null || $input->orderCostCurrency === null) {
            return;
        }

        if ($input->orderCostCurrency === OrderCostCurrency::Cny) {
            $this->assertOrderCostInRange(
                $input->orderCost,
                $channel->min_order_cost_cny !== null ? (string) $channel->min_order_cost_cny : null,
                $channel->max_order_cost_cny !== null ? (string) $channel->max_order_cost_cny : null,
                'CNY',
                $errors
            );

            return;
        }

        // RUB: сначала проверяем RUB-диапазон, если задан; иначе конвертируем в CNY и сверяем CNY-лимиты.
        if ($channel->min_order_cost_rub !== null || $channel->max_order_cost_rub !== null) {
            $this->assertOrderCostInRange(
                $input->orderCost,
                $channel->min_order_cost_rub !== null ? (string) $channel->min_order_cost_rub : null,
                $channel->max_order_cost_rub !== null ? (string) $channel->max_order_cost_rub : null,
                'RUB',
                $errors
            );

            return;
        }

        if ($channel->min_order_cost_cny !== null || $channel->max_order_cost_cny !== null) {
            $orderCostCny = Decimal::mul($input->orderCost, $rubToCnyRate, 8);
            $this->assertOrderCostInRange(
                $orderCostCny,
                $channel->min_order_cost_cny !== null ? (string) $channel->min_order_cost_cny : null,
                $channel->max_order_cost_cny !== null ? (string) $channel->max_order_cost_cny : null,
                'CNY',
                $errors
            );
        }
    }

    /**
     * @param numeric-string $value
     * @param numeric-string|null $min
     * @param numeric-string|null $max
     * @param list<string> $errors
     */
    private function assertOrderCostInRange(
        string $value,
        ?string $min,
        ?string $max,
        string $currency,
        array &$errors,
    ): void {
        if ($min === null && $max === null) {
            return;
        }

        $belowMin = $min !== null && Decimal::compare($value, $min, 4) < 0;
        $aboveMax = $max !== null && Decimal::compare($value, $max, 4) > 0;

        if (! $belowMin && ! $aboveMax) {
            return;
        }

        if ($min !== null && $max !== null) {
            $errors[] = sprintf(
                'Order cost is outside the allowed range: %s-%s %s.',
                Decimal::formatMoneyDisplay($min),
                Decimal::formatMoneyDisplay($max),
                $currency
            );

            return;
        }

        if ($belowMin) {
            $errors[] = sprintf(
                'Order cost is below the minimum allowed value of %s %s.',
                Decimal::formatMoneyDisplay((string) $min),
                $currency
            );
        }

        if ($aboveMax) {
            $errors[] = sprintf(
                'Order cost exceeds the maximum allowed value of %s %s.',
                Decimal::formatMoneyDisplay((string) $max),
                $currency
            );
        }
    }
}
