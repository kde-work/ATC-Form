<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Сборка тестовых XLSX по docs/xlsx-import-format.md и исходному файлу заказчика.
 */
final class TariffsXlsxFixtureBuilder
{
    /**
     * Строки Ozon в формате исходника: отдельные min/max g, RUB и CNY.
     *
     * @return list<array{
     *     name: string,
     *     rate: string,
     *     limits: string,
     *     min_weight: string,
     *     max_weight: string,
     *     order_cny: string,
     *     order_rub: string,
     *     tarification: string
     * }>
     */
    public static function defaultOzonRows(): array
    {
        return [
            [
                'name' => 'ATC Express Extra Small',
                'rate' => '¥ 3.37 + ¥ 0.0505/1 g',
                'limits' => 'Sum of sides ≤ 90 cm, length ≤ 60 cm',
                'min_weight' => '1',
                'max_weight' => '500',
                'order_cny' => '0.01 - 135',
                'order_rub' => '1 - 1500',
                'tarification' => 'Physical weight',
            ],
            [
                'name' => 'ATC Standard Extra Small',
                'rate' => '¥ 3.37 + ¥ 0.0393/1 g',
                'limits' => 'Sum of sides ≤ 90 cm, length ≤ 60 cm',
                'min_weight' => '1',
                'max_weight' => '500',
                'order_cny' => '0.01 - 135',
                'order_rub' => '1 - 1500',
                'tarification' => 'Physical weight',
            ],
            [
                'name' => 'ATC Economy Extra Small',
                'rate' => '¥ 3.37 + ¥ 0.0281/1 g',
                'limits' => 'Sum of sides ≤ 90 cm, length ≤ 60 cm',
                'min_weight' => '1',
                'max_weight' => '500',
                'order_cny' => '0.01 - 135',
                'order_rub' => '1 - 1500',
                'tarification' => 'Physical weight',
            ],
            [
                'name' => 'ATC Standard Budget',
                'rate' => '¥ 25.83 + ¥ 0.0281/1 g',
                'limits' => 'Sum of sides ≤ 150 cm, length ≤ 60 cm',
                'min_weight' => '501',
                'max_weight' => '30000',
                'order_cny' => '0.01 - 135',
                'order_rub' => '1 - 1500',
                'tarification' => 'Physical weight',
            ],
            [
                'name' => 'ATC Economy Budget',
                'rate' => '¥ 25.83 + ¥ 0.0191/1 g',
                'limits' => 'Sum of sides ≤ 150 cm, length ≤ 60 cm',
                'min_weight' => '501',
                'max_weight' => '30000',
                'order_cny' => '0.01 - 135',
                'order_rub' => '1 - 1500',
                'tarification' => 'Physical weight',
            ],
            [
                'name' => 'ATC Express Small',
                'rate' => '¥ 17.97 + ¥ 0.0505/1 g',
                'limits' => 'Sum of sides ≤ 150 cm, length ≤ 60 cm',
                'min_weight' => '1',
                'max_weight' => '2000',
                'order_cny' => '135.01 - 635',
                'order_rub' => '1501 - 7000',
                'tarification' => 'Physical weight',
            ],
            [
                'name' => 'ATC Standard Small',
                'rate' => '¥ 17.97 + ¥ 0.0393/1 g',
                'limits' => 'Sum of sides ≤ 150 cm, length ≤ 60 cm',
                'min_weight' => '1',
                'max_weight' => '2000',
                'order_cny' => '135.01 - 635',
                'order_rub' => '1501 - 7000',
                'tarification' => 'Physical weight',
            ],
            [
                'name' => 'ATC Economy Small',
                'rate' => '¥ 17.97 + ¥ 0.0281/1 g',
                'limits' => 'Sum of sides ≤ 150 cm, length ≤ 60 cm',
                'min_weight' => '1',
                'max_weight' => '2000',
                'order_cny' => '135.01 - 635',
                'order_rub' => '1501 - 7000',
                'tarification' => 'Physical weight',
            ],
            [
                'name' => 'ATC Standard Big',
                'rate' => '¥ 40.44 + ¥ 0.0281/1 g',
                'limits' => 'Sum of sides ≤ 310 cm, length ≤ 150 cm',
                'min_weight' => '2001',
                'max_weight' => '30000',
                'order_cny' => '135.01 - 635',
                'order_rub' => '1501 - 7000',
                'tarification' => 'Max weight between the physical and volume ones',
            ],
            [
                'name' => 'ATC Economy Big',
                'rate' => '¥ 40.44 + ¥ 0.0191/1 g',
                'limits' => 'Sum of sides ≤ 310 cm, length ≤ 150 cm',
                'min_weight' => '2001',
                'max_weight' => '30000',
                'order_cny' => '135.01 - 635',
                'order_rub' => '1501 - 7000',
                'tarification' => 'Max weight between the physical and volume ones',
            ],
            [
                'name' => 'ATC Express Premium Small',
                'rate' => '¥ 24.71 + ¥ 0.0505/1 g',
                'limits' => 'Sum of sides ≤ 250 cm, length ≤ 150 cm.',
                'min_weight' => '1',
                'max_weight' => '5000',
                'order_cny' => '635.01 - 22525',
                'order_rub' => '7001 - 250000',
                'tarification' => 'Physical weight',
            ],
            [
                'name' => 'ATC Standard Premium Small',
                'rate' => '¥ 24.71 + ¥ 0.0393/1 g',
                'limits' => 'Sum of sides ≤ 250 cm, length ≤ 150 cm.',
                'min_weight' => '1',
                'max_weight' => '5000',
                'order_cny' => '635.01 - 22525',
                'order_rub' => '7001 - 250000',
                'tarification' => 'Physical weight',
            ],
            [
                'name' => 'ATC Economy Premium Small',
                'rate' => '¥ 24.71 + ¥ 0.0281/1 g',
                'limits' => 'Sum of sides ≤ 250 cm, length ≤ 150 cm.',
                'min_weight' => '1',
                'max_weight' => '5000',
                'order_cny' => '635.01 - 22525',
                'order_rub' => '7001 - 250000',
                'tarification' => 'Physical weight',
            ],
            [
                'name' => 'ATC Standard Premium Big',
                'rate' => '¥ 69.64 + ¥ 0.0314/1 g',
                'limits' => 'Sum of sides ≤ 310 cm, length ≤ 150 cm',
                'min_weight' => '5001',
                'max_weight' => '30000',
                'order_cny' => '635.01 - 22525',
                'order_rub' => '7001 - 250000',
                'tarification' => 'Max weight between the physical and volume ones',
            ],
            [
                'name' => 'ATC Economy Premium Big',
                'rate' => '¥ 69.64 + ¥ 0.0258/1 g',
                'limits' => 'Sum of sides ≤ 310 cm, length ≤ 150 cm',
                'min_weight' => '5001',
                'max_weight' => '30000',
                'order_cny' => '635.01 - 22525',
                'order_rub' => '7001 - 250000',
                'tarification' => 'Max weight between the physical and volume ones',
            ],
        ];
    }

