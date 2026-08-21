<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AppSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AppSetting>
 */
class AppSettingFactory extends Factory
{
    protected $model = AppSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'value' => fake()->numerify('#.####'),
            'updated_by_user_id' => null,
        ];
    }

    /**
     * Курс RUB → CNY по умолчанию для seed и тестов.
     */
    public function rubToCnyRate(?string $rate = null, ?User $updatedBy = null): static
    {
        return $this->state(fn (): array => [
            'key' => AppSetting::KEY_RUB_TO_CNY_RATE,
            'value' => $rate ?? AppSetting::DEFAULT_RUB_TO_CNY_RATE,
            'updated_by_user_id' => $updatedBy?->id,
        ]);
    }
}
