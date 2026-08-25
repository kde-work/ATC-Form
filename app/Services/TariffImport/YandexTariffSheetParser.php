<?php

declare(strict_types=1);

namespace App\Services\TariffImport;

use App\Dto\TariffImport\ImportErrorDraft;
use App\Dto\TariffImport\ParsedChannelRow;
use App\Enums\Platform;
use App\Support\CellValueNormalizer;
use App\Support\ChannelCode;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Парсинг секции Yandex Market (Super Express / Express).
 */
final class YandexTariffSheetParser
{
    public const REQUIRED_CODES = [
        'super-express',
        'express',
    ];

    private const DEFAULT_INCREMENT_GRAMS = 100;

    /**
     * @return array{channels: list<ParsedChannelRow>, errors: list<ImportErrorDraft>}
     */
    public function parse(Worksheet $sheet): array
    {
        $sheetName = $sheet->getTitle();
        $rows = $sheet->toArray(null, false, false, false);
        $sectionStart = $this->findSectionStart($rows);

        if ($sectionStart === null) {
            return [
                'channels' => [],
                'errors' => [
                    new ImportErrorDraft(
                        message: 'Workbook: Yandex Market section not found.',
                        sheetName: $sheetName,
                        field: 'workbook',
                        errorCode: 'yandex_section_missing',
                    ),
                ],
            ];
        }

        // Сначала таблица "Параметр | Super Express | Express" (исходник заказчика).
        $table = $this->parseParameterTable($rows, $sectionStart, $sheetName);
        $channels = $table['channels'];
        $errors = $table['errors'];

        if (count($channels) >= 2) {
            return [
                'channels' => $channels,
                'errors' => $errors,
            ];
        }

        $present = [];
        foreach ($channels as $channel) {
            $present[$channel->code] = true;
        }

        for ($i = $sectionStart, $count = count($rows); $i < $count; $i++) {
            $row = $rows[$i] ?? null;
            if (! is_array($row) || $this->isEmptyRow($row)) {
                continue;
            }

            $excelRow = $i + 1;
            $nameCell = CellValueNormalizer::toString($row[0] ?? null);
            $englishName = $this->extractEnglishChannelName($nameCell);
            if ($englishName === null) {
                if (count($channels) >= 2) {
                    break;
                }

                continue;
            }

            $code = ChannelCode::fromName($englishName);
            if (! in_array($code, self::REQUIRED_CODES, true) || isset($present[$code])) {
                continue;
            }

            $rateRaw = $this->findRateCell($row);

            try {
                $rate = CellValueNormalizer::parseYandexRate($rateRaw);
            } catch (InvalidArgumentException) {
                $errors[] = new ImportErrorDraft(
                    message: "Yandex Market section: {$englishName} tariff rate cannot be parsed.",
                    sheetName: $sheetName,
                    rowNumber: $excelRow,
                    field: 'per_kg_fee',
                    errorCode: 'yandex_rate_parse',
                );

                continue;
            }

            $fixedFee = $rate['fixed_fee'];
            $perKgFee = $rate['per_kg_fee'];

            $channels[] = $this->makeChannel(
                $englishName,
                $code,
                $fixedFee,
                $perKgFee,
                $excelRow,
                $sheetName,
                [
                    'name_raw' => $nameCell,
                    'rate' => CellValueNormalizer::toString($rateRaw),
                ],
            );
            $present[$code] = true;
        }

        return [
            'channels' => $channels,
            'errors' => $errors,
        ];
    }

    /**
     * @param list<list<mixed>|null> $rows
     * @return array{channels: list<ParsedChannelRow>, errors: list<ImportErrorDraft>}
     */
    private function parseParameterTable(array $rows, int $sectionStart, string $sheetName): array
    {
        $columnByCode = [];
        $headerIndex = null;

        for ($i = $sectionStart, $count = count($rows); $i < $count; $i++) {
            $row = $rows[$i] ?? null;
            if (! is_array($row)) {
                continue;
            }

            $found = [];
            foreach ($row as $col => $cell) {
                $englishName = $this->extractEnglishChannelName(CellValueNormalizer::toString($cell));
                if ($englishName === null) {
                    continue;
                }

                $code = ChannelCode::fromName($englishName);
                if (in_array($code, self::REQUIRED_CODES, true)) {
                    $found[$code] = ['col' => $col, 'name' => $englishName];
                }
            }

            if (isset($found['super-express'], $found['express'])) {
                $columnByCode = $found;
                $headerIndex = $i;
                break;
            }
        }

        if ($headerIndex === null) {
            return ['channels' => [], 'errors' => []];
        }

        $perKg = [];
        $fixed = [];
        $sourceRow = $headerIndex + 1;
        $raw = [];

        $last = min(count($rows), $headerIndex + 25);
        for ($i = $headerIndex + 1; $i < $last; $i++) {
            $row = $rows[$i] ?? null;
            if (! is_array($row) || $this->isEmptyRow($row)) {
                continue;
            }

            $label = mb_strtolower(CellValueNormalizer::toString($row[0] ?? null), 'UTF-8');
            $joined = mb_strtolower(implode(' ', array_map(
                static fn (mixed $cell): string => CellValueNormalizer::toString($cell),
                $row,
            )), 'UTF-8');

            if (str_contains($joined, 'окрвверх') || str_contains($joined, 'ceil(')) {
                continue;
            }

            $rowLooksPerKg = str_contains($label, 'кг') || str_contains($label, '/kg') || str_contains($label, 'per kg');
            $rowLooksFixed = str_contains($label, 'фиксир') || str_contains($label, '/шт') || str_contains($label, 'fixed');

            foreach ($columnByCode as $code => $meta) {
                $cell = $row[$meta['col']] ?? null;
                $text = CellValueNormalizer::toString($cell);
                if ($text === '' || str_starts_with($text, '=')) {
                    continue;
                }

                $lower = mb_strtolower($text, 'UTF-8');
                $amount = CellValueNormalizer::parseLeadingDecimal($cell);
                if ($amount === null) {
                    continue;
                }

                if (str_contains($lower, '/кг') || str_contains($lower, '/kg') || $rowLooksPerKg) {
                    $perKg[$code] = $amount;
                    $raw[$code]['per_kg'] = $text;
                    $sourceRow = $i + 1;
                } elseif (str_contains($lower, '/шт') || $rowLooksFixed) {
                    $fixed[$code] = $amount;
                    $raw[$code]['fixed'] = $text;
                    $sourceRow = $i + 1;
                }
            }
        }

        $channels = [];
        $errors = [];

        foreach ($columnByCode as $code => $meta) {
            $name = $meta['name'];
            if (! isset($fixed[$code], $perKg[$code])) {
                $errors[] = new ImportErrorDraft(
                    message: "Yandex Market section: {$name} tariff rate cannot be parsed.",
                    sheetName: $sheetName,
                    rowNumber: $sourceRow,
                    field: 'per_kg_fee',
                    errorCode: 'yandex_rate_parse',
                );

                continue;
            }

            $channels[] = $this->makeChannel(
                $name,
                $code,
                $fixed[$code],
                $perKg[$code],
                $sourceRow,
                $sheetName,
                $raw[$code] ?? [],
            );
        }

        return [
            'channels' => $channels,
            'errors' => $errors,
        ];
    }

