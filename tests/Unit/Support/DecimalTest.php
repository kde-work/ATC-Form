<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Decimal;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Проверяет bcmath-хелпер: ceil к инкременту и сравнение без float.
 */
final class DecimalTest extends TestCase
{
    #[DataProvider('ceilToIncrementProvider')]
    public function test_ceil_to_increment(string $value, int $increment, string $expected): void
    {
        self::assertSame($expected, Decimal::ceilToIncrement($value, $increment, 3));
    }

    /**
     * @return array<string, array{0: string, 1: int, 2: string}>
     */
    public static function ceilToIncrementProvider(): array
    {
        return [
            '80 to 100' => ['80', 100, '100.000'],
            '500 stays' => ['500', 100, '500.000'],
            '550 to 600' => ['550', 100, '600.000'],
        ];
    }

    public function test_rejects_non_numeric(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Decimal::normalize('abc');
    }
}
