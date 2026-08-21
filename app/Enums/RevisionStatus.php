<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Статус ревизии набора тарифов.
 */
enum RevisionStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';
    case Invalid = 'invalid';
}
