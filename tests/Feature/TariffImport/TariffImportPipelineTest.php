<?php

declare(strict_types=1);

namespace Tests\Feature\TariffImport;

use App\Enums\ImportStatus;
use App\Enums\Platform;
use App\Enums\RevisionStatus;
use App\Enums\WeightCalculationType;
use App\Exceptions\TariffRevisionActivationException;
use App\Models\TariffRevision;
use App\Models\User;
use App\Services\ActiveTariffQuery;
use App\Services\TariffImport\TariffImportService;
use App\Services\TariffImport\TariffRevisionActivationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Fixtures\TariffsXlsxFixtureBuilder;
use Tests\TestCase;

/**
 * Импорт XLSX, preview, activate и rollback.
 */
final class TariffImportPipelineTest extends TestCase
{
    use RefreshDatabase;

    private string $fixturesDir;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->fixturesDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'atc-xlsx-fixtures-' . uniqid('', true);
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

    public function test_valid_xlsx_creates_draft_revision_with_summary(): void
    {
        $path = $this->fixturesDir . DIRECTORY_SEPARATOR . 'valid-tariffs.xlsx';
        TariffsXlsxFixtureBuilder::write($path);

        $user = User::factory()->create();
        $import = $this->service()->uploadAndProcess(
            new UploadedFile($path, 'valid-tariffs.xlsx', null, null, true),
            $user,
        );

        $this->assertSame(ImportStatus::Validated, $import->status);
        $this->assertNotNull($import->file_hash);
        $this->assertSame('valid-tariffs.xlsx', $import->original_filename);
        $this->assertGreaterThan(0, $import->file_size_bytes);
        $this->assertStringStartsWith('tariff-imports/', $import->stored_path);

        $revision = $import->revision;
        $this->assertNotNull($revision);
        $this->assertSame(RevisionStatus::Draft, $revision->status);
        $this->assertSame(17, $revision->channels()->count());

        $big = $revision->channels()
            ->where('platform', Platform::Ozon)
            ->where('code', 'atc-standard-big')
            ->first();
        $this->assertNotNull($big);
        $this->assertSame(WeightCalculationType::MaxPhysicalOrVolumetric, $big->chargeable_weight_type);
        $this->assertSame('12000.0000', (string) $big->volumetric_divisor);

        $summary = $import->summary_json;
        $this->assertIsArray($summary);
        $this->assertSame(17, $summary['total']);
        $this->assertSame(17, $summary['added']);
        $this->assertSame(0, $summary['changed']);
        $this->assertSame(0, $summary['removed']);
    }

    public function test_duplicate_code_fails_validation(): void
    {
        $path = $this->fixturesDir . DIRECTORY_SEPARATOR . 'invalid-duplicate-code.xlsx';
        $rows = TariffsXlsxFixtureBuilder::defaultOzonRows();
        $rows[] = $rows[1]; // duplicate Standard Extra Small
        TariffsXlsxFixtureBuilder::write($path, $rows);

        $import = $this->service()->uploadAndProcess(
            new UploadedFile($path, 'invalid-duplicate-code.xlsx', null, null, true),
            User::factory()->create(),
        );

        $this->assertSame(ImportStatus::ValidationFailed, $import->status);
        $this->assertSame(RevisionStatus::Invalid, $import->revision?->status);
        $error = $import->errors()->where('field', 'code')->where('error_code', 'duplicate_code')->first();
        $this->assertNotNull($error);
        $this->assertNotNull($error->sheet_name);
        $this->assertNotNull($error->row_number);
        $this->assertStringContainsString('duplicate channel code', $error->message);
    }

    public function test_calculator_sees_only_active_revision_after_activation(): void
    {
        $user = User::factory()->create();
        $service = $this->service();
        $activation = $this->app->make(TariffRevisionActivationService::class);
        $activeQuery = $this->app->make(ActiveTariffQuery::class);

        $firstPath = $this->fixturesDir . DIRECTORY_SEPARATOR . 'calc-first.xlsx';
        TariffsXlsxFixtureBuilder::write($firstPath);
        $firstImport = $service->uploadAndProcess(
            new UploadedFile($firstPath, 'calc-first.xlsx', null, null, true),
            $user,
        );
        $activation->activate($firstImport, $user);

        $before = $activeQuery->findActiveChannel(Platform::Ozon, 'atc-standard-extra-small');
        $this->assertNotNull($before);
        $this->assertSame('3.370000', (string) $before->fixed_fee);

        $secondRows = TariffsXlsxFixtureBuilder::defaultOzonRows();
        $secondRows[1]['rate'] = '¥ 9.99 + ¥ 0.0111/1 g';
        $secondPath = $this->fixturesDir . DIRECTORY_SEPARATOR . 'calc-second.xlsx';
        TariffsXlsxFixtureBuilder::write($secondPath, $secondRows);
        $secondImport = $service->uploadAndProcess(
            new UploadedFile($secondPath, 'calc-second.xlsx', null, null, true),
            $user,
        );

        // Draft ещё не active: калькулятор видит старые ставки
        $stillOld = $activeQuery->findActiveChannel(Platform::Ozon, 'atc-standard-extra-small');
        $this->assertNotNull($stillOld);
        $this->assertSame('3.370000', (string) $stillOld->fixed_fee);
        $this->assertSame(ImportStatus::Validated, $secondImport->status);

        $activation->activate($secondImport, $user);
        $updated = $activeQuery->findActiveChannel(Platform::Ozon, 'atc-standard-extra-small');
        $this->assertNotNull($updated);
        $this->assertSame('9.990000', (string) $updated->fixed_fee);
    }

