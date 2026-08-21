<?php

declare(strict_types=1);

namespace Tests\Unit\Dto;

use App\Dto\Calculation\CalculationInput;
use App\Enums\Platform;
use App\Exceptions\InvalidCalculationInputException;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет нормализацию входа расчёта и отказ от float/неполных данных Ozon.
 */
final class CalculationInputTest extends TestCase
{
    public function test_ozon_requires_dimensions_and_order_cost(): void
    {
        try {
            CalculationInput::fromArray([
                'platform' => 'ozon',
                'delivery_channel_code' => 'atc-standard-big',
                'physical_weight_grams' => '2500',
            ]);
            self::fail('Expected InvalidCalculationInputException');
        } catch (InvalidCalculationInputException $exception) {
            self::assertArrayHasKey('length_cm', $exception->errors);
            self::assertArrayHasKey('width_cm', $exception->errors);
            self::assertArrayHasKey('height_cm', $exception->errors);
            self::assertArrayHasKey('order_cost', $exception->errors);
            self::assertArrayHasKey('order_cost_currency', $exception->errors);
        }
    }

    public function test_yandex_allows_weight_only(): void
    {
        $input = CalculationInput::fromArray([
            'platform' => 'yandex_market',
            'delivery_channel_code' => 'super-express',
            'physical_weight_grams' => '550',
        ]);

        self::assertSame(Platform::YandexMarket, $input->platform);
        self::assertSame('550.00000000', $input->physicalWeightGrams);
        self::assertNull($input->lengthCm);
        self::assertNull($input->orderCost);
    }

    public function test_rejects_float_physical_weight(): void
    {
        try {
            CalculationInput::fromArray([
                'platform' => 'yandex_market',
                'delivery_channel_code' => 'express',
                'physical_weight_grams' => 550.5,
            ]);
            self::fail('Expected InvalidCalculationInputException');
        } catch (InvalidCalculationInputException $exception) {
            self::assertArrayHasKey('physical_weight_grams', $exception->errors);
        }
    }
}
