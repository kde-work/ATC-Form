<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ImportStatus;
use App\Models\AppSetting;
use App\Models\User;
use App\Services\TariffImport\TariffImportService;
use App\Services\TariffImport\TariffRevisionActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\TariffsXlsxFixtureBuilder;
use Tests\TestCase;

/**
 * Кейсы из CSV после тестов калькулятора: лимиты и ставки исходного Excel.
 */
final class CustomerCalculatorCasesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        AppSetting::factory()->rubToCnyRate('0.085')->create();

        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'atc-customer-cases-' . uniqid('', true) . '.xlsx';
        TariffsXlsxFixtureBuilder::write($path);

        $user = User::factory()->create();
        $import = $this->app->make(TariffImportService::class)->uploadAndProcess(
            new UploadedFile($path, 'valid-tariffs.xlsx', null, null, true),
            $user,
        );
        $this->assertSame(ImportStatus::Validated, $import->status);
        $this->app->make(TariffRevisionActivationService::class)->activate($import, $user);
        @unlink($path);
    }

    /**
     * @param array<string, string> $payload
     */
    #[DataProvider('eligibleCasesProvider')]
    public function test_csv_cases_are_eligible_with_expected_cost(
        array $payload,
        string $expectedCost,
    ): void {
        $response = $this->postJson('/api/v1/calculations', $payload);

        $response->assertOk();
        $response->assertJsonPath('eligible', true);
        $response->assertJsonPath('final_cost', $expectedCost);
        $response->assertJsonPath('errors', []);
    }

    /**
     * @return array<string, array{0: array<string, string>, 1: string}>
     */
    public static function eligibleCasesProvider(): array
    {
        return [
            'economy big 60x70x80' => [
                self::ozon('atc-economy-big', '3000', '60', '70', '80', '5000'),
                '575.24',
            ],
            'economy budget 30kg' => [
                self::ozon('atc-economy-budget', '3000', '20', '30', '40', '1000'),
                '83.13',
            ],
            'economy premium big 310/150' => [
                self::ozon('atc-economy-premium-big', '6000', '50', '60', '70', '8000'),
                '521.14',
            ],
            'economy premium small cost' => [
                self::ozon('atc-economy-premium-small', '2000', '10', '20', '30', '8000'),
                '80.91',
            ],
            'economy small cost' => [
                self::ozon('atc-economy-small', '1000', '10', '20', '30', '2000'),
                '46.07',
            ],
            'express extra small 1500 rub' => [
                self::ozon('atc-express-extra-small', '200', '10', '20', '30', '1000'),
                '13.47',
            ],
            'express premium small 250/150' => [
                self::ozon('atc-express-premium-small', '2000', '40', '50', '60', '8000'),
                '125.71',
            ],
            'express small cost' => [
                self::ozon('atc-express-small', '1500', '20', '30', '40', '5000'),
                '93.72',
            ],
            'standard big ok' => [
                self::ozon('atc-standard-big', '3000', '20', '30', '40', '5000'),
                '124.74',
            ],
            'standard budget 30kg' => [
                self::ozon('atc-standard-budget', '3000', '20', '30', '40', '1000'),
                '110.13',
            ],
            'standard extra small 1500 rub' => [
                self::ozon('atc-standard-extra-small', '200', '10', '20', '30', '1000'),
                '11.23',
            ],
            'standard premium big cost' => [
                self::ozon('atc-standard-premium-big', '6000', '40', '50', '60', '9000'),
                '383.64',
            ],
            'standard premium small 250/150' => [
                self::ozon('atc-standard-premium-small', '2000', '40', '50', '60', '9000'),
                '103.31',
            ],
            'standard small cost' => [
                self::ozon('atc-standard-small', '1500', '30', '40', '50', '5000'),
                '76.92',
            ],
        ];
    }

    public function test_economy_extra_small_1000g_fails_weight_not_order_cost(): void
    {
        $response = $this->postJson('/api/v1/calculations', self::ozon(
            'atc-economy-extra-small',
            '1000',
            '10',
            '20',
            '30',
            '1000',
        ));

        $response->assertOk();
        $response->assertJsonPath('eligible', false);
        $errors = $response->json('errors');
        $this->assertIsArray($errors);
        $this->assertContains('Physical weight exceeds the maximum allowed weight of 500 g.', $errors);
        foreach ($errors as $error) {
            $this->assertStringNotContainsString('Order cost', (string) $error);
        }
    }

    /**
     * @return array<string, string>
     */
    private static function ozon(
        string $code,
        string $weightGrams,
        string $length,
        string $width,
        string $height,
        string $orderCostRub,
    ): array {
        return [
            'platform' => 'ozon',
            'delivery_channel_code' => $code,
            'physical_weight_grams' => $weightGrams,
            'length_cm' => $length,
            'width_cm' => $width,
            'height_cm' => $height,
            'order_cost' => $orderCostRub,
            'order_cost_currency' => 'RUB',
        ];
    }
}
