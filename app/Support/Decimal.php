<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Десятичная арифметика строками через bcmath без float.
 */
final class Decimal
{
    /**
     * Нормализует числовую строку: без экспоненты, без лишних нулей в целой части.
     *
     * @throws InvalidArgumentException
     */
    public static function normalize(string $value, int $scale = 8): string
    {
        $trimmed = trim($value);
        if ($trimmed === '' || ! is_numeric($trimmed)) {
            throw new InvalidArgumentException('Value is not a valid decimal number.');
        }

        if (stripos($trimmed, 'e') !== false) {
            throw new InvalidArgumentException('Scientific notation is not allowed for decimal values.');
        }

        return bcadd($trimmed, '0', $scale);
    }

    /**
     * Проверяет, что значение можно трактовать как целое без дробной части.
     */
    public static function isIntegerString(mixed $value): bool
    {
        if (is_int($value)) {
            return true;
        }

        if (! is_string($value) && ! is_float($value)) {
            return false;
        }

        $asString = is_string($value) ? trim($value) : (string) $value;
        if ($asString === '' || ! is_numeric($asString)) {
            return false;
        }

        if (stripos($asString, 'e') !== false) {
            return false;
        }

        return bccomp($asString, (string) (int) $asString, 8) === 0
            && ! str_contains($asString, '.');
    }

    public static function add(string $left, string $right, int $scale = 8): string
    {
        return bcadd(self::normalize($left, $scale), self::normalize($right, $scale), $scale);
    }

    public static function sub(string $left, string $right, int $scale = 8): string
    {
        return bcsub(self::normalize($left, $scale), self::normalize($right, $scale), $scale);
    }

    public static function mul(string $left, string $right, int $scale = 8): string
    {
        return bcmul(self::normalize($left, $scale), self::normalize($right, $scale), $scale);
    }

    public static function div(string $left, string $right, int $scale = 8): string
    {
        $divisor = self::normalize($right, $scale);
        if (bccomp($divisor, '0', $scale) === 0) {
            throw new InvalidArgumentException('Division by zero.');
        }

        return bcdiv(self::normalize($left, $scale), $divisor, $scale);
    }

    public static function compare(string $left, string $right, int $scale = 8): int
    {
        return bccomp(self::normalize($left, $scale), self::normalize($right, $scale), $scale);
    }

    public static function max(string $left, string $right, int $scale = 8): string
    {
        return self::compare($left, $right, $scale) >= 0
            ? self::normalize($left, $scale)
            : self::normalize($right, $scale);
    }

    /**
     * Округление вверх до кратности increment (для billed weight Yandex).
     */
    public static function ceilToIncrement(string $value, int $increment, int $scale = 8): string
    {
        if ($increment <= 0) {
            throw new InvalidArgumentException('Billing increment must be positive.');
        }

        $normalized = self::normalize($value, $scale);
        $incrementString = (string) $increment;
        $quotient = bcdiv($normalized, $incrementString, 0);
        $product = bcmul($quotient, $incrementString, $scale);

        if (bccomp($product, $normalized, $scale) < 0) {
            $product = bcadd($product, $incrementString, $scale);
        }

        return $product;
    }

    /**
     * Форматирует число для сообщений UI: тысячи через запятую, без лишних нулей.
     */
    public static function formatDisplay(string $value, int $maxScale = 4): string
    {
        $normalized = self::normalize($value, $maxScale);
        $trimmed = self::trimTrailingZeros($normalized);

        return self::groupThousands($trimmed);
    }

    /**
     * Деньги в сообщениях UI: фиксированный scale (обычно 2).
     */
    public static function formatMoneyDisplay(string $value, int $scale = 2): string
    {
        return self::groupThousands(self::normalize($value, $scale));
    }

    /**
     * Группирует тысячные разряды запятой без смены scale.
     */
    private static function groupThousands(string $value): string
    {
        $parts = explode('.', $value, 2);
        $intPart = $parts[0];
        $negative = str_starts_with($intPart, '-');
        if ($negative) {
            $intPart = substr($intPart, 1);
        }

        $grouped = preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $intPart) ?? $intPart;
        $result = ($negative ? '-' : '') . $grouped;
        if (isset($parts[1])) {
            $result .= '.' . $parts[1];
        }

        return $result;
    }

    /**
     * Убирает хвостовые нули после десятичной точки, сохраняя целую часть.
     */
    public static function trimTrailingZeros(string $value): string
    {
        if (! str_contains($value, '.')) {
            return $value;
        }

        $trimmed = rtrim(rtrim($value, '0'), '.');

        return $trimmed === '-0' ? '0' : $trimmed;
    }

    /**
     * Приводит значение к строке для API: фиксированный scale без float.
     */
    public static function toApiString(string $value, int $scale): string
    {
        return self::normalize($value, $scale);
    }
}
