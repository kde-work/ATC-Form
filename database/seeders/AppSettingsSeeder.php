<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

/**
 * Заполняет стартовые ключи app_settings.
 */
class AppSettingsSeeder extends Seeder
{
    public function run(): void
    {
        AppSetting::query()->firstOrCreate(
            ['key' => AppSetting::KEY_RUB_TO_CNY_RATE],
            ['value' => AppSetting::DEFAULT_RUB_TO_CNY_RATE]
        );
    }
}
