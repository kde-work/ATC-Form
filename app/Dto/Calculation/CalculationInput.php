<?php

declare(strict_types=1);

namespace App\Dto\Calculation;

use App\Enums\OrderCostCurrency;
use App\Enums\Platform;
use App\Exceptions\InvalidCalculationInputException;
use App\Support\Decimal;

/**
 * Нормализованный вход расчёта стоимости доставки.
 * Все денежные и весовые величины хранятся строками.
 */
final readonly class CalculationInput
{
    /**
     * @param non-empty-string $deliveryChannelCode
     * @param numeric-string $physicalWeightGrams
     * @param numeric-string|null $lengthCm
     * @param numeric-string|null $widthCm
     * @param numeric-string|null $heightCm
     * @param numeric-string|null $orderCost
     */
    public function __construct(
        public Platform $platform,
        public string $deliveryChannelCode,
        public string $physicalWeightGrams,
        public ?string $lengthCm,
        public ?string $widthCm,
        public ?string $heightCm,
        public ?string $orderCost,
        public ?OrderCostCurrency $orderCostCurrency,
    ) {
    }

    /**
     * Собирает DTO из недостоверного массива (request/JSON) с явной валидацией.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidCalculationInputException
     */
    public static function fromArray(array $data): self
    {
        /** @var array<string, list<string>> $errors */
        $errors = [];

        $platform = self::parsePlatform($data['platform'] ?? null, $errors);
        $channelCode = self::parseNonEmptyString($data['delivery_channel_code'] ?? null, 'delivery_channel_code', $errors);
        $physicalWeight = self::parsePositiveDecimal($data['physical_weight_grams'] ?? null, 'physical_weight_grams', $errors);

        $length = null;
        $width = null;
        $height = null;
        $orderCost = null;
        $orderCostCurrency = null;

        if ($platform === Platform::Ozon) {
            $length = self::parsePositiveDecimal($data['length_cm'] ?? null, 'length_cm', $errors);
            $width = self::parsePositiveDecimal($data['width_cm'] ?? null, 'width_cm', $errors);
            $height = self::parsePositiveDecimal($data['height_cm'] ?? null, 'height_cm', $errors);
            $orderCost = self::parsePositiveDecimal($data['order_cost'] ?? null, 'order_cost', $errors);
            $orderCostCurrency = self::parseCurrency($data['order_cost_currency'] ?? null, 'order_cost_currency', $errors, required: true);
        } else {
            if (array_key_exists('length_cm', $data) && $data['length_cm'] !== null && $data['length_cm'] !== '') {
                $length = self::parsePositiveDecimal($data['length_cm'], 'length_cm', $errors);
            }
            if (array_key_exists('width_cm', $data) && $data['width_cm'] !== null && $data['width_cm'] !== '') {
                $width = self::parsePositiveDecimal($data['width_cm'], 'width_cm', $errors);
            }
            if (array_key_exists('height_cm', $data) && $data['height_cm'] !== null && $data['height_cm'] !== '') {
                $height = self::parsePositiveDecimal($data['height_cm'], 'height_cm', $errors);
            }
            if (array_key_exists('order_cost', $data) && $data['order_cost'] !== null && $data['order_cost'] !== '') {
                $orderCost = self::parsePositiveDecimal($data['order_cost'], 'order_cost', $errors);
                $orderCostCurrency = self::parseCurrency($data['order_cost_currency'] ?? null, 'order_cost_currency', $errors, required: true);
            } elseif (array_key_exists('order_cost_currency', $data) && $data['order_cost_currency'] !== null && $data['order_cost_currency'] !== '') {
                $orderCostCurrency = self::parseCurrency($data['order_cost_currency'], 'order_cost_currency', $errors, required: true);
            }
        }

        if ($errors !== []) {
            throw new InvalidCalculationInputException($errors);
        }

        assert($platform instanceof Platform);
        assert(is_string($channelCode) && $channelCode !== '');
        assert(is_string($physicalWeight));

        return new self(
            platform: $platform,
            deliveryChannelCode: $channelCode,
            physicalWeightGrams: $physicalWeight,
            lengthCm: $length,
            widthCm: $width,
            heightCm: $height,
            orderCost: $orderCost,
            orderCostCurrency: $orderCostCurrency,
        );
    }

    /**
     * @param array<string, list<string>> $errors
     */
    private static function parsePlatform(mixed $value, array &$errors): ?Platform
    {
        if (! is_string($value) || $value === '') {
            $errors['platform'][] = 'The platform field is required.';

            return null;
        }

        $platform = Platform::tryFrom($value);
        if ($platform === null) {
            $errors['platform'][] = 'The selected platform is invalid.';

            return null;
        }

        return $platform;
    }

    /**
     * @param array<string, list<string>> $errors
     */
    private static function parseNonEmptyString(mixed $value, string $field, array &$errors): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            $errors[$field][] = "The {$field} field is required.";

            return null;
        }

        return trim($value);
    }

    /**
     * @param array<string, list<string>> $errors
     */
    private static function parsePositiveDecimal(mixed $value, string $field, array &$errors): ?string
    {
        if ($value === null || $value === '') {
            $errors[$field][] = "The {$field} field is required.";

            return null;
        }

        if (is_bool($value) || is_array($value) || is_object($value)) {
            $errors[$field][] = "The {$field} field must be a number.";

            return null;
        }

        if (is_int($value)) {
            $asString = (string) $value;
        } elseif (is_float($value)) {
            $errors[$field][] = "The {$field} field must be provided as a decimal string, not a float.";

            return null;
        } elseif (is_string($value)) {
            $asString = trim($value);
        } else {
            $errors[$field][] = "The {$field} field must be a number.";

            return null;
        }

        try {
            $normalized = Decimal::normalize($asString, 8);
        } catch (\InvalidArgumentException) {
            $errors[$field][] = "The {$field} field must be a valid decimal number.";

            return null;
        }

        if (Decimal::compare($normalized, '0', 8) <= 0) {
            $errors[$field][] = "The {$field} field must be greater than 0.";

            return null;
        }

        return $normalized;
    }

    /**
     * @param array<string, list<string>> $errors
     */
    private static function parseCurrency(mixed $value, string $field, array &$errors, bool $required): ?OrderCostCurrency
    {
        if ($value === null || $value === '') {
            if ($required) {
                $errors[$field][] = "The {$field} field is required.";
            }

            return null;
        }

        if (! is_string($value)) {
            $errors[$field][] = "The {$field} field is invalid.";

            return null;
        }

        $currency = OrderCostCurrency::tryFrom(strtoupper($value));
        if ($currency === null) {
            $errors[$field][] = "The selected {$field} is invalid.";

            return null;
        }

        return $currency;
    }
}
