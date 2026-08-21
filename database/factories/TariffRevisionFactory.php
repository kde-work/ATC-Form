<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RevisionStatus;
use App\Models\TariffImport;
use App\Models\TariffRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TariffRevision>
 */
class TariffRevisionFactory extends Factory
{
    protected $model = TariffRevision::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tariff_import_id' => TariffImport::factory(),
            'version_number' => fake()->unique()->numberBetween(1, 1_000_000),
            'status' => RevisionStatus::Draft,
            'activated_by_user_id' => null,
            'activated_at' => null,
            'archived_at' => null,
            'metadata_json' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => [
            'status' => RevisionStatus::Draft,
            'activated_at' => null,
            'archived_at' => null,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => RevisionStatus::Active,
            'activated_at' => now(),
            'archived_at' => null,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (): array => [
            'status' => RevisionStatus::Archived,
            'activated_at' => now()->subDay(),
            'archived_at' => now(),
        ]);
    }
}
