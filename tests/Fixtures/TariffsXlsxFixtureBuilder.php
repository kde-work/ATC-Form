<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Сборка тестовых XLSX по docs/xlsx-import-format.md.
 */
final class TariffsXlsxFixtureBuilder
{
    /**
     * @return list<array{name: string, weight: string, rate: string, limits: string, order_cny: string, order_rub: string}>
     */
    public static function defaultOzonRows(): array
    {
        return [
            [
                'name' => 'ATC Express Extra Small',
                'weight' => '1 - 1500',
                'rate' => '¥ 4.10 + ¥ 0.0600/1 g',
                'limits' => 'Sum of sides ≤ 90 cm, length ≤ 60 cm',
                'order_cny' => '100.00 - 700.00',
                'order_rub' => '',
            ],
            [
                'name' => 'ATC Standard Extra Small',
                'weight' => '1 - 1500',
                'rate' => '¥ 3.37 + ¥ 0.0505/1 g',
                'limits' => 'Sum of sides ≤ 90 cm, length ≤ 60 cm',
                'order_cny' => '135.01 - 635.00',
                'order_rub' => '',
            ],
            [
                'name' => 'ATC Economy Extra Small',
                'weight' => '1 - 1500',
                'rate' => '¥ 2.90 + ¥ 0.0400/1 g',
                'limits' => 'Sum of sides ≤ 90 cm, length ≤ 60 cm',
                'order_cny' => '100.00 - 600.00',
                'order_rub' => '',
            ],
            [
                'name' => 'ATC Standard Budget',
                'weight' => '1 - 2000',
                'rate' => '¥ 2.50 + ¥ 0.0350/1 g',
                'limits' => 'Sum of sides ≤ 100 cm, length ≤ 70 cm',
                'order_cny' => '50.00 - 500.00',
                'order_rub' => '',
            ],
            [
                'name' => 'ATC Economy Budget',
                'weight' => '1 - 2000',
                'rate' => '¥ 2.10 + ¥ 0.0300/1 g',
                'limits' => 'Sum of sides ≤ 100 cm, length ≤ 70 cm',
                'order_cny' => '50.00 - 500.00',
                'order_rub' => '',
            ],
            [
                'name' => 'ATC Express Small',
                'weight' => '1 - 5000',
                'rate' => '¥ 8.00 + ¥ 0.0450/1 g',
                'limits' => 'Sum of sides ≤ 120 cm, length ≤ 80 cm',
                'order_cny' => '100.00 - 1500.00',
                'order_rub' => '',
            ],
            [
                'name' => 'ATC Standard Small',
                'weight' => '1 - 5000',
                'rate' => '¥ 6.50 + ¥ 0.0380/1 g',
                'limits' => 'Sum of sides ≤ 120 cm, length ≤ 80 cm',
                'order_cny' => '100.00 - 1500.00',
                'order_rub' => '',
            ],
            [
                'name' => 'ATC Economy Small',
                'weight' => '1 - 5000',
                'rate' => '¥ 5.20 + ¥ 0.0320/1 g',
                'limits' => 'Sum of sides ≤ 120 cm, length ≤ 80 cm',
                'order_cny' => '100.00 - 1500.00',
                'order_rub' => '',
            ],
            [
                'name' => 'ATC Standard Big',
                'weight' => '1 - 30000',
                'rate' => '¥ 40.44 + ¥ 0.0281/1 g',
                'limits' => 'Sum of sides ≤ 150 cm, length ≤ 100 cm',
                'order_cny' => '',
                'order_rub' => '',
            ],
            [
                'name' => 'ATC Economy Big',
                'weight' => '1 - 30000',
                'rate' => '¥ 35.00 + ¥ 0.0250/1 g',
                'limits' => 'Sum of sides ≤ 150 cm, length ≤ 100 cm',
                'order_cny' => '',
                'order_rub' => '',
            ],
            [
                'name' => 'ATC Express Premium Small',
                'weight' => '1 - 5000',
                'rate' => '¥ 12.00 + ¥ 0.0550/1 g',
                'limits' => 'Sum of sides ≤ 120 cm, length ≤ 80 cm',
                'order_cny' => '200.00 - 2000.00',
                'order_rub' => '',
            ],
            [
                'name' => 'ATC Standard Premium Small',
                'weight' => '1 - 5000',
                'rate' => '¥ 10.00 + ¥ 0.0480/1 g',
                'limits' => 'Sum of sides ≤ 120 cm, length ≤ 80 cm',
                'order_cny' => '200.00 - 2000.00',
                'order_rub' => '',
            ],
            [
                'name' => 'ATC Economy Premium Small',
                'weight' => '1 - 5000',
                'rate' => '¥ 8.50 + ¥ 0.0420/1 g',
                'limits' => 'Sum of sides ≤ 120 cm, length ≤ 80 cm',
                'order_cny' => '200.00 - 2000.00',
                'order_rub' => '',
            ],
            [
                'name' => 'ATC Standard Premium Big',
                'weight' => '1 - 30000',
                'rate' => '¥ 50.00 + ¥ 0.0300/1 g',
                'limits' => 'Sum of sides ≤ 160 cm, length ≤ 110 cm',
                'order_cny' => '',
                'order_rub' => '',
            ],
            [
                'name' => 'ATC Economy Premium Big',
                'weight' => '1 - 30000',
                'rate' => '¥ 45.00 + ¥ 0.0270/1 g',
                'limits' => 'Sum of sides ≤ 160 cm, length ≤ 110 cm',
                'order_cny' => '',
                'order_rub' => '',
            ],
        ];
    }

