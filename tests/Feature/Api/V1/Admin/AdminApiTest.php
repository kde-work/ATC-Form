<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\ImportStatus;
use App\Enums\Platform;
use App\Enums\RevisionStatus;
use App\Models\AppSetting;
use App\Models\DeliveryChannel;
use App\Models\TariffImport;
use App\Models\TariffRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Fixtures\TariffsXlsxFixtureBuilder;
use Tests\TestCase;

/**
 * Проверяет Admin API: auth, settings, tariffs, imports.
 */
final class AdminApiTest extends TestCase
{
    use RefreshDatabase;

    private string $fixturesDir;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        AppSetting::factory()->rubToCnyRate('0.085')->create();

        $this->fixturesDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'atc-admin-api-' . uniqid('', true);
        mkdir($this->fixturesDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->fixturesDir . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->fixturesDir);

        parent::tearDown();
    }

    public function test_login_returns_token_and_user(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@atc.form',
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson($this->adminApiUrl('login'), [
            'email' => 'admin@atc.form',
            'password' => 'password',
        ]);

        $response->assertOk();
        $response->assertJsonPath('token_type', 'Bearer');
        $response->assertJsonPath('user.email', 'admin@atc.form');
        $response->assertJsonPath('user.id', $user->id);
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'admin@atc.form',
            'password' => Hash::make('password'),
        ]);

        $response = $this->postJson($this->adminApiUrl('login'), [
            'email' => 'admin@atc.form',
            'password' => 'wrong',
        ]);

        $response->assertUnprocessable();
        $response->assertJsonPath('code', 'validation_error');
    }

    public function test_protected_routes_require_auth(): void
    {
        $response = $this->getJson($this->adminApiUrl('me'));

        $response->assertUnauthorized();
        $response->assertJsonPath('code', 'unauthenticated');
    }

    public function test_me_and_logout(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('admin')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson($this->adminApiUrl('me'))
            ->assertOk()
            ->assertJsonPath('email', $user->email);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson($this->adminApiUrl('logout'))
            ->assertOk()
            ->assertJsonPath('message', 'Logged out.');

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_settings_get_and_update_exchange_rate(): void
    {
        $user = User::factory()->create(['name' => 'Admin']);
        Sanctum::actingAs($user);

        $this->getJson($this->adminApiUrl('settings'))
            ->assertOk()
            ->assertJsonPath('rub_to_cny_rate', '0.085000')
            ->assertJsonPath('updated_by', null);

        $this->putJson($this->adminApiUrl('settings/exchange-rate'), [
            'rub_to_cny_rate' => '0.091',
        ])
            ->assertOk()
            ->assertJsonPath('rub_to_cny_rate', '0.091000')
            ->assertJsonPath('updated_by.id', $user->id)
            ->assertJsonPath('updated_by.name', 'Admin');

        $this->putJson($this->adminApiUrl('settings/exchange-rate'), [
            'rub_to_cny_rate' => '0',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_error');
    }

    public function test_tariffs_read_only_with_filters(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $revision = TariffRevision::factory()->active()->create(['version_number' => 1]);
        DeliveryChannel::factory()->ozonPhysical()->for($revision, 'revision')->create([
            'code' => 'atc-express-extra-small',
            'name' => 'ATC Express Extra Small',
            'fixed_fee' => '4.100000',
        ]);
        DeliveryChannel::factory()->yandexExpress()->for($revision, 'revision')->create();

        $draft = TariffRevision::factory()->draft()->create(['version_number' => 2]);
        DeliveryChannel::factory()->ozonPhysical()->for($draft, 'revision')->create([
            'code' => 'draft-only',
            'name' => 'Draft Only',
        ]);

        $this->getJson($this->adminApiUrl('tariffs'))
            ->assertOk()
            ->assertJsonCount(2);

        $this->getJson($this->adminApiUrl('tariffs') . '?platform=ozon')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.code', 'atc-express-extra-small')
            ->assertJsonPath('0.fixed_fee', '4.100000');

        $this->getJson($this->adminApiUrl('tariffs') . '?revision_id=' . $draft->id)
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.code', 'draft-only');
    }

    public function test_imports_list_filter_upload_activate_rollback(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $path = $this->fixturesDir . DIRECTORY_SEPARATOR . 'valid-tariffs.xlsx';
        TariffsXlsxFixtureBuilder::write($path);
        $upload = new UploadedFile($path, 'valid-tariffs.xlsx', null, null, true);

        $create = $this->post($this->adminApiUrl('imports'), ['file' => $upload], [
            'Accept' => 'application/json',
        ]);

        $create->assertCreated();
        $create->assertJsonPath('status', ImportStatus::Validated->value);
        $create->assertJsonPath('actions.can_activate', true);
        $create->assertJsonPath('parsed.total', 17);
        $create->assertJsonPath('parsed.by_platform.ozon', 15);
        $create->assertJsonPath('parsed.by_platform.yandex_market', 2);
        $this->assertIsArray($create->json('summary'));
        $this->assertIsArray($create->json('diff'));
        $this->assertIsArray($create->json('errors'));

        $importId = (int) $create->json('id');

        $this->getJson($this->adminApiUrl('imports') . '?status=validated')
            ->assertOk()
            ->assertJsonPath('data.0.id', $importId)
            ->assertJsonPath('data.0.actions.can_activate', true);

        $this->postJson($this->adminApiUrl('imports/' . $importId . '/activate'))
            ->assertOk()
            ->assertJsonPath('status', ImportStatus::Activated->value)
            ->assertJsonPath('revision.status', RevisionStatus::Active->value)
            ->assertJsonPath('actions.can_activate', false);

        $path2 = $this->fixturesDir . DIRECTORY_SEPARATOR . 'valid-tariffs-2.xlsx';
        TariffsXlsxFixtureBuilder::write($path2);
        $upload2 = new UploadedFile($path2, 'valid-tariffs-2.xlsx', null, null, true);

        $second = $this->post($this->adminApiUrl('imports'), ['file' => $upload2], [
            'Accept' => 'application/json',
        ]);
        $second->assertCreated();
        $secondId = (int) $second->json('id');

        $this->postJson($this->adminApiUrl('imports/' . $secondId . '/activate'))
            ->assertOk()
            ->assertJsonPath('revision.status', RevisionStatus::Active->value);

        $this->getJson($this->adminApiUrl('imports/' . $importId))
            ->assertOk()
            ->assertJsonPath('revision.status', RevisionStatus::Archived->value)
            ->assertJsonPath('actions.can_rollback', true);

        $this->postJson($this->adminApiUrl('imports/' . $importId . '/rollback'))
            ->assertOk()
            ->assertJsonPath('revision.status', RevisionStatus::Active->value);

        $active = TariffRevision::query()->where('status', RevisionStatus::Active)->get();
        $this->assertCount(1, $active);
        $this->assertSame($importId, $active->first()?->tariff_import_id);

        $channels = $this->getJson('/api/v1/delivery-channels?platform=' . Platform::Ozon->value);
        $channels->assertOk();
        $this->assertNotEmpty($channels->json());
    }

    public function test_activate_invalid_import_returns_domain_error(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $import = TariffImport::factory()->create([
            'status' => ImportStatus::ValidationFailed,
            'uploaded_by_user_id' => $user->id,
        ]);
        TariffRevision::factory()->create([
            'tariff_import_id' => $import->id,
            'status' => RevisionStatus::Invalid,
            'version_number' => 1,
        ]);

        $this->postJson($this->adminApiUrl('imports/' . $import->id . '/activate'))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'import_not_activatable');
    }

    public function test_import_file_download_returns_original_xlsx(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $path = $this->fixturesDir . DIRECTORY_SEPARATOR . 'download-me.xlsx';
        TariffsXlsxFixtureBuilder::write($path);
        $upload = new UploadedFile($path, 'history-tariffs.xlsx', null, null, true);

        $create = $this->post($this->adminApiUrl('imports'), ['file' => $upload], [
            'Accept' => 'application/json',
        ]);
        $create->assertCreated();
        $importId = (int) $create->json('id');

        $download = $this->get($this->adminApiUrl('imports/' . $importId . '/download'));
        $download->assertOk();
        $download->assertDownload('history-tariffs.xlsx');
        $download->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        $fileResponse = $download->baseResponse;
        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class, $fileResponse);
        $this->assertSame(
            file_get_contents($path),
            file_get_contents($fileResponse->getFile()->getPathname()),
        );
    }

    public function test_import_file_download_missing_file_returns_not_found(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $import = TariffImport::factory()->create([
            'uploaded_by_user_id' => $user->id,
            'original_filename' => 'gone.xlsx',
            'stored_path' => 'tariff-imports/missing-file.xlsx',
        ]);

        $this->getJson($this->adminApiUrl('imports/' . $import->id . '/download'))
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found');
    }

    public function test_no_self_registration_endpoint(): void
    {
        $this->postJson($this->adminApiUrl('register'), [
            'email' => 'new@atc.form',
            'password' => 'password',
        ])->assertNotFound();
    }
}