    /**
     * @param list<array<string, string>>|null $ozonRows
     */
    public static function write(
        string $absolutePath,
        ?array $ozonRows = null,
        ?array $yandexRows = null,
        string $ozonSheetName = 'тарифы Ozon без формул + лимиты',
        string $yandexSheetName = 'тарифы с формулами EXCEL',
    ): void {
        $ozonRows ??= self::defaultOzonRows();

        $spreadsheet = new Spreadsheet();
        $ozonSheet = $spreadsheet->getActiveSheet();
        $ozonSheet->setTitle($ozonSheetName);

        $ozonSheet->fromArray(
            [
                'Delivery Method',
                'Rates (PUDO / Courier)',
                'Measurements, max cm',
                'Shipment weight limits / min g',
                'Shipment weight limits / max g',
                'Shipment cost limit / min-max  | RUB',
                'Shipment cost limit / min-max  | CNY',
                'Tarification type',
            ],
            null,
            'A1',
        );

        $rowNumber = 2;
        foreach ($ozonRows as $row) {
            $ozonSheet->fromArray([
                $row['name'],
                $row['rate'],
                $row['limits'] ?? '',
                $row['min_weight'] ?? '',
                $row['max_weight'] ?? '',
                $row['order_rub'] ?? '',
                $row['order_cny'] ?? '',
                $row['tarification'] ?? '',
            ], null, 'A' . $rowNumber);
            $rowNumber++;
        }

        $yandexSheet = $spreadsheet->createSheet();
        $yandexSheet->setTitle($yandexSheetName);
        $yandexSheet->setCellValue('A1', 'Yandex Market');
        $yandexSheet->setCellValue('A2', 'Параметр');
        $yandexSheet->setCellValue('B2', 'Super Express (空运卡航)');
        $yandexSheet->setCellValue('C2', 'Express (卡航陆运)');
        $yandexSheet->setCellValue('A3', 'Ставка за кг');
        $yandexSheet->setCellValue('B3', '948 ₽/кг');
        $yandexSheet->setCellValue('C3', '787 ₽/кг');
        $yandexSheet->setCellValue('A4', 'Фиксированная ставка за заказ');
        $yandexSheet->setCellValue('B4', '193 ₽/шт');
        $yandexSheet->setCellValue('C4', '198 ₽/шт');

        if ($yandexRows !== null) {
            $yandexRow = 6;
            $yandexSheet->setCellValue('A5', 'Channel');
            $yandexSheet->setCellValue('B5', 'Rate');
            foreach ($yandexRows as $row) {
                $yandexSheet->setCellValue('A' . $yandexRow, $row['name']);
                $yandexSheet->setCellValue('B' . $yandexRow, $row['rate']);
                $yandexRow++;
            }
        }

        self::save($spreadsheet, $absolutePath);
    }