    /**
     * @param list<array{name: string, weight: string, rate: string, limits: string, order_cny: string, order_rub: string}>|null $ozonRows
     * @param list<array{name: string, rate: string}>|null $yandexRows
     */
    public static function write(
        string $absolutePath,
        ?array $ozonRows = null,
        ?array $yandexRows = null,
        string $ozonSheetName = 'тарифы Ozon без формул + лимиты',
        string $yandexSheetName = 'тарифы с формулами EXCEL',
    ): void {
        $ozonRows ??= self::defaultOzonRows();
        $yandexRows ??= [
            ['name' => 'Super Express 空运卡航', 'rate' => '193 + 948 RUB/kg'],
            ['name' => 'Express 卡航陆运', 'rate' => '198 RUB + 787 /kg'],
        ];

        $spreadsheet = new Spreadsheet();
        $ozonSheet = $spreadsheet->getActiveSheet();
        $ozonSheet->setTitle($ozonSheetName);

        $ozonSheet->fromArray(
            ['Channel', 'Weight g', 'Rate', 'Limits', 'Order CNY', 'Order RUB'],
            null,
            'A1',
        );

        $rowNumber = 2;
        foreach ($ozonRows as $row) {
            $ozonSheet->fromArray([
                $row['name'],
                $row['weight'],
                $row['rate'],
                $row['limits'],
                $row['order_cny'],
                $row['order_rub'],
            ], null, 'A' . $rowNumber);
            $rowNumber++;
        }

        $yandexSheet = $spreadsheet->createSheet();
        $yandexSheet->setTitle($yandexSheetName);
        $yandexSheet->setCellValue('A1', 'Yandex Market');
        $yandexSheet->setCellValue('A2', 'Channel');
        $yandexSheet->setCellValue('B2', 'Rate');

        $yandexRow = 3;
        foreach ($yandexRows as $row) {
            $yandexSheet->setCellValue('A' . $yandexRow, $row['name']);
            $yandexSheet->setCellValue('B' . $yandexRow, $row['rate']);
            $yandexRow++;
        }

        $directory = dirname($absolutePath);
        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        (new Xlsx($spreadsheet))->save($absolutePath);
        $spreadsheet->disconnectWorksheets();
    }

    public static function path(string $filename): string
    {
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Fixtures' . DIRECTORY_SEPARATOR . 'xlsx' . DIRECTORY_SEPARATOR . $filename;
    }
}
