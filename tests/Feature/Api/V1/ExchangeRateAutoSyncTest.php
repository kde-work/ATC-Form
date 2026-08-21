<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\AppSetting;
use App\Services\Calculator\CalculatorFormDataCache;
use App\Services\ExchangeRate\ExchangeRateSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Автообновление курса с ЦБ РФ по API-запросу (wp-cron style).
 */
final class ExchangeRateAutoSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AppSetting::factory()->rubToCnyRate('0.085')->create();
        // Разрешаем sync в этом классе: базовый TestCase ставит next_due в будущее.
        Cache::forget(ExchangeRateSyncService::CACHE_KEY_NEXT_DUE_AT);
        Cache::forget(ExchangeRateSyncService::CACHE_KEY_SYNC_LOCK);
        Http::preventStrayRequests();
    }

    public function test_public_request_refreshes_rate_from_cbr_when_due(): void
    {
        Http::fake([
            'www.cbr.ru/*' => Http::response($this->sampleCbrXml(), 200, [
                'Content-Type' => 'application/xml',
            ]),
        ]);

        $this->getJson('/api/v1/settings/public')
            ->assertOk()
            ->assertJsonPath('rub_to_cny_rate', '0.085000');

        $this->assertSame(
            '0.08107472',
            AppSetting::query()->where('key', AppSetting::KEY_RUB_TO_CNY_RATE)->value('value'),
        );
        $this->assertNotNull(Cache::get(ExchangeRateSyncService::CACHE_KEY_NEXT_DUE_AT));

        Http::assertSentCount(1);
    }

    public function test_second_request_within_interval_does_not_hit_cbr_again(): void
    {
        Http::fake([
            'www.cbr.ru/*' => Http::response($this->sampleCbrXml(), 200),
        ]);

        $this->getJson('/api/v1/settings/public')->assertOk();
        $this->getJson('/api/v1/settings/public')->assertOk();

        Http::assertSentCount(1);
    }

    public function test_failed_cbr_fetch_keeps_previous_rate_and_retries_later(): void
    {
        Http::fake([
            'www.cbr.ru/*' => Http::response('unavailable', 503),
        ]);

        $this->getJson('/api/v1/settings/public')
            ->assertOk()
            ->assertJsonPath('rub_to_cny_rate', '0.085000');

        $this->assertSame(
            '0.085',
            AppSetting::query()->where('key', AppSetting::KEY_RUB_TO_CNY_RATE)->value('value'),
        );

        $nextDue = (int) Cache::get(ExchangeRateSyncService::CACHE_KEY_NEXT_DUE_AT);
        $this->assertGreaterThan(time(), $nextDue);
        $this->assertLessThanOrEqual(time() + (int) config('atc.exchange_rate.retry_after_seconds') + 5, $nextDue);
    }

    public function test_auto_sync_invalidates_form_data_cache(): void
    {
        Cache::put(CalculatorFormDataCache::CACHE_KEY, [
            'platforms' => [],
            'delivery_channels' => [],
            'settings' => [
                'rub_to_cny_rate' => '0.085000',
                'updated_at' => now()->toIso8601String(),
            ],
        ], 3600);

        Http::fake([
            'www.cbr.ru/*' => Http::response($this->sampleCbrXml(), 200),
        ]);

        $this->getJson('/api/v1/platforms')->assertOk();

        $this->assertFalse(Cache::has(CalculatorFormDataCache::CACHE_KEY));
    }

    private function sampleCbrXml(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="windows-1251"?>
<ValCurs Date="22.08.2026" name="Foreign Currency Market">
  <Valute ID="R01375">
    <NumCode>156</NumCode>
    <CharCode>CNY</CharCode>
    <Nominal>1</Nominal>
    <Name>Юань</Name>
    <Value>12,3343</Value>
  </Valute>
</ValCurs>
XML;
    }
}
