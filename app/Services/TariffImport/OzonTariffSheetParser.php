<?php

declare(strict_types=1);

namespace App\Services\TariffImport;

use App\Dto\TariffImport\ImportErrorDraft;
use App\Dto\TariffImport\ParsedChannelRow;
use App\Enums\Platform;
use App\Enums\WeightCalculationType;
use App\Support\CellValueNormalizer;
use App\Support\ChannelCode;
use App\Support\Decimal;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Парсинг листа тарифов Ozon по значениям ячеек.
 */
final class OzonTariffSheetParser
{
    /**
     * Ожидаемые имена каналов для валидного импорта (ставки только из файла).
     *
     * @var list<string>
     */
    public const REQUIRED_CHANNEL_NAMES = [
        'ATC Express Extra Small',
        'ATC Standard Extra Small',
        'ATC Economy Extra Small',
        'ATC Standard Budget',
        'ATC Economy Budget',
        'ATC Express Small',
        'ATC Standard Small',
        'ATC Economy Small',
        'ATC Standard Big',
        'ATC Economy Big',
        'ATC Express Premium Small',
        'ATC Standard Premium Small',
        'ATC Economy Premium Small',
        'ATC Standard Premium Big',
        'ATC Economy Premium Big',
    ];

    private const DEFAULT_BIG_DIVISOR = '12000';

    /**
     * @return array{channels: list<ParsedChannelRow>, errors: list<ImportErrorDraft>}
     */
    public function parse(Worksheet $sheet): array
    {
        $sheetName = $sheet->getTitle();
        $rows = $sheet->toArray(null, false, false, false);
        $headerIndex = $this->findHeaderRowIndex($rows);

        if ($headerIndex === null) {
            return [
                'channels' => [],
                'errors' => [
                    new ImportErrorDraft(
                        message: "Sheet '{$sheetName}': header row not found.",
                        sheetName: $sheetName,
                        field: 'workbook',
                        errorCode: 'ozon_header_missing',
                    ),
                ],
            ];
        }

        $columnMap = $this->mapColumns($rows[$headerIndex]);
        if (! isset($columnMap['name'], $columnMap['rate'])) {
            return [
                'channels' => [],
                'errors' => [
                    new ImportErrorDraft(
                        message: "Sheet '{$sheetName}': required columns name/rate not found.",
                        sheetName: $sheetName,
                        field: 'workbook',
                        errorCode: 'ozon_columns_missing',
                    ),
                ],
            ];
        }

        $channels = [];
        $errors = [];

        for ($i = $headerIndex + 1, $count = count($rows); $i < $count; $i++) {
            $row = $rows[$i];
            $excelRow = $i + 1;

            if ($this->isEmptyRow($row)) {
                continue;
            }

            if ($this->looksLikeHeaderRow($row)) {
                continue;
            }

            $parsed = $this->parseDataRow($row, $columnMap, $sheetName, $excelRow, $errors);
            if ($parsed !== null) {
                $channels[] = $parsed;
            }
        }

        return [
            'channels' => $channels,
            'errors' => $errors,
        ];
    }

