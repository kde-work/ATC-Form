<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ImportStatus;
use App\Models\TariffImport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TariffImport>
 */
class TariffImportFactory extends Factory
{
    protected $model = TariffImport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $filename = fake()->lexify('tariffs-????.xlsx');

        return [
            'uploaded_by_user_id' => User::factory(),
            'original_filename' => $filename,
            'stored_path' => 'tariff-imports/' . Str::uuid()->toString() . '.xlsx',
            'file_hash' => hash('sha256', fake()->uuid()),
            'file_size_bytes' => fake()->numberBetween(1024, 500_000),
            'status' => ImportStatus::Uploaded,
            'summary_json' => null,
            'started_at' => null,
            'finished_at' => null,
        ];
    }

    public function validated(): static
    {
        return $this->state(fn (): array => [
            'status' => ImportStatus::Validated,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'summary_json' => [
                'total' => 2,
                'added' => 2,
                'changed' => 0,
                'removed' => 0,
            ],
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (): array => [
            'status' => ImportStatus::Processing,
            'started_at' => now(),
        ]);
    }
}
