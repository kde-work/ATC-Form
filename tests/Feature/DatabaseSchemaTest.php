<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\ImportStatus;
use App\Enums\Platform;
use App\Enums\RevisionStatus;
use App\Enums\WeightCalculationType;
use App\Models\AppSetting;
use App\Models\DeliveryChannel;
use App\Models\TariffImport;
use App\Models\TariffRevision;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Проверяет накат схемы, seed и factories доменных сущностей.
 */
final class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_creates_admin_and_exchange_rate(): void
    {
        config([
            'atc.admin_email' => 'admin@atc.form',
            'atc.admin_password' => 'secret-password',
        ]);

        $this->seed();

        $this->assertDatabaseHas('users', [
            'email' => 'admin@atc.form',
            'name' => 'Admin',
        ]);

        $this->assertDatabaseHas('app_settings', [
            'key' => AppSetting::KEY_RUB_TO_CNY_RATE,
            'value' => AppSetting::DEFAULT_RUB_TO_CNY_RATE,
        ]);
    }

    public function test_factories_create_import_revision_and_channel(): void
    {
        $user = User::factory()->create();
        $import = TariffImport::factory()->validated()->for($user, 'uploadedBy')->create();
        $revision = TariffRevision::factory()->active()->for($import, 'import')->create([
            'version_number' => 1,
        ]);
        $channel = DeliveryChannel::factory()->ozonBig()->for($revision, 'revision')->create([
            'code' => 'atc-standard-big',
            'name' => 'ATC Standard Big',
        ]);

        self::assertSame(ImportStatus::Validated, $import->status);
        self::assertSame(RevisionStatus::Active, $revision->fresh()->status);
        self::assertSame(Platform::Ozon, $channel->platform);
        self::assertSame(WeightCalculationType::MaxPhysicalOrVolumetric, $channel->chargeable_weight_type);
        self::assertSame(1, $revision->fresh()->active_guard);
    }

    public function test_only_one_active_revision_guard(): void
    {
        $importA = TariffImport::factory()->validated()->create();
        $importB = TariffImport::factory()->validated()->create();

        TariffRevision::factory()->active()->for($importA, 'import')->create([
            'version_number' => 1,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        TariffRevision::factory()->active()->for($importB, 'import')->create([
            'version_number' => 2,
        ]);
    }
}
