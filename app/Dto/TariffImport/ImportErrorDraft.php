<?php

declare(strict_types=1);

namespace App\Dto\TariffImport;

/**
 * Черновик ошибки импорта до записи в tariff_import_errors.
 */
final readonly class ImportErrorDraft
{
    /**
     * @param array<string, mixed>|null $context
     */
    public function __construct(
        public string $message,
        public ?string $sheetName = null,
        public ?int $rowNumber = null,
        public ?string $field = null,
        public ?string $errorCode = null,
        public ?array $context = null,
    ) {
    }
}
