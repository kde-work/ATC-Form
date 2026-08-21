<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Tests\Fixtures\TariffsXlsxFixtureBuilder;

$valid = TariffsXlsxFixtureBuilder::path('valid-tariffs.xlsx');
TariffsXlsxFixtureBuilder::write($valid);

$dupRows = TariffsXlsxFixtureBuilder::defaultOzonRows();
$dupRows[] = $dupRows[1];
TariffsXlsxFixtureBuilder::write(
    TariffsXlsxFixtureBuilder::path('invalid-duplicate-code.xlsx'),
    $dupRows,
);

$brokenRows = TariffsXlsxFixtureBuilder::defaultOzonRows();
$brokenRows[1]['rate'] = 'broken rate value';
TariffsXlsxFixtureBuilder::write(
    TariffsXlsxFixtureBuilder::path('invalid-broken-rate.xlsx'),
    $brokenRows,
);

echo "Fixtures written to tests/Fixtures/xlsx/\n";
