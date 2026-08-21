<?php

declare(strict_types=1);

namespace App\Services\TariffImport;

use App\Dto\TariffImport\ParsedChannelRow;
use App\Models\DeliveryChannel;
use App\Models\TariffRevision;
use App\Support\Decimal;

/**
 * Preview diff draft-каналов относительно текущей active-ревизии.
 */
final class TariffRevisionDiffService
{
    /**
     * Поля тарифа, изменение которых считается changed.
     *
     * @var list<string>
     */
    private const COMPARE_FIELDS = [
        'name',
        'active',
        'currency',
        'chargeable_weight_type',
        'fixed_fee',
        'per_gram_fee',
        'per_kg_fee',
        'billing_increment_grams',
        'volumetric_divisor',
        'min_weight_grams',
        'max_weight_grams',
        'max_length_cm',
        'max_sum_dimensions_cm',
        'min_order_cost_rub',
        'max_order_cost_rub',
        'min_order_cost_cny',
        'max_order_cost_cny',
    ];

    /**
     * @param list<ParsedChannelRow> $incoming
     * @return array{
     *     total: int,
     *     added: int,
     *     changed: int,
     *     removed: int,
     *     added_codes: list<string>,
     *     changed_codes: list<string>,
     *     removed_codes: list<string>
     * }
     */
    public function summarize(array $incoming, ?TariffRevision $activeRevision): array
    {
        $incomingMap = [];
        foreach ($incoming as $row) {
            $incomingMap[$this->key($row->platform->value, $row->code)] = $row;
        }

        $activeMap = [];
        if ($activeRevision !== null) {
            /** @var DeliveryChannel $channel */
            foreach ($activeRevision->channels as $channel) {
                $activeMap[$this->key($channel->platform->value, $channel->code)] = $channel;
            }
        }

        $added = [];
        $changed = [];
        $removed = [];

        foreach ($incomingMap as $key => $row) {
            if (! isset($activeMap[$key])) {
                $added[] = $key;

                continue;
            }

            if ($this->isChanged($row, $activeMap[$key])) {
                $changed[] = $key;
            }
        }

        foreach ($activeMap as $key => $channel) {
            if (! isset($incomingMap[$key])) {
                $removed[] = $key;
            }
        }

        sort($added);
        sort($changed);
        sort($removed);

        return [
            'total' => count($incoming),
            'added' => count($added),
            'changed' => count($changed),
            'removed' => count($removed),
            'added_codes' => $added,
            'changed_codes' => $changed,
            'removed_codes' => $removed,
        ];
    }

    private function key(string $platform, string $code): string
    {
        return $platform . ':' . $code;
    }

    private function isChanged(ParsedChannelRow $incoming, DeliveryChannel $active): bool
    {
        $incomingAttrs = $incoming->toChannelAttributes();

        foreach (self::COMPARE_FIELDS as $field) {
            $left = $this->normalizeComparable($incomingAttrs[$field] ?? null, $field);
            $right = $this->normalizeComparable($active->getAttribute($field), $field);
            if ($left !== $right) {
                return true;
            }
        }

        return false;
    }

    private function normalizeComparable(mixed $value, string $field): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if (is_int($value)) {
            return (string) $value;
        }

        $string = trim((string) $value);
        if ($string === '') {
            return null;
        }

        $decimalFields = [
            'fixed_fee',
            'per_gram_fee',
            'per_kg_fee',
            'volumetric_divisor',
            'min_weight_grams',
            'max_weight_grams',
            'max_length_cm',
            'max_sum_dimensions_cm',
            'min_order_cost_rub',
            'max_order_cost_rub',
            'min_order_cost_cny',
            'max_order_cost_cny',
        ];

        if (in_array($field, $decimalFields, true) && is_numeric($string)) {
            $scale = match ($field) {
                'per_gram_fee' => 8,
                'fixed_fee', 'per_kg_fee' => 6,
                'volumetric_divisor' => 4,
                'min_order_cost_rub', 'max_order_cost_rub', 'min_order_cost_cny', 'max_order_cost_cny' => 4,
                default => 3,
            };

            return Decimal::normalize($string, $scale);
        }

        return $string;
    }
}