    /**
     * @param numeric-string $fixedFee
     * @param numeric-string $perKgFee
     * @param array<string, mixed> $rawData
     */
    private function makeChannel(
        string $name,
        string $code,
        string $fixedFee,
        string $perKgFee,
        int $excelRow,
        string $sheetName,
        array $rawData,
    ): ParsedChannelRow {
        return new ParsedChannelRow(
            platform: Platform::YandexMarket,
            code: $code,
            name: $name,
            currency: 'RUB',
            fixedFee: $fixedFee,
            perGramFee: null,
            perKgFee: $perKgFee,
            billingIncrementGrams: self::DEFAULT_INCREMENT_GRAMS,
            chargeableWeightType: null,
            volumetricDivisor: null,
            minWeightGrams: null,
            maxWeightGrams: null,
            maxLengthCm: null,
            maxSumDimensionsCm: null,
            minOrderCostRub: null,
            maxOrderCostRub: null,
            minOrderCostCny: null,
            maxOrderCostCny: null,
            importSourceRow: $excelRow,
            sheetName: $sheetName,
            rawData: $rawData,
        );
    }

    /**
     * Есть ли на листе секция Yandex (для выбора листа книги).
     *
     * @param list<list<mixed>|null> $rows
     */
    public function sheetContainsYandexSection(array $rows): bool
    {
        return $this->findSectionStart($rows) !== null;
    }

    /**
     * На листе есть оба канала YM (не только упоминание в описании).
     *
     * @param list<list<mixed>|null> $rows
     */
    public function sheetHasRequiredChannels(array $rows): bool
    {
        $found = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            foreach ($row as $cell) {
                $englishName = $this->extractEnglishChannelName(CellValueNormalizer::toString($cell));
                if ($englishName === null) {
                    continue;
                }

                $code = ChannelCode::fromName($englishName);
                if (in_array($code, self::REQUIRED_CODES, true)) {
                    $found[$code] = true;
                }
            }

            if (isset($found['super-express'], $found['express'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<list<mixed>|null> $rows
     */
    private function findSectionStart(array $rows): ?int
    {
        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $joined = mb_strtolower(implode(' ', array_map(
                static fn (mixed $cell): string => CellValueNormalizer::toString($cell),
                $row,
            )), 'UTF-8');

            if (
                str_contains($joined, 'yandex market')
                || str_contains($joined, 'яндекс')
                || str_contains($joined, 'yandex')
            ) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param list<mixed>|null $row
     */
    private function isEmptyRow(?array $row): bool
    {
        if ($row === null) {
            return true;
        }

        foreach ($row as $cell) {
            if (CellValueNormalizer::toString($cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function extractEnglishChannelName(string $cell): ?string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($cell)) ?? trim($cell);
        if ($normalized === '') {
            return null;
        }

        if (preg_match('/super\s*express/iu', $normalized) === 1) {
            return 'Super Express';
        }

        if (preg_match('/\bexpress\b/iu', $normalized) === 1) {
            return 'Express';
        }

        return null;
    }

    /**
     * @param list<mixed> $row
     */
    private function findRateCell(array $row): mixed
    {
        foreach ($row as $index => $cell) {
            if ($index === 0) {
                continue;
            }

            $text = CellValueNormalizer::toString($cell);
            if ($text === '') {
                continue;
            }

            if (str_contains(mb_strtolower($text, 'UTF-8'), '/kg') || str_contains($text, '+')) {
                return $cell;
            }
        }

        return $row[1] ?? null;
    }
}
