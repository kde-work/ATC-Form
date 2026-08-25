<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Нормализация значений ячеек XLSX без исполнения формул.
 */
final class CellValueNormalizer
{
    /**
     * Приводит сырое значение ячейки к строке для разбора.
     */
    public static function toString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            // float из PhpSpreadsheet только как промежуточный источник; дальше Decimal.
            return trim((string) $value);
        }

        if (is_string($value)) {
            return trim($value);
        }

        return trim((string) $value);
    }

    /**
     * Извлекает десятичное число из строки с валютой и разделителями тысяч.
     *
     * @return numeric-string|null
     */
    public static function parseDecimal(mixed $value): ?string
    {
        $raw = self::toString($value);
        if ($raw === '') {
            return null;
        }

        $cleaned = preg_replace('/[¥₽$€]|CNY|RUB|USD|EUR/iu', '', $raw) ?? $raw;
        $cleaned = str_replace(["\u{00A0}", ' '], '', $cleaned);
        $cleaned = str_replace(',', '.', $cleaned);
        $cleaned = trim($cleaned);

        if ($cleaned === '' || ! is_numeric($cleaned)) {
            return null;
        }

        if (stripos($cleaned, 'e') !== false) {
            return null;
        }

        return Decimal::normalize($cleaned, 8);
    }

    /**
     * Первое число в ячейке: "948 ₽/кг", "193 ₽/шт", "135. 01".
     *
     * @return numeric-string|null
     */
    public static function parseLeadingDecimal(mixed $value): ?string
    {
        $raw = self::toString($value);
        if ($raw === '') {
            return null;
        }

        $cleaned = preg_replace('/[¥₽$€]|CNY|RUB|USD|EUR/iu', '', $raw) ?? $raw;
        if (! preg_match('/([0-9]+(?:[.\s]*[0-9]+)*)/u', $cleaned, $matches)) {
            return null;
        }

        return self::parseDecimal($matches[1]);
    }

    /**
     * Разбор диапазона веса или стоимости: "1 - 1500", "1–1500", "1..1500".
     * Допускает пробел в числе: "135. 01 - 635", "7001 - 250 000".
     *
     * @return array{0: numeric-string, 1: numeric-string}|null
     */
    public static function parseRange(mixed $value): ?array
    {
        $raw = self::toString($value);
        if ($raw === '') {
            return null;
        }

        $normalized = preg_replace('/[¥₽$€]|CNY|RUB|USD|EUR/iu', '', $raw) ?? $raw;
        $normalized = str_replace("\u{00A0}", ' ', $normalized);
        $normalized = preg_replace('/\s+/u', ' ', trim($normalized)) ?? trim($normalized);

        if (! preg_match(
            '/^([0-9][0-9\s.,]*)\s*(?:-|–|\.\.|—)\s*([0-9][0-9\s.,]*)$/u',
            $normalized,
            $matches,
        )) {
            return null;
        }

        $min = self::parseDecimal($matches[1]);
        $max = self::parseDecimal($matches[2]);
        if ($min === null || $max === null) {
            return null;
        }

        return [$min, $max];
    }

    /**
     * Ozon rate: "¥ 3.37 + ¥ 0.0505/1 g" -> fixed + per_gram.
     *
     * @return array{fixed_fee: numeric-string, per_gram_fee: numeric-string}
     *
     * @throws InvalidArgumentException
     */
    public static function parseOzonRate(mixed $value): array
    {
        $raw = self::toString($value);
        if ($raw === '') {
            throw new InvalidArgumentException('cannot parse per-gram rate');
        }

        $normalized = preg_replace('/[¥₽]|CNY|RUB/iu', '', $raw) ?? $raw;
        $normalized = preg_replace('/\s+/u', ' ', trim($normalized)) ?? trim($normalized);

        if (! preg_match(
            '/^([0-9]+(?:[.,][0-9]+)?)\s*\+\s*([0-9]+(?:[.,][0-9]+)?)\s*\/\s*(?:1\s*)?g$/iu',
            $normalized,
            $matches,
        )) {
            throw new InvalidArgumentException('cannot parse per-gram rate');
        }

        $fixed = self::parseDecimal($matches[1]);
        $perGram = self::parseDecimal($matches[2]);
        if ($fixed === null || $perGram === null) {
            throw new InvalidArgumentException('cannot parse per-gram rate');
        }

        return [
            'fixed_fee' => $fixed,
            'per_gram_fee' => $perGram,
        ];
    }

    /**
     * Yandex rate: "193 + 948 RUB/kg" -> fixed + per_kg.
     *
     * @return array{fixed_fee: numeric-string, per_kg_fee: numeric-string}
     *
     * @throws InvalidArgumentException
     */
    public static function parseYandexRate(mixed $value): array
    {
        $raw = self::toString($value);
        if ($raw === '') {
            throw new InvalidArgumentException('cannot parse per-kg rate');
        }

        $normalized = preg_replace('/[¥₽]|CNY|RUB/iu', '', $raw) ?? $raw;
        $normalized = preg_replace('/\s+/u', ' ', trim($normalized)) ?? trim($normalized);

        if (! preg_match(
            '/^([0-9]+(?:[.,][0-9]+)?)\s*(?:\+\s*)?([0-9]+(?:[.,][0-9]+)?)\s*\/\s*kg$/iu',
            $normalized,
            $matches,
        )) {
            throw new InvalidArgumentException('cannot parse per-kg rate');
        }

        $fixed = self::parseDecimal($matches[1]);
        $perKg = self::parseDecimal($matches[2]);
        if ($fixed === null || $perKg === null) {
            throw new InvalidArgumentException('cannot parse per-kg rate');
        }

        return [
            'fixed_fee' => $fixed,
            'per_kg_fee' => $perKg,
        ];
    }

    /**
     * Лимиты сторон: sum of sides / length.
     *
     * @return array{max_sum_dimensions_cm: numeric-string|null, max_length_cm: numeric-string|null}
     */
    public static function parseDimensionLimits(mixed $value): array
    {
        $raw = self::toString($value);
        $maxSum = null;
        $maxLength = null;

        if ($raw === '') {
            return [
                'max_sum_dimensions_cm' => null,
                'max_length_cm' => null,
            ];
        }

        if (preg_match(
            '/(?:sum\s+of\s+sides|сумма\s+сторон)\s*(?:≤|<=|не\s+более)?\s*([0-9]+(?:[.,][0-9]+)?)/iu',
            $raw,
            $matches,
        )) {
            $maxSum = self::parseDecimal($matches[1]);
        }

        if (preg_match(
            '/(?:length|длина|max\s+side)\s*(?:≤|<=|не\s+более)?\s*([0-9]+(?:[.,][0-9]+)?)/iu',
            $raw,
            $matches,
        )) {
            $maxLength = self::parseDecimal($matches[1]);
        }

        return [
            'max_sum_dimensions_cm' => $maxSum,
            'max_length_cm' => $maxLength,
        ];
    }

    /**
     * Нормализация заголовка колонки для сопоставления.
     */
    public static function normalizeHeader(string $header): string
    {
        $value = mb_strtolower(trim($header), 'UTF-8');
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value;
    }

    /**
     * Нормализация имени листа для поиска платформы.
     */
    public static function normalizeSheetName(string $name): string
    {
        $value = trim($name);
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;
        $value = mb_strtolower($value, 'UTF-8');
        $value = str_replace(['ё', 'Ё'], ['е', 'е'], $value);
        $value = preg_replace('/\s*\+\s*/u', '+', $value) ?? $value;

        return $value;
    }
}
