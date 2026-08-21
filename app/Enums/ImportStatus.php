<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Статус обработки загруженного XLSX-импорта тарифов.
 */
enum ImportStatus: string
{
    case Uploaded = 'uploaded';
    case Processing = 'processing';
    case ValidationFailed = 'validation_failed';
    case Validated = 'validated';
    case Activated = 'activated';
    case Failed = 'failed';
}
