<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Способ расчёта платного веса по тарифу канала.
 */
enum WeightCalculationType: string
{
    case Physical = 'physical';
    case MaxPhysicalOrVolumetric = 'max_physical_or_volumetric';
}
