<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Calculation;

use App\Dto\Calculation\CalculationInput;
use App\Enums\Platform;
use App\Enums\WeightCalculationType;
use App\Models\DeliveryChannel;
use App\Services\Calculation\EligibilityValidationService;
use App\Services\Calculation\PricingService;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет формулы Yandex (550 g) и Ozon Big volumetric из ТЗ.
 */
final class PricingServiceTest extends TestCase
{
    private PricingService $pricing;

    private EligibilityValidationService $eligibility;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eligibility = new EligibilityValidationService();
        $this->pricing = new PricingService($this->eligibility);
    }

    public function test_yandex_super_express_550_grams(): void
    {
        $channel = new DeliveryChannel([
            'platform' => Platform::YandexMarket,
            'code' => 'super-express',
            'name' => 'Super Express',
            'active' => true,
            'currency' => 'RUB',
            'fixed_fee' => '193',
            'per_kg_fee' => '948',
            'billing_increment_grams' => 100,
        ]);

        $input = CalculationInput::fromArray([
            'platform' => 'yandex_market',
            'delivery_channel_code' => 'super-express',
            'physical_weight_grams' => '550',
        ]);

        $result = $this->pricing->calculate($channel, $input, '0.085');

        self::assertTrue($result->eligible);
        self::assertSame('550.000', $result->physicalWeightGrams);
        self::assertSame('600.000', $result->billedWeightGrams);
        self::assertSame('761.80', $result->finalCost);
        self::assertSame('64.7530', $result->finalCostCny);
        self::assertSame([], $result->errors);
    }

    public function test_yandex_express_550_grams(): void
    {
        $channel = new DeliveryChannel([
            'platform' => Platform::YandexMarket,
            'code' => 'express',
            'name' => 'Express',
            'active' => true,
            'currency' => 'RUB',
            'fixed_fee' => '198',
            'per_kg_fee' => '787',
            'billing_increment_grams' => 100,
        ]);

        $input = CalculationInput::fromArray([
            'platform' => 'yandex_market',
            'delivery_channel_code' => 'express',
            'physical_weight_grams' => '550',
        ]);

        $result = $this->pricing->calculate($channel, $input, '0.085');

        self::assertTrue($result->eligible);
        self::assertSame('600.000', $result->billedWeightGrams);
        self::assertSame('670.20', $result->finalCost);
    }

    public function test_ozon_big_uses_volumetric_weight_from_tariff_divisor(): void
    {
        $channel = new DeliveryChannel([
            'platform' => Platform::Ozon,
            'code' => 'atc-standard-big',
            'name' => 'ATC Standard Big',
            'active' => true,
            'currency' => 'CNY',
            'chargeable_weight_type' => WeightCalculationType::MaxPhysicalOrVolumetric,
            'fixed_fee' => '40.44',
            'per_gram_fee' => '0.0281',
            'volumetric_divisor' => '12000',
            'min_weight_grams' => '501',
            'max_weight_grams' => '30000',
            'max_length_cm' => '120',
            'max_sum_dimensions_cm' => '300',
            'min_order_cost_cny' => '0.01',
            'max_order_cost_cny' => '5000',
        ]);

        $input = CalculationInput::fromArray([
            'platform' => 'ozon',
            'delivery_channel_code' => 'atc-standard-big',
            'physical_weight_grams' => '2500',
            'length_cm' => '100',
            'width_cm' => '40',
            'height_cm' => '30',
            'order_cost' => '500',
            'order_cost_currency' => 'CNY',
        ]);

        $result = $this->pricing->calculate($channel, $input, '0.085');

        self::assertTrue($result->eligible);
        self::assertSame('2500.000', $result->physicalWeightGrams);
        self::assertSame('10000.000', $result->volumetricWeightGrams);
        self::assertSame('10000.000', $result->chargeableWeightGrams);
        self::assertSame('321.44', $result->finalCost);
        self::assertSame([], $result->errors);
    }

    public function test_not_eligible_returns_null_cost_and_all_errors(): void
    {
        $channel = new DeliveryChannel([
            'platform' => Platform::Ozon,
            'code' => 'atc-express-extra-small',
            'name' => 'ATC Express Extra Small',
            'active' => true,
            'currency' => 'CNY',
            'chargeable_weight_type' => WeightCalculationType::Physical,
            'fixed_fee' => '3.37',
            'per_gram_fee' => '0.0505',
            'min_weight_grams' => '1',
            'max_weight_grams' => '2000',
            'max_length_cm' => '60',
            'max_sum_dimensions_cm' => '150',
            'min_order_cost_cny' => '135.01',
            'max_order_cost_cny' => '635.00',
        ]);

        $input = CalculationInput::fromArray([
            'platform' => 'ozon',
            'delivery_channel_code' => 'atc-express-extra-small',
            'physical_weight_grams' => '2500',
            'length_cm' => '80',
            'width_cm' => '40',
            'height_cm' => '40',
            'order_cost' => '50',
            'order_cost_currency' => 'CNY',
        ]);

        $result = $this->pricing->calculate($channel, $input, '0.085');

        self::assertFalse($result->eligible);
        self::assertNull($result->finalCost);
        self::assertCount(4, $result->errors);
        self::assertContains('Physical weight exceeds the maximum allowed weight of 2,000 g.', $result->errors);
        self::assertContains('Parcel length exceeds the maximum allowed length of 60 cm.', $result->errors);
        self::assertContains('Sum of dimensions exceeds the maximum allowed value of 150 cm.', $result->errors);
        self::assertContains('Order cost is outside the allowed range: 135.01-635.00 CNY.', $result->errors);
    }

    public function test_yandex_without_limits_does_not_invent_errors(): void
    {
        $channel = new DeliveryChannel([
            'platform' => Platform::YandexMarket,
            'code' => 'super-express',
            'name' => 'Super Express',
            'active' => true,
            'currency' => 'RUB',
            'fixed_fee' => '193',
            'per_kg_fee' => '948',
            'billing_increment_grams' => 100,
            'min_weight_grams' => null,
            'max_weight_grams' => null,
            'max_length_cm' => null,
            'max_sum_dimensions_cm' => null,
        ]);

        $input = CalculationInput::fromArray([
            'platform' => 'yandex_market',
            'delivery_channel_code' => 'super-express',
            'physical_weight_grams' => '50000',
            'length_cm' => '300',
            'width_cm' => '300',
            'height_cm' => '300',
        ]);

        $eligibility = $this->eligibility->validate($channel, $input, '0.085');

        self::assertTrue($eligibility->eligible);
        self::assertSame([], $eligibility->errors);
    }
}