    /**
     * Компактный layout тестов: Weight g диапазоном и ставка в одной ячейке.
     *
     * @param list<array<string, string>> $ozonRows
     */
    public static function writeLegacyCompact(string $absolutePath, array $ozonRows): void
    {
        $spreadsheet = new Spreadsheet();
        $ozonSheet = $spreadsheet->getActiveSheet();
        $ozonSheet->setTitle('тарифы Ozon без формул + лимиты');
        $ozonSheet->fromArray(
            ['Channel', 'Weight g', 'Rate', 'Limits', 'Order CNY', 'Order RUB'],
            null,
            'A1',
        );

        $rowNumber = 2;
        foreach ($ozonRows as $row) {
            $weight = $row['weight'] ?? '';
            if ($weight === '' && isset($row['min_weight'], $row['max_weight'])) {
                $weight = $row['min_weight'] . ' - ' . $row['max_weight'];
            }

            $ozonSheet->fromArray([
                $row['name'],
                $weight,
                $row['rate'],
                $row['limits'] ?? '',
                $row['order_cny'] ?? '',
                $row['order_rub'] ?? '',
            ], null, 'A' . $rowNumber);
            $rowNumber++;
        }

        $yandexSheet = $spreadsheet->createSheet();
        $yandexSheet->setTitle('тарифы с формулами EXCEL');
        $yandexSheet->setCellValue('A1', 'Yandex Market');
        $yandexSheet->setCellValue('A2', 'Channel');
        $yandexSheet->setCellValue('B2', 'Rate');
        $yandexSheet->setCellValue('A3', 'Super Express 空运卡航');
        $yandexSheet->setCellValue('B3', '193 + 948 RUB/kg');
        $yandexSheet->setCellValue('A4', 'Express 卡航陆运');
        $yandexSheet->setCellValue('B4', '198 RUB + 787 /kg');

        self::save($spreadsheet, $absolutePath);
    }

    public static function path(string $filename): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . 'xlsx' . DIRECTORY_SEPARATOR . $filename;
    }

    private static function save(Spreadsheet $spreadsheet, string $absolutePath): void
    {
        $directory = dirname($absolutePath);
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        (new Xlsx($spreadsheet))->save($absolutePath);
        $spreadsheet->disconnectWorksheets();
    }
}
