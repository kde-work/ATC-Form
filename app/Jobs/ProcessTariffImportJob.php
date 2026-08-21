<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\TariffImport;
use App\Services\TariffImport\TariffImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Обработка загруженного XLSX. При QUEUE_CONNECTION=sync выполняется сразу.
 */
class ProcessTariffImportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $tariffImportId,
    ) {
    }

    public function handle(TariffImportService $tariffImportService): void
    {
        $import = TariffImport::query()->find($this->tariffImportId);
        if ($import === null) {
            return;
        }

        $tariffImportService->process($import);
    }
}