    /**
     * @param list<list<mixed>|null> $rows
     */
    private function findHeaderRowIndex(array $rows): ?int
    {
        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            if ($this->looksLikeHeaderRow($row)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param list<mixed> $row
     * @return array<string, int>
     */
    private function mapColumns(array $row): array
    {
        $map = [];

        foreach ($row as $index => $cell) {
            $header = CellValueNormalizer::normalizeHeader(CellValueNormalizer::toString($cell));
            if ($header === '') {
                continue;
            }

            if ($this->headerMatches($header, ['channel', 'name', 'канал', 'имя'])) {
                $map['name'] = $index;
            } elseif ($this->headerMatches($header, ['weight', 'вес'])) {
                $map['weight'] = $index;
            } elseif ($this->headerMatches($header, ['rate', 'тариф', 'fee'])) {
                $map['rate'] = $index;
            } elseif ($this->headerMatches($header, ['limits', 'limit', 'ограничен'])) {
                $map['limits'] = $index;
            } elseif (
                $this->headerMatches($header, ['order cny', 'order_cost_cny', 'cost cny'])
                || (str_contains($header, 'order') && str_contains($header, 'cny'))
            ) {
                $map['order_cny'] = $index;
            } elseif (
                $this->headerMatches($header, ['order rub', 'order_cost_rub', 'cost rub'])
                || (str_contains($header, 'order') && str_contains($header, 'rub'))
            ) {
                $map['order_rub'] = $index;
            } elseif ($this->headerMatches($header, ['currency', 'валюта'])) {
                $map['currency'] = $index;
            } elseif ($this->headerMatches($header, ['divisor', 'volumetric'])) {
                $map['divisor'] = $index;
            }
        }

        return $map;
    }

    /**
     * @param list<string> $needles
     */
    private function headerMatches(string $header, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($header === $needle || str_contains($header, $needle)) {
                return true;
            }
        }

        return false;
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

    /**
     * @param list<mixed> $row
     */
    private function looksLikeHeaderRow(array $row): bool
    {
        $joined = CellValueNormalizer::normalizeHeader(implode(' ', array_map(
            static fn (mixed $cell): string => CellValueNormalizer::toString($cell),
            $row,
        )));

        if ($joined === '') {
            return false;
        }

        $keywords = ['name', 'канал', 'weight', 'вес', 'rate', 'тариф', 'limit', 'channel'];
        $hits = 0;
        foreach ($keywords as $keyword) {
            if (str_contains($joined, $keyword)) {
                $hits++;
            }
        }

        return $hits >= 2;
    }

    /**
     * @param list<mixed> $row
     * @param array<string, int> $columnMap
     * @param list<ImportErrorDraft> $errors
     */
    private function parseDataRow(
        array $row,
        array $columnMap,
        string $sheetName,
        int $excelRow,
        array &$errors,
    ): ?ParsedChannelRow {
        $name = CellValueNormalizer::toString($row[$columnMap['name']] ?? null);
        if ($name === '') {
            $errors[] = new ImportErrorDraft(
                message: "Sheet '{$sheetName}', row {$excelRow}: channel name is empty.",
                sheetName: $sheetName,
                rowNumber: $excelRow,
                field: 'name',
                errorCode: 'empty_name',
            );

            return null;
        }

        $code = ChannelCode::fromName($name);
        $isBig = str_contains($code, 'big');

        $rateRaw = $row[$columnMap['rate']] ?? null;

        try {
            $rate = CellValueNormalizer::parseOzonRate($rateRaw);
        } catch (InvalidArgumentException) {
            $errors[] = new ImportErrorDraft(
                message: "Sheet '{$sheetName}', row {$excelRow}: cannot parse per-gram rate.",
                sheetName: $sheetName,
                rowNumber: $excelRow,
                field: 'per_gram_fee',
                errorCode: 'ozon_rate_parse',
            );

            return null;
        }

        $fixedFee = $rate['fixed_fee'];
        $perGramFee = $rate['per_gram_fee'];

        $minWeight = null;
        $maxWeight = null;
        if (isset($columnMap['weight'])) {
            $range = CellValueNormalizer::parseRange($row[$columnMap['weight']] ?? null);
            if ($range === null && CellValueNormalizer::toString($row[$columnMap['weight']] ?? null) !== '') {
                $errors[] = new ImportErrorDraft(
                    message: "Sheet '{$sheetName}', row {$excelRow}: cannot parse weight range.",
                    sheetName: $sheetName,
                    rowNumber: $excelRow,
                    field: 'min_weight_grams',
                    errorCode: 'weight_range_parse',
                );
            } elseif ($range !== null) {
                [$minWeight, $maxWeight] = $range;
            }
        }

        $maxLength = null;
        $maxSum = null;
        if (isset($columnMap['limits'])) {
            $limits = CellValueNormalizer::parseDimensionLimits($row[$columnMap['limits']] ?? null);
            $maxLength = $limits['max_length_cm'];
            $maxSum = $limits['max_sum_dimensions_cm'];
        }

        $minOrderCny = null;
        $maxOrderCny = null;
        if (isset($columnMap['order_cny'])) {
            $orderCny = CellValueNormalizer::parseRange($row[$columnMap['order_cny']] ?? null);
            if ($orderCny !== null) {
                [$minOrderCny, $maxOrderCny] = $orderCny;
            }
        }

        $minOrderRub = null;
        $maxOrderRub = null;
        if (isset($columnMap['order_rub'])) {
            $orderRub = CellValueNormalizer::parseRange($row[$columnMap['order_rub']] ?? null);
            if ($orderRub !== null) {
                [$minOrderRub, $maxOrderRub] = $orderRub;
            }
        }

        $currency = 'CNY';
        if (isset($columnMap['currency'])) {
            $currencyRaw = strtoupper(CellValueNormalizer::toString($row[$columnMap['currency']] ?? null));
            if ($currencyRaw !== '') {
                $currency = $currencyRaw;
            }
        }

        $divisor = null;
        if (isset($columnMap['divisor'])) {
            $divisor = CellValueNormalizer::parseDecimal($row[$columnMap['divisor']] ?? null);
        }

        $weightType = WeightCalculationType::Physical;
        if ($isBig) {
            $weightType = WeightCalculationType::MaxPhysicalOrVolumetric;
            if ($divisor === null) {
                $divisor = Decimal::normalize(self::DEFAULT_BIG_DIVISOR, 4);
            }
        }

        return new ParsedChannelRow(
            platform: Platform::Ozon,
            code: $code,
            name: $name,
            currency: $currency,
            fixedFee: $fixedFee,
            perGramFee: $perGramFee,
            perKgFee: null,
            billingIncrementGrams: null,
            chargeableWeightType: $weightType,
            volumetricDivisor: $divisor,
            minWeightGrams: $minWeight,
            maxWeightGrams: $maxWeight,
            maxLengthCm: $maxLength,
            maxSumDimensionsCm: $maxSum,
            minOrderCostRub: $minOrderRub,
            maxOrderCostRub: $maxOrderRub,
            minOrderCostCny: $minOrderCny,
            maxOrderCostCny: $maxOrderCny,
            importSourceRow: $excelRow,
            sheetName: $sheetName,
            rawData: [
                'rate' => CellValueNormalizer::toString($rateRaw),
                'weight' => isset($columnMap['weight'])
                    ? CellValueNormalizer::toString($row[$columnMap['weight']] ?? null)
                    : null,
                'limits' => isset($columnMap['limits'])
                    ? CellValueNormalizer::toString($row[$columnMap['limits']] ?? null)
                    : null,
            ],
        );
    }
}
