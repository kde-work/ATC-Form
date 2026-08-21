<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\Platform;
use App\Enums\RevisionStatus;
use App\Enums\WeightCalculationType;
use App\Models\AppSetting;
use App\Models\DeliveryChannel;
use App\Models\TariffImport;
use App\Models\TariffRevision;
use App\Models\User;
use App\Services\Calculator\CalculatorFormDataCache;
use App\Services\TariffImport\TariffRevisionActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Проверяет публичные endpoints калькулятора: happy-path и not eligible.
 */
final class PublicCalculatorApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AppSetting::factory()->rubToCnyRate('0.085')->create();
    }

    public function test_platforms_returns_fixed_list(): void
    {
        $response = $this->getJson('/api/v1/platforms');

        $response->assertOk();
        $response->assertExactJson([
            ['code' => 'ozon', 'name' => 'Ozon'],
            ['code' => 'yandex_market', 'name' => 'Yandex Market'],
        ]);
    }

    public function test_delivery_channels_empty_without_active_revision(): void
    {
        $response = $this->getJson('/api/v1/delivery-channels?platform=ozon');

        $response->assertOk();
        $response->assertExactJson([]);
    }

    public function test_delivery_channels_returns_only_active_from_active_revision(): void
    {
        $revision = $this->createActiveRevision();

        DeliveryChannel::factory()->ozonBig()->for($revision, 'revision')->create([
            'code' => 'atc-standard-big',
            'name' => 'ATC Standard Big',
            'active' => true,
        ]);
        DeliveryChannel::factory()->ozonPhysical()->for($revision, 'revision')->create([
            'code' => 'inactive-channel',
            'name' => 'Inactive',
            'active' => false,
        ]);
        DeliveryChannel::factory()->yandexExpress()->for($revision, 'revision')->create();

        $draft = TariffRevision::factory()->draft()->create(['version_number' => 2]);
        DeliveryChannel::factory()->ozonPhysical()->for($draft, 'revision')->create([
            'code' => 'draft-only',
            'name' => 'Draft Only',
        ]);

        $response = $this->getJson('/api/v1/delivery-channels?platform=ozon');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonPath('0.code', 'atc-standard-big');
        $response->assertJsonPath('0.platform', 'ozon');
    }

    public function test_public_settings_returns_exchange_rate(): void
    {
        $response = $this->getJson('/api/v1/settings/public');

        $response->assertOk();
        $response->assertJsonPath('rub_to_cny_rate', '0.085000');
        $response->assertJsonStructure(['rub_to_cny_rate', 'updated_at']);
    }

    public function test_calculator_bootstrap_returns_platforms_channels_and_settings(): void
    {
        $revision = $this->createActiveRevision();
        DeliveryChannel::factory()->ozonBig()->for($revision, 'revision')->create([
            'code' => 'atc-standard-big',
            'name' => 'ATC Standard Big',
            'active' => true,
        ]);
        DeliveryChannel::factory()->yandexExpress()->for($revision, 'revision')->create([
            'code' => 'yandex-express',
            'name' => 'Express',
            'active' => true,
        ]);

        $response = $this->getJson('/api/v1/calculator/bootstrap');

        $response->assertOk();
        $response->assertJsonPath('platforms.0.code', 'ozon');
        $response->assertJsonPath('platforms.1.code', 'yandex_market');
        $response->assertJsonPath('settings.rub_to_cny_rate', '0.085000');
        $response->assertJsonCount(2, 'delivery_channels');
        $codes = collect($response->json('delivery_channels'))->pluck('code')->all();
        $this->assertContains('atc-standard-big', $codes);
        $this->assertContains('yandex-express', $codes);
    }

    public function test_form_data_cache_reused_until_forgotten(): void
    {
        Cache::flush();

        $this->getJson('/api/v1/calculator/bootstrap')->assertOk();
        $this->assertTrue(Cache::has(CalculatorFormDataCache::CACHE_KEY));

        $cached = Cache::get(CalculatorFormDataCache::CACHE_KEY);
        $this->assertIsArray($cached);
        $this->assertArrayHasKey('platforms', $cached);

        // Повторный запрос не должен падать и должен читать тот же ключ.
        $this->getJson('/api/v1/platforms')->assertOk();
        $this->assertTrue(Cache::has(CalculatorFormDataCache::CACHE_KEY));
    }

    public function test_form_data_cache_invalidated_after_exchange_rate_change(): void
    {
        Cache::flush();
        $this->getJson('/api/v1/settings/public')->assertJsonPath('rub_to_cny_rate', '0.085000');
        $this->assertTrue(Cache::has(CalculatorFormDataCache::CACHE_KEY));

        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/admin/settings/exchange-rate', [
            'rub_to_cny_rate' => '0.091',
        ])->assertOk();

        $this->assertFalse(Cache::has(CalculatorFormDataCache::CACHE_KEY));

        $this->getJson('/api/v1/settings/public')
            ->assertOk()
            ->assertJsonPath('rub_to_cny_rate', '0.091000');
    }

    public function test_form_data_cache_invalidated_after_revision_activation(): void
    {
        Cache::flush();

        $user = User::factory()->create();
        $firstImport = TariffImport::factory()->validated()->for($user, 'uploadedBy')->create();
        $firstRevision = TariffRevision::factory()->active()->for($firstImport, 'import')->create([
            'version_number' => 1,
        ]);
        DeliveryChannel::factory()->ozonPhysical()->for($firstRevision, 'revision')->create([
            'code' => 'channel-v1',
            'name' => 'Channel V1',
            'active' => true,
        ]);

        $this->getJson('/api/v1/delivery-channels?platform=ozon')
            ->assertOk()
            ->assertJsonPath('0.code', 'channel-v1');
        $this->assertTrue(Cache::has(CalculatorFormDataCache::CACHE_KEY));

        $secondImport = TariffImport::factory()->validated()->for($user, 'uploadedBy')->create();
        $secondRevision = TariffRevision::factory()->draft()->for($secondImport, 'import')->create([
            'version_number' => 2,
        ]);
        DeliveryChannel::factory()->ozonPhysical()->for($secondRevision, 'revision')->create([
            'code' => 'channel-v2',
            'name' => 'Channel V2',
            'active' => true,
        ]);

        $this->app->make(TariffRevisionActivationService::class)->activate($secondImport, $user);

        $this->assertFalse(Cache::has(CalculatorFormDataCache::CACHE_KEY));
        $this->getJson('/api/v1/delivery-channels?platform=ozon')
            ->assertOk()
            ->assertJsonPath('0.code', 'channel-v2');
    }

    public function test_calculation_happy_path_ozon_big(): void
    {
        $revision = $this->createActiveRevision();
        DeliveryChannel::factory()->ozonBig()->for($revision, 'revision')->create([
            'code' => 'atc-standard-big',
            'name' => 'ATC Standard Big',
        ]);

        $response = $this->postJson('/api/v1/calculations', [
            'platform' => 'ozon',
            'delivery_channel_code' => 'atc-standard-big',
            'physical_weight_grams' => '2500',
            'length_cm' => '100',
            'width_cm' => '40',
            'height_cm' => '30',
            'order_cost' => '500',
            'order_cost_currency' => 'CNY',
        ]);

        $response->assertOk();
        $response->assertJsonPath('eligible', true);
        $response->assertJsonPath('final_cost', '321.44');
        $response->assertJsonPath('volumetric_weight_grams', '10000.000');
        $response->assertJsonPath('chargeable_weight_grams', '10000.000');
        $response->assertJsonPath('errors', []);
    }

    public function test_calculation_not_eligible_returns_200_with_null_cost(): void
    {
        $revision = $this->createActiveRevision();
        DeliveryChannel::factory()->for($revision, 'revision')->create([
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

        $response = $this->postJson('/api/v1/calculations', [
            'platform' => 'ozon',
            'delivery_channel_code' => 'atc-express-extra-small',
            'physical_weight_grams' => '2500',
            'length_cm' => '80',
            'width_cm' => '40',
            'height_cm' => '40',
            'order_cost' => '50',
            'order_cost_currency' => 'CNY',
        ]);

        $response->assertOk();
        $response->assertJsonPath('eligible', false);
        $response->assertJsonPath('final_cost', null);
        $response->assertJsonCount(4, 'errors');
    }

    public function test_calculation_unknown_channel_returns_422(): void
    {
        $response = $this->postJson('/api/v1/calculations', [
            'platform' => 'yandex_market',
            'delivery_channel_code' => 'missing',
            'physical_weight_grams' => '550',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('code', 'validation_error');
        $response->assertJsonStructure(['errors' => ['delivery_channel_code']]);
    }

    public function test_calculation_yandex_super_express(): void
    {
        $revision = $this->createActiveRevision();
        DeliveryChannel::factory()->yandexSuperExpress()->for($revision, 'revision')->create();

        $response = $this->postJson('/api/v1/calculations', [
            'platform' => 'yandex_market',
            'delivery_channel_code' => 'super-express',
            'physical_weight_grams' => '550',
        ]);

        $response->assertOk();
        $response->assertJsonPath('eligible', true);
        $response->assertJsonPath('billed_weight_grams', '600.000');
        $response->assertJsonPath('final_cost', '761.80');
        $response->assertJsonPath('final_cost_cny', '64.7530');
        $response->assertJsonPath('exchange_rate', '0.085000');
    }

    public function test_calculation_yandex_express_550(): void
    {
        $revision = $this->createActiveRevision();
        DeliveryChannel::factory()->yandexExpress()->for($revision, 'revision')->create();

        $response = $this->postJson('/api/v1/calculations', [
            'platform' => 'yandex_market',
            'delivery_channel_code' => 'express',
            'physical_weight_grams' => '550',
        ]);

        $response->assertOk();
        $response->assertJsonPath('eligible', true);
        $response->assertJsonPath('billed_weight_grams', '600.000');
        $response->assertJsonPath('final_cost', '670.20');
        $response->assertJsonPath('final_cost_cny', '56.9670');
    }

    public function test_calculation_ozon_extra_small_physical_weight(): void
    {
        $revision = $this->createActiveRevision();
        DeliveryChannel::factory()->ozonPhysical()->for($revision, 'revision')->create([
            'code' => 'atc-express-extra-small',
            'name' => 'ATC Express Extra Small',
            'fixed_fee' => '3.370000',
            'per_gram_fee' => '0.05050000',
            'min_weight_grams' => '1.000',
            'max_weight_grams' => '2000.000',
            'max_length_cm' => '60.000',
            'max_sum_dimensions_cm' => '150.000',
            'min_order_cost_cny' => '135.0100',
            'max_order_cost_cny' => '635.0000',
        ]);

        $response = $this->postJson('/api/v1/calculations', [
            'platform' => 'ozon',
            'delivery_channel_code' => 'atc-express-extra-small',
            'physical_weight_grams' => '1000',
            'length_cm' => '50',
            'width_cm' => '40',
            'height_cm' => '30',
            'order_cost' => '200',
            'order_cost_currency' => 'CNY',
        ]);

        $response->assertOk();
        $response->assertJsonPath('eligible', true);
        $response->assertJsonPath('chargeable_weight_grams', '1000.000');
        $response->assertJsonPath('volumetric_weight_grams', null);
        $response->assertJsonPath('final_cost', '53.87');
    }

    public function test_calculation_ozon_order_cost_rub_converted_to_cny(): void
    {
        $revision = $this->createActiveRevision();
        DeliveryChannel::factory()->ozonPhysical()->for($revision, 'revision')->create([
            'code' => 'atc-express-extra-small',
            'name' => 'ATC Express Extra Small',
            'fixed_fee' => '3.370000',
            'per_gram_fee' => '0.05050000',
            'min_order_cost_cny' => '135.0100',
            'max_order_cost_cny' => '635.0000',
        ]);

        $response = $this->postJson('/api/v1/calculations', [
            'platform' => 'ozon',
            'delivery_channel_code' => 'atc-express-extra-small',
            'physical_weight_grams' => '500',
            'length_cm' => '30',
            'width_cm' => '20',
            'height_cm' => '20',
            'order_cost' => '2000',
            'order_cost_currency' => 'RUB',
        ]);

        $response->assertOk();
        $response->assertJsonPath('eligible', true);
        $response->assertJsonPath('order_cost_currency', 'RUB');
        $response->assertJsonPath('order_cost_cny', '170.0000');
    }

    public function test_calculation_uses_only_active_revision_rates(): void
    {
        $user = User::factory()->create();

        $activeImport = TariffImport::factory()->validated()->for($user, 'uploadedBy')->create();
        $activeRevision = TariffRevision::factory()->active()->for($activeImport, 'import')->create([
            'version_number' => 1,
        ]);
        DeliveryChannel::factory()->ozonPhysical()->for($activeRevision, 'revision')->create([
            'code' => 'atc-express-extra-small',
            'name' => 'ATC Express Extra Small',
            'fixed_fee' => '3.370000',
            'per_gram_fee' => '0.05050000',
            'min_order_cost_cny' => '135.0100',
            'max_order_cost_cny' => '635.0000',
        ]);

        $draftImport = TariffImport::factory()->validated()->for($user, 'uploadedBy')->create();
        $draftRevision = TariffRevision::factory()->draft()->for($draftImport, 'import')->create([
            'version_number' => 2,
        ]);
        DeliveryChannel::factory()->ozonPhysical()->for($draftRevision, 'revision')->create([
            'code' => 'atc-express-extra-small',
            'name' => 'ATC Express Extra Small',
            'fixed_fee' => '99.990000',
            'per_gram_fee' => '0.99990000',
            'min_order_cost_cny' => '135.0100',
            'max_order_cost_cny' => '635.0000',
        ]);

        $response = $this->postJson('/api/v1/calculations', [
            'platform' => 'ozon',
            'delivery_channel_code' => 'atc-express-extra-small',
            'physical_weight_grams' => '1000',
            'length_cm' => '30',
            'width_cm' => '20',
            'height_cm' => '20',
            'order_cost' => '200',
            'order_cost_currency' => 'CNY',
        ]);

        $response->assertOk();
        $response->assertJsonPath('eligible', true);
        // Ставки только из active, не из draft 99.99 / 0.9999
        $response->assertJsonPath('final_cost', '53.87');
        $response->assertJsonPath('fixed_fee', '3.37');
    }

    public function test_delivery_channels_requires_platform(): void
    {
        $response = $this->getJson('/api/v1/delivery-channels');

        $response->assertUnprocessable();
        $response->assertJsonPath('code', 'validation_error');
    }

    private function createActiveRevision(): TariffRevision
    {
        $user = User::factory()->create();
        $import = TariffImport::factory()->validated()->for($user, 'uploadedBy')->create();

        return TariffRevision::factory()->active()->for($import, 'import')->create([
            'version_number' => 1,
            'status' => RevisionStatus::Active,
        ]);
    }
}
