<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Platform;
use App\Enums\RevisionStatus;
use App\Models\AppSetting;
use App\Models\DeliveryChannel;
use App\Models\TariffRevision;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Collection;
use RuntimeException;

/**
 * Чтение активной ревизии тарифов и публичных настроек.
 */
final class ActiveTariffQuery
{
    /**
     * Активные каналы платформы из единственной active-ревизии.
     * Нет active-ревизии: пустая коллекция.
     *
     * @return Collection<int, DeliveryChannel>
     */
    public function activeChannels(Platform $platform): Collection
    {
        $revision = $this->activeRevision();
        if ($revision === null) {
            return new Collection();
        }

        return DeliveryChannel::query()
            ->where('tariff_revision_id', $revision->id)
            ->where('platform', $platform)
            ->where('active', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * Канал по коду в активной ревизии (только active=true).
     */
    public function findActiveChannel(Platform $platform, string $code): ?DeliveryChannel
    {
        $revision = $this->activeRevision();
        if ($revision === null) {
            return null;
        }

        return DeliveryChannel::query()
            ->where('tariff_revision_id', $revision->id)
            ->where('platform', $platform)
            ->where('code', $code)
            ->where('active', true)
            ->first();
    }

    public function activeRevision(): ?TariffRevision
    {
        return TariffRevision::query()
            ->where('status', RevisionStatus::Active)
            ->first();
    }

    /**
     * Активный курс RUB → CNY строкой.
     *
     * @return numeric-string
     */
    public function rubToCnyRate(): string
    {
        $setting = $this->exchangeRateSetting();

        return Decimal::normalize($setting->value, 8);
    }

    public function exchangeRateSetting(): AppSetting
    {
        $setting = AppSetting::query()
            ->where('key', AppSetting::KEY_RUB_TO_CNY_RATE)
            ->first();

        if ($setting === null) {
            throw new RuntimeException('Exchange rate setting is not configured.');
        }

        return $setting;
    }
}
