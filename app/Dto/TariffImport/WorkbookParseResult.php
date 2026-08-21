<?php

declare(strict_types=1);

namespace App\Dto\TariffImport;

/**
 * Результат разбора книги: каналы и ошибки парсинга отдельных строк.
 */
final readonly class WorkbookParseResult
{
    /**
     * @param list<ParsedChannelRow> $channels
     * @param list<ImportErrorDraft> $errors
     */
    public function __construct(
        public array $channels,
        public array $errors,
    ) {
    }
}
