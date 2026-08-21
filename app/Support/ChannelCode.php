<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Стабильный machine-id канала из отображаемого имени.
 */
final class ChannelCode
{
    /**
     * Алгоритм из docs/xlsx-import-format.md: lowercase, не [a-z0-9] -> дефис.
     */
    public static function fromName(string $name): string
    {
        $lower = mb_strtolower(trim($name), 'UTF-8');
        $slug = preg_replace('/[^a-z0-9]+/', '-', $lower) ?? '';
        $slug = trim($slug, '-');
        $slug = preg_replace('/-+/', '-', $slug) ?? $slug;

        return $slug;
    }
}
