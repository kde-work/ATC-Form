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
 * Покрытие раздела 13 ТЗ: Ozon Extra Small/Big, лимиты, Yandex округление и формулы.
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

    public function test_ozon_extra_small_uses_physical_weight(): void
    {
        $channel = $this->ozonExtraSmallChannel();

        // Габариты дали бы большой volumetric у Big, Extra Small берёт только physical.
        $input = CalculationInput::fromArray([
            'platform' => 'ozon',
            'delivery_channel_code' => 'atc-express-extra-small',
            'physical_weight_grams' => '1000',
            'length_cm' => '50',
            'width_cm' => '40',
            'height_cm' => '30',
            'order_cost' => '200',
            'order_cost_currency' => 'CNY',
        ]);

        $result = $this->pricing->calculate($channel, $input, '0.085');

        self::assertTrue($result->eligible);
        self::assertNull($result->volumetricWeightGrams);
        self::assertSame('1000.000', $result->physicalWeightGrams);
        self::assertSame('1000.000', $result->chargeableWeightGrams);
        // 3.37 + 0.0505 * 1000 = 53.87
        self::assertSame('53.87', $result->finalCost);
        self::assertSame([], $result->errors);
    }

    public function test_ozon_big_uses_max_of_physical_and_volumetric(): void
    {
        $channel = $this->ozonBigChannel();

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

    public function test_ozon_big_uses_physical_when_greater_than_volumetric(): void
    {
        $channel = $this->ozonBigChannel();

        // 20*20*20/12000*1000 = 666.666...; physical 8000 > volumetric.
        $input = CalculationInput::fromArray([
            'platform' => 'ozon',
            'delivery_channel_code' => 'atc-standard-big',
            'physical_weight_grams' => '8000',
            'length_cm' => '20',
            'width_cm' => '20',
            'height_cm' => '20',
            'order_cost' => '500',
            'order_cost_currency' => 'CNY',
        ]);

        $result = $this->pricing->calculate($channel, $input, '0.085');

        self::assertTrue($result->eligible);
        self::assertSame('8000.000', $result->physicalWeightGrams);
        self::assertSame('8000.000', $result->chargeableWeightGrams);
        // 40.44 + 0.0281 * 8000 = 265.24
        self::assertSame('265.24', $result->finalCost);
    }

    public function test_ozon_rejects_max_length(): void
    {
        $channel = $this->ozonExtraSmallChannel();

        $input = CalculationInput::fromArray([
            'platform' => 'ozon',
            'delivery_channel_code' => 'atc-express-extra-small',
            'physical_weight_grams' => '500',
            'length_cm' => '61',
            'width_cm' => '20',
            'height_cm' => '20',
            'order_cost' => '200',
            'order_cost_currency' => 'CNY',
        ]);

        $result = $this->pricing->calculate($channel, $input, '0.085');

        self::assertFalse($result->eligible);
        self::assertNull($result->finalCost);
        self::assertSame(
            ['Parcel length exceeds the maximum allowed length of 60 cm.'],
            $result->errors,
        );
    }

    public function test_ozon_rejects_sum_of_dimensions(): void
    {
        $channel = $this->ozonExtraSmallChannel();

        $input = CalculationInput::fromArray([
            'platform' => 'ozon',
            'delivery_channel_code' => 'atc-express-extra-small',
            'physical_weight_grams' => '500',
            'length_cm' => '50',
            'width_cm' => '50',
            'height_cm' => '51',
            'order_cost' => '200',
            'order_cost_currency' => 'CNY',
        ]);

        $result = $this->pricing->calculate($channel, $input, '0.085');

        self::assertFalse($result->eligible);
        self::assertNull($result->finalCost);
        self::assertSame(
            ['Sum of dimensions exceeds the maximum allowed value of 150 cm.'],
            $result->errors,
        );
    }

    public function test_ozon_rejects_min_and_max_physical_weight(): void
    {
        $channel = $this->ozonBigChannel();

        $tooLight = $this->pricing->calculate(
            $channel,
            CalculationInput::fromArray([
                'platform' => 'ozon',
                'delivery_channel_code' => 'atc-standard-big',
                'physical_weight_grams' => '500',
                'length_cm' => '40',
                'width_cm' => '40',
                'height_cm' => '40',
                'order_cost' => '500',
                'order_cost_currency' => 'CNY',
            ]),
            '0.085',
        );
        self::assertFalse($tooLight->eligible);
        self::assertContains(
            'Physical weight is below the minimum allowed weight of 501 g.',
            $tooLight->errors,
        );

        $tooHeavy = $this->pricing->calculate(
            $channel,
            CalculationInput::fromArray([
                'platform' => 'ozon',
                'delivery_channel_code' => 'atc-standard-big',
                'physical_weight_grams' => '30001',
                'length_cm' => '40',
                'width_cm' => '40',
                'height_cm' => '40',
                'order_cost' => '500',
                'order_cost_currency' => 'CNY',
            ]),
            '0.085',
        );
        self::assertFalse($tooHeavy->eligible);
        self::assertContains(
            'Physical weight exceeds the maximum allowed weight of 30,000 g.',
            $tooHeavy->errors,
        );
    }

    public function test_ozon_order_cost_cny_limits(): void
    {
        $channel = $this->ozonExtraSmallChannel();

        $below = $this->pricing->calculate(
            $channel,
            CalculationInput::fromArray([
                'platform' => 'ozon',
                'delivery_channel_code' => 'atc-express-extra-small',
                'physical_weight_grams' => '500',
                'length_cm' => '30',
                'width_cm' => '20',
                'height_cm' => '20',
                'order_cost' => '135',
                'order_cost_currency' => 'CNY',
            ]),
            '0.085',
        );
        self::assertFalse($below->eligible);
        self::assertSame(
            ['Order cost is outside the allowed range: 135.01-635.00 CNY.'],
            $below->errors,
        );

        $ok = $this->pricing->calculate(
            $channel,
            CalculationInput::fromArray([
                'platform' => 'ozon',
                'delivery_channel_code' => 'atc-express-extra-small',
                'physical_weight_grams' => '500',
                'length_cm' => '30',
                'width_cm' => '20',
                'height_cm' => '20',
                'order_cost' => '135.01',
                'order_cost_currency' => 'CNY',
            ]),
            '0.085',
        );
        self::assertTrue($ok->eligible);
        self::assertSame('135.0100', $ok->orderCostCny);
    }

    public function test_ozon_order_cost_rub_converted_to_cny_limits(): void
    {
        $channel = $this->ozonExtraSmallChannel();
        $rate = '0.085';

        // 1000 RUB * 0.085 = 85 CNY: ниже min 135.01
        $below = $this->pricing->calculate(
            $channel,
            CalculationInput::fromArray([
                'platform' => 'ozon',
                'delivery_channel_code' => 'atc-express-extra-small',
                'physical_weight_grams' => '500',
                'length_cm' => '30',
                'width_cm' => '20',
                'height_cm' => '20',
                'order_cost' => '1000',
                'order_cost_currency' => 'RUB',
            ]),
            $rate,
        );
        self::assertFalse($below->eligible);
        self::assertNull($below->finalCost);
        self::assertSame(
            ['Order cost is outside the allowed range: 135.01-635.00 CNY.'],
            $below->errors,
        );
        self::assertSame('85.0000', $below->orderCostCny);

        // 2000 RUB * 0.085 = 170 CNY: в диапазоне
        $ok = $this->pricing->calculate(
            $channel,
            CalculationInput::fromArray([
                'platform' => 'ozon',
                'delivery_channel_code' => 'atc-express-extra-small',
                'physical_weight_grams' => '500',
                'length_cm' => '30',
                'width_cm' => '20',
                'height_cm' => '20',
                'order_cost' => '2000',
                'order_cost_currency' => 'RUB',
            ]),
            $rate,
        );
        self::assertTrue($ok->eligible);
        self::assertSame('170.0000', $ok->orderCostCny);
        self::assertNotNull($ok->finalCost);
    }

    public function test_not_eligible_returns_null_cost_and_all_errors(): void
    {
        $channel = $this->ozonExtraSmallChannel();

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

    public function test_yandex_rounds_80_to_100(): void
    {
        $result = $this->pricing->calculate(
            $this->yandexSuperExpressChannel(),
            CalculationInput::fromArray([
                'platform' => 'yandex_market',
                'delivery_channel_code' => 'super-express',
                'physical_weight_grams' => '80',
            ]),
            '0.085',
        );

        self::assertTrue($result->eligible);
        self::assertSame('80.000', $result->physicalWeightGrams);
        self::assertSame('100.000', $result->billedWeightGrams);
        // 193 + 948 * 0.1 = 287.80
        self::assertSame('287.80', $result->finalCost);
    }

    public function test_yandex_keeps_500_grams(): void
    {
        $result = $this->pricing->calculate(
            $this->yandexSuperExpressChannel(),
            CalculationInput::fromArray([
                'platform' => 'yandex_market',
                'delivery_channel_code' => 'super-express',
                'physical_weight_grams' => '500',
            ]),
            '0.085',
        );

        self::assertTrue($result->eligible);
        self::assertSame('500.000', $result->billedWeightGrams);
        // 193 + 948 * 0.5 = 667.00
        self::assertSame('667.00', $result->finalCost);
    }

    public function test_yandex_super_express_550_grams(): void
    {
        $result = $this->pricing->calculate(
            $this->yandexSuperExpressChannel(),
            CalculationInput::fromArray([
                'platform' => 'yandex_market',
                'delivery_channel_code' => 'super-express',
                'physical_weight_grams' => '550',
            ]),
            '0.085',
        );

        self::assertTrue($result->eligible);
        self::assertSame('550.000', $result->physicalWeightGrams);
        self::assertSame('600.000', $result->billedWeightGrams);
        // 193 + 948 * 0.6 = 761.80
        self::assertSame('761.80', $result->finalCost);
        self::assertSame('64.7530', $result->finalCostCny);
        self::assertSame('0.085000', $result->exchangeRate);
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

        $result = $this->pricing->calculate(
            $channel,
            CalculationInput::fromArray([
                'platform' => 'yandex_market',
                'delivery_channel_code' => 'express',
                'physical_weight_grams' => '550',
            ]),
            '0.085',
        );

        self::assertTrue($result->eligible);
        self::assertSame('600.000', $result->billedWeightGrams);
        // 198 + 787 * 0.6 = 670.20
        self::assertSame('670.20', $result->finalCost);
        self::assertSame('56.9670', $result->finalCostCny);
    }

    public function test_yandex_converts_final_cost_by_active_rate(): void
    {
        $result = $this->pricing->calculate(
            $this->yandexSuperExpressChannel(),
            CalculationInput::fromArray([
                'platform' => 'yandex_market',
                'delivery_channel_code' => 'super-express',
                'physical_weight_grams' => '550',
            ]),
            '0.1',
        );

        self::assertTrue($result->eligible);
        self::assertSame('761.80', $result->finalCost);
        self::assertSame('76.1800', $result->finalCostCny);
        self::assertSame('0.100000', $result->exchangeRate);
    }

    public function test_yandex_without_limits_does_not_invent_errors(): void
    {
        $channel = $this->yandexSuperExpressChannel();
        $channel->min_weight_grams = null;
        $channel->max_weight_grams = null;
        $channel->max_length_cm = null;
        $channel->max_sum_dimensions_cm = null;

        $eligibility = $this->eligibility->validate(
            $channel,
            CalculationInput::fromArray([
                'platform' => 'yandex_market',
                'delivery_channel_code' => 'super-express',
                'physical_weight_grams' => '50000',
                'length_cm' => '300',
                'width_cm' => '300',
                'height_cm' => '300',
            ]),
            '0.085',
        );

        self::assertTrue($eligibility->eligible);
        self::assertSame([], $eligibility->errors);
    }

    private function ozonExtraSmallChannel(): DeliveryChannel
    {
        return new DeliveryChannel([
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
    }

    private function ozonBigChannel(): DeliveryChannel
    {
        return new DeliveryChannel([
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
    }

    private function yandexSuperExpressChannel(): DeliveryChannel
    {
        return new DeliveryChannel([
            'platform' => Platform::YandexMarket,
            'code' => 'super-express',
            'name' => 'Super Express',
            'active' => true,
            'currency' => 'RUB',
            'fixed_fee' => '193',
            'per_kg_fee' => '948',
            'billing_increment_grams' => 100,
        ]);
    }
}
