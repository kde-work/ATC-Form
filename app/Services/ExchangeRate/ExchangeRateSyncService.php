<?php

declare(strict_types=1);

namespace App\Services\ExchangeRate;

use App\Models\User;
use App\Services\ActiveTariffQuery;
use App\Services\Calculator\CalculatorFormDataCache;
use App\Support\Decimal;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Обновление курса RUB → CNY раз в сутки без системного cron.
 *
 * Расписание хранится в file-cache: при заходе пользователя middleware
 * планирует sync после ответа, если интервал истёк.
 */
final class ExchangeRateSyncService
{
    public const CACHE_KEY_NEXT_DUE_AT = 'exchange_rate.next_due_at';

    public const CACHE_KEY_SYNC_LOCK = 'exchange_rate.sync_lock';

    public function __construct(
        private readonly CbrDailyRateClient $cbrClient,
        private readonly ActiveTariffQuery $activeTariffQuery,
        private readonly CalculatorFormDataCache $formDataCache,
    ) {
    }

    /**
     * Быстрая проверка без HTTP: пора ли планировать фоновый sync.
     */
    public function isDue(): bool
    {
        $nextDueAt = Cache::get(self::CACHE_KEY_NEXT_DUE_AT);
        if (! is_int($nextDueAt) && ! is_numeric($nextDueAt)) {
            return true;
        }

        return time() >= (int) $nextDueAt;
    }

    /**
     * Планирует sync после HTTP-ответа, если пора и нет активного lock.
     */
    public function scheduleIfDue(): void
    {
        if (! $this->isDue()) {
            return;
        }

        /** @var array{retry_after_seconds: int} $config */
        $config = config('atc.exchange_rate');
        $lockTtl = max(30, $config['retry_after_seconds']);

        // Один claim на окно: параллельные запросы не плодят несколько HTTP к ЦБ.
        if (! Cache::add(self::CACHE_KEY_SYNC_LOCK, 1, $lockTtl)) {
            return;
        }

        dispatch(function (): void {
            try {
                $this->syncIfDue();
            } finally {
                Cache::forget(self::CACHE_KEY_SYNC_LOCK);
            }
        })->afterResponse();
    }

    /**
     * Синхронизирует курс с ЦБ, если интервал истёк.
     *
     * @return bool true, если курс записан
     */
    public function syncIfDue(): bool
    {
        if (! $this->isDue()) {
            return false;
        }

        return $this->syncFromCbr();
    }

    /**
     * Принудительная загрузка с ЦБ (игнор расписания).
     *
     * @return bool true, если курс записан
     */
    public function syncFromCbr(): bool
    {
        /** @var array{interval_seconds: int, retry_after_seconds: int} $config */
        $config = config('atc.exchange_rate');

        try {
            $rate = $this->cbrClient->fetchRubToCnyRate();
            $this->persistRate($rate, null);
            $this->markNextDueIn($config['interval_seconds']);

            return true;
        } catch (Throwable $e) {
            Log::warning('Автообновление курса RUB→CNY не удалось.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
            $this->markNextDueIn($config['retry_after_seconds']);

            return false;
        }
    }

    /**
     * Сдвигает следующее автообновление (после ручного сохранения админом).
     */
    public function deferAutomaticSync(): void
    {
        /** @var array{interval_seconds: int} $config */
        $config = config('atc.exchange_rate');
        $this->markNextDueIn($config['interval_seconds']);
    }

    /**
     * @param numeric-string $rate
     */
    public function persistRate(string $rate, ?User $updatedBy): void
    {
        $normalized = Decimal::normalize($rate, 8);
        $setting = $this->activeTariffQuery->exchangeRateSetting();

        $sameRate = Decimal::compare($setting->value, $normalized, 8) === 0;
        $sameAuthor = $setting->updated_by_user_id === ($updatedBy?->id);

        if ($sameRate && $sameAuthor) {
            return;
        }

        $setting->value = $normalized;
        $setting->updated_by_user_id = $updatedBy?->id;
        $setting->save();

        if (! $sameRate) {
            $this->formDataCache->forget();
        }
    }

    private function markNextDueIn(int $seconds): void
    {
        $ttl = max(1, $seconds);
        Cache::put(self::CACHE_KEY_NEXT_DUE_AT, time() + $ttl, $ttl + 86_400);
    }
}
