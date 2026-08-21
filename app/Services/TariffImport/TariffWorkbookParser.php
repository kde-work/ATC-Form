<?php

declare(strict_types=1);

namespace App\Services\TariffImport;

use App\Dto\TariffImport\ImportErrorDraft;
use App\Dto\TariffImport\WorkbookParseResult;
use App\Exceptions\TariffImportFileException;
use App\Support\CellValueNormalizer;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ReaderException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Разбор XLSX: поиск листов Ozon/Yandex и делегирование sheet-парсерам.
 */
final class TariffWorkbookParser
{
    public function __construct(
        private readonly OzonTariffSheetParser $ozonTariffSheetParser,
        private readonly YandexTariffSheetParser $yandexTariffSheetParser,
    ) {
    }

    public function parseFile(string $absolutePath): WorkbookParseResult
    {
        $spreadsheet = $this->loadSpreadsheet($absolutePath);

        try {
            return $this->parseSpreadsheet($spreadsheet);
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    public function parseSpreadsheet(Spreadsheet $spreadsheet): WorkbookParseResult
    {
        $channels = [];
        $errors = [];

        $ozonSheet = $this->findOzonSheet($spreadsheet);
        if ($ozonSheet === null) {
            $errors[] = new ImportErrorDraft(
                message: 'Workbook: Ozon sheet not found.',
                field: 'workbook',
                errorCode: 'ozon_sheet_missing',
            );
        } else {
            $ozonResult = $this->ozonTariffSheetParser->parse($ozonSheet);
            $channels = array_merge($channels, $ozonResult['channels']);
            $errors = array_merge($errors, $ozonResult['errors']);
        }

        $yandexSheet = $this->findYandexSheet($spreadsheet);
        if ($yandexSheet === null) {
            $errors[] = new ImportErrorDraft(
                message: 'Workbook: Yandex Market section not found.',
                field: 'workbook',
                errorCode: 'yandex_section_missing',
            );
        } else {
            $yandexResult = $this->yandexTariffSheetParser->parse($yandexSheet);
            $channels = array_merge($channels, $yandexResult['channels']);
            $errors = array_merge($errors, $yandexResult['errors']);
        }

        return new WorkbookParseResult($channels, $errors);
    }

    private function loadSpreadsheet(string $absolutePath): Spreadsheet
    {
        if (! is_file($absolutePath)) {
            throw new TariffImportFileException('Import file does not exist.');
        }

        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $reader->setReadEmptyCells(false);

            return $reader->load($absolutePath);
        } catch (ReaderException|SpreadsheetException $exception) {
            throw new TariffImportFileException(
                'Unable to open XLSX workbook.',
                0,
                $exception,
            );
        } catch (Throwable $exception) {
            throw new TariffImportFileException(
                'Unable to open XLSX workbook.',
                0,
                $exception,
            );
        }
    }

    private function findOzonSheet(Spreadsheet $spreadsheet): ?Worksheet
    {
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $normalized = CellValueNormalizer::normalizeSheetName($sheet->getTitle());
            if (str_contains($normalized, 'ozon')) {
                return $sheet;
            }
        }

        return null;
    }

    private function findYandexSheet(Spreadsheet $spreadsheet): ?Worksheet
    {
        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $normalized = CellValueNormalizer::normalizeSheetName($sheet->getTitle());
            if (str_contains($normalized, 'yandex') || str_contains($normalized, 'яндекс')) {
                return $sheet;
            }
        }

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $rows = $sheet->toArray(null, false, false, false);
            if ($this->yandexTariffSheetParser->sheetContainsYandexSection($rows)) {
                return $sheet;
            }
        }

        return null;
    }
}
