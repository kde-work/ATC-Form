<?php

declare(strict_types=1);

namespace App\Services\Calculator;

use App\Enums\Platform;
use App\Models\DeliveryChannel;
use App\Services\ActiveTariffQuery;
use App\Support\Decimal;
use Illuminate\Support\Facades\Cache;

/**
 * Кэш справочников публичной формы калькулятора.
 *
 * Store: CACHE_STORE (локально file, без Redis). Инвалидация при смене
 * активной ревизии и курса.
 */
final class CalculatorFormDataCache
{
    public const CACHE_KEY = 'calculator.form_data';

    public function __construct(
        private readonly ActiveTariffQuery $activeTariffQuery,
    ) {
    }

    /**
     * Полный payload для презагрузки формы.
     *
     * @return array{
     *     platforms: list<array{code: string, name: string}>,
     *     delivery_channels: list<array{code: string, name: string, platform: string, currency: string}>,
     *     settings: array{rub_to_cny_rate: string, updated_at: string}
     * }
     */
    public function bootstrap(): array
    {
        /** @var array{
         *     platforms: list<array{code: string, name: string}>,
         *     delivery_channels: list<array{code: string, name: string, platform: string, currency: string}>,
         *     settings: array{rub_to_cny_rate: string, updated_at: string}
         * } $payload
         */
        $payload = Cache::rememberForever(self::CACHE_KEY, fn (): array => $this->build());

        return $payload;
    }

    /**
     * @return list<array{code: string, name: string}>
     */
    public function platforms(): array
    {
        return $this->bootstrap()['platforms'];
    }

    /**
     * @return list<array{code: string, name: string, platform: string, currency: string}>
     */
    public function deliveryChannels(?Platform $platform = null): array
    {
        $channels = $this->bootstrap()['delivery_channels'];
        if ($platform === null) {
            return $channels;
        }

        $code = $platform->value;

        return array_values(array_filter(
            $channels,
            static fn (array $channel): bool => $channel['platform'] === $code,
        ));
    }

    /**
     * @return array{rub_to_cny_rate: string, updated_at: string}
     */
    public function publicSettings(): array
    {
        return $this->bootstrap()['settings'];
    }

    /**
     * Сбрасывает кэш после activate/rollback или смены курса.
     */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array{
     *     platforms: list<array{code: string, name: string}>,
     *     delivery_channels: list<array{code: string, name: string, platform: string, currency: string}>,
     *     settings: array{rub_to_cny_rate: string, updated_at: string}
     * }
     */
    private function build(): array
    {
        $platforms = [];
        foreach (Platform::cases() as $platform) {
            $platforms[] = [
                'code' => $platform->value,
                'name' => $platform->label(),
            ];
        }

        $channels = [];
        $revision = $this->activeTariffQuery->activeRevision();
        if ($revision !== null) {
            /** @var list<DeliveryChannel> $rows */
            $rows = DeliveryChannel::query()
                ->where('tariff_revision_id', $revision->id)
                ->where('active', true)
                ->orderBy('platform')
                ->orderBy('name')
                ->get()
                ->all();

            foreach ($rows as $channel) {
                $channels[] = [
                    'code' => $channel->code,
                    'name' => $channel->name,
                    'platform' => $channel->platform->value,
                    'currency' => $channel->currency,
                ];
            }
        }

        $setting = $this->activeTariffQuery->exchangeRateSetting();

        return [
            'platforms' => $platforms,
            'delivery_channels' => $channels,
            'settings' => [
                'rub_to_cny_rate' => Decimal::toApiString($setting->value, 6),
                'updated_at' => $setting->updated_at?->toIso8601String() ?? now()->toIso8601String(),
            ],
        ];
    }
}
