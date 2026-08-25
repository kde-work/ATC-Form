<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\CellValueNormalizer;
use App\Support\ChannelCode;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Нормализация ячеек и стабильный code канала.
 */
final class CellValueNormalizerTest extends TestCase
{
    public function test_channel_code_from_name(): void
    {
        $this->assertSame('atc-express-extra-small', ChannelCode::fromName('ATC Express Extra Small'));
        $this->assertSame('super-express', ChannelCode::fromName('Super Express'));
        $this->assertSame('express', ChannelCode::fromName('Express'));
    }

    #[DataProvider('ozonRateProvider')]
    public function test_parse_ozon_rate(string $raw, string $fixed, string $perGram): void
    {
        $parsed = CellValueNormalizer::parseOzonRate($raw);
        $this->assertSame($fixed, $parsed['fixed_fee']);
        $this->assertSame($perGram, $parsed['per_gram_fee']);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function ozonRateProvider(): array
    {
        return [
            'with yen' => ['¥ 3.37 + ¥ 0.0505/1 g', '3.37000000', '0.05050000'],
            'plain' => ['3.37 + 0.0505/g', '3.37000000', '0.05050000'],
            'compact' => ['¥40.44 + ¥0.0281/1 g', '40.44000000', '0.02810000'],
            'leading tab' => ["\t¥ 24.71 + ¥ 0.0505/1 g", '24.71000000', '0.05050000'],
        ];
    }

    public function test_parse_ozon_rate_rejects_garbage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        CellValueNormalizer::parseOzonRate('not a rate');
    }

    public function test_parse_yandex_rate(): void
    {
        $parsed = CellValueNormalizer::parseYandexRate('193 + 948 RUB/kg');
        $this->assertSame('193.00000000', $parsed['fixed_fee']);
        $this->assertSame('948.00000000', $parsed['per_kg_fee']);

        $parsed2 = CellValueNormalizer::parseYandexRate('198 RUB + 787 /kg');
        $this->assertSame('198.00000000', $parsed2['fixed_fee']);
        $this->assertSame('787.00000000', $parsed2['per_kg_fee']);
    }

    public function test_parse_range_and_limits(): void
    {
        $this->assertSame(['1.00000000', '1500.00000000'], CellValueNormalizer::parseRange('1 - 1500'));
        $this->assertSame(['1.00000000', '1500.00000000'], CellValueNormalizer::parseRange('1–1500'));
        $this->assertSame(['135.01000000', '635.00000000'], CellValueNormalizer::parseRange('135. 01 - 635'));
        $this->assertSame(['7001.00000000', '250000.00000000'], CellValueNormalizer::parseRange('7001 - 250 000'));
        $this->assertSame(['635.01000000', '22525.00000000'], CellValueNormalizer::parseRange('635.01 - 22 525'));

        $limits = CellValueNormalizer::parseDimensionLimits('Sum of sides ≤ 90 cm, length ≤ 60 cm');
        $this->assertSame('90.00000000', $limits['max_sum_dimensions_cm']);
        $this->assertSame('60.00000000', $limits['max_length_cm']);

        $premium = CellValueNormalizer::parseDimensionLimits('Sum of sides ≤ 250 cm, length ≤ 150 cm.');
        $this->assertSame('250.00000000', $premium['max_sum_dimensions_cm']);
        $this->assertSame('150.00000000', $premium['max_length_cm']);
    }

    public function test_parse_leading_decimal_from_yandex_cells(): void
    {
        $this->assertSame('948.00000000', CellValueNormalizer::parseLeadingDecimal('948 ₽/кг'));
        $this->assertSame('193.00000000', CellValueNormalizer::parseLeadingDecimal('193 ₽/шт'));
    }
}
