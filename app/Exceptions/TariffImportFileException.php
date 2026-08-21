<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Файл импорта не удалось открыть или он не является допустимым XLSX.
 */
final class TariffImportFileException extends RuntimeException
{
}
