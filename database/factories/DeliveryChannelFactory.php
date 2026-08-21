<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Platform;
use App\Enums\WeightCalculationType;
use App\Models\DeliveryChannel;
use App\Models\TariffRevision;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DeliveryChannel>
 */
class DeliveryChannelFactory extends Factory
{
    protected $model = DeliveryChannel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'ATC ' . fake()->words(2, true);

        return [
            'tariff_revision_id' => TariffRevision::factory(),
            'platform' => Platform::Ozon,
            'code' => Str::slug($name),
            'name' => $name,
            'active' => true,
            'currency' => 'CNY',
            'chargeable_weight_type' => WeightCalculationType::Physical,
            'fixed_fee' => '10.000000',
            'per_gram_fee' => '0.01000000',
            'per_kg_fee' => null,
            'billing_increment_grams' => null,
            'volumetric_divisor' => null,
            'min_weight_grams' => '1.000',
            'max_weight_grams' => '2000.000',
            'max_length_cm' => '60.000',
            'max_sum_dimensions_cm' => '150.000',
            'min_order_cost_rub' => null,
            'max_order_cost_rub' => null,
            'min_order_cost_cny' => '0.0100',
            'max_order_cost_cny' => '5000.0000',
            'import_source_row' => fake()->optional()->numberBetween(2, 50),
            'raw_data_json' => null,
        ];
    }

    public function ozonPhysical(): static
    {
        return $this->state(fn (): array => [
            'platform' => Platform::Ozon,
            'currency' => 'CNY',
            'chargeable_weight_type' => WeightCalculationType::Physical,
            'per_gram_fee' => '0.05050000',
            'per_kg_fee' => null,
            'billing_increment_grams' => null,
            'volumetric_divisor' => null,
        ]);
    }

    public function ozonBig(): static
    {
        return $this->state(fn (): array => [
            'platform' => Platform::Ozon,
            'currency' => 'CNY',
            'chargeable_weight_type' => WeightCalculationType::MaxPhysicalOrVolumetric,
            'fixed_fee' => '40.440000',
            'per_gram_fee' => '0.02810000',
            'per_kg_fee' => null,
            'billing_increment_grams' => null,
            'volumetric_divisor' => '12000.0000',
            'min_weight_grams' => '501.000',
            'max_weight_grams' => '30000.000',
            'max_length_cm' => '120.000',
            'max_sum_dimensions_cm' => '300.000',
        ]);
    }

    public function yandexSuperExpress(): static
    {
        return $this->state(fn (): array => [
            'platform' => Platform::YandexMarket,
            'code' => 'super-express',
            'name' => 'Super Express',
            'currency' => 'RUB',
            'chargeable_weight_type' => null,
            'fixed_fee' => '193.000000',
            'per_gram_fee' => null,
            'per_kg_fee' => '948.000000',
            'billing_increment_grams' => 100,
            'volumetric_divisor' => null,
            'min_weight_grams' => null,
            'max_weight_grams' => null,
            'max_length_cm' => null,
            'max_sum_dimensions_cm' => null,
            'min_order_cost_cny' => null,
            'max_order_cost_cny' => null,
        ]);
    }

    public function yandexExpress(): static
    {
        return $this->state(fn (): array => [
            'platform' => Platform::YandexMarket,
            'code' => 'express',
            'name' => 'Express',
            'currency' => 'RUB',
            'chargeable_weight_type' => null,
            'fixed_fee' => '198.000000',
            'per_gram_fee' => null,
            'per_kg_fee' => '787.000000',
            'billing_increment_grams' => 100,
            'volumetric_divisor' => null,
            'min_weight_grams' => null,
            'max_weight_grams' => null,
            'max_length_cm' => null,
            'max_sum_dimensions_cm' => null,
            'min_order_cost_cny' => null,
            'max_order_cost_cny' => null,
        ]);
    }
}