    public function test_broken_rate_fails_validation_with_row(): void
    {
        $path = $this->fixturesDir . DIRECTORY_SEPARATOR . 'invalid-broken-rate.xlsx';
        $rows = TariffsXlsxFixtureBuilder::defaultOzonRows();
        $rows[1]['rate'] = 'broken rate value';
        TariffsXlsxFixtureBuilder::write($path, $rows);

        $import = $this->service()->uploadAndProcess(
            new UploadedFile($path, 'invalid-broken-rate.xlsx', null, null, true),
            User::factory()->create(),
        );

        $this->assertSame(ImportStatus::ValidationFailed, $import->status);
        $error = $import->errors()->where('field', 'per_gram_fee')->first();
        $this->assertNotNull($error);
        $this->assertNotNull($error->row_number);
        $this->assertNotNull($error->sheet_name);
        $this->assertStringContainsString('cannot parse per-gram rate', $error->message);
    }

    public function test_activate_and_rollback_in_transaction(): void
    {
        $user = User::factory()->create();
        $service = $this->service();
        $activation = $this->app->make(TariffRevisionActivationService::class);

        $firstPath = $this->fixturesDir . DIRECTORY_SEPARATOR . 'first.xlsx';
        TariffsXlsxFixtureBuilder::write($firstPath);
        $firstImport = $service->uploadAndProcess(
            new UploadedFile($firstPath, 'first.xlsx', null, null, true),
            $user,
        );
        $firstRevision = $activation->activate($firstImport, $user);
        $this->assertSame(RevisionStatus::Active, $firstRevision->status);
        $this->assertSame(ImportStatus::Activated, $firstImport->refresh()->status);

        $secondRows = TariffsXlsxFixtureBuilder::defaultOzonRows();
        $secondRows[1]['rate'] = '¥ 3.99 + ¥ 0.0510/1 g';
        $secondPath = $this->fixturesDir . DIRECTORY_SEPARATOR . 'second.xlsx';
        TariffsXlsxFixtureBuilder::write($secondPath, $secondRows);
        $secondImport = $service->uploadAndProcess(
            new UploadedFile($secondPath, 'second.xlsx', null, null, true),
            $user,
        );

        $this->assertSame(ImportStatus::Validated, $secondImport->status);
        $summary = $secondImport->summary_json;
        $this->assertSame(1, $summary['changed']);
        $this->assertSame(0, $summary['added']);
        $this->assertSame(0, $summary['removed']);

        $secondRevision = $activation->activate($secondImport, $user);
        $this->assertSame(RevisionStatus::Active, $secondRevision->fresh()->status);
        $this->assertSame(RevisionStatus::Archived, $firstRevision->fresh()->status);
        $this->assertSame(
            1,
            TariffRevision::query()->where('status', RevisionStatus::Active)->count(),
        );

        $activeQuery = $this->app->make(ActiveTariffQuery::class);
        $channel = $activeQuery->findActiveChannel(Platform::Ozon, 'atc-standard-extra-small');
        $this->assertNotNull($channel);
        $this->assertSame('3.990000', (string) $channel->fixed_fee);

        $rolled = $activation->rollback($firstRevision->fresh(), $user);
        $this->assertSame(RevisionStatus::Active, $rolled->status);
        $this->assertSame(RevisionStatus::Archived, $secondRevision->fresh()->status);

        $restored = $activeQuery->findActiveChannel(Platform::Ozon, 'atc-standard-extra-small');
        $this->assertNotNull($restored);
        $this->assertSame('3.370000', (string) $restored->fixed_fee);
    }

    public function test_cannot_activate_validation_failed_import(): void
    {
        $path = $this->fixturesDir . DIRECTORY_SEPARATOR . 'invalid-broken-rate.xlsx';
        $rows = TariffsXlsxFixtureBuilder::defaultOzonRows();
        $rows[0]['rate'] = 'bad';
        TariffsXlsxFixtureBuilder::write($path, $rows);

        $user = User::factory()->create();
        $import = $this->service()->uploadAndProcess(
            new UploadedFile($path, 'bad.xlsx', null, null, true),
            $user,
        );

        $this->expectException(TariffRevisionActivationException::class);
        $this->app->make(TariffRevisionActivationService::class)->activate($import, $user);
    }

    public function test_rejects_non_xlsx_extension(): void
    {
        $user = User::factory()->create();
        $txt = $this->fixturesDir . DIRECTORY_SEPARATOR . 'notes.txt';
        file_put_contents($txt, 'not xlsx');

        $this->expectException(\App\Exceptions\TariffImportFileException::class);
        $this->service()->uploadAndProcess(
            new UploadedFile($txt, 'notes.txt', null, null, true),
            $user,
        );
    }

    private function service(): TariffImportService
    {
        return $this->app->make(TariffImportService::class);
    }
}
