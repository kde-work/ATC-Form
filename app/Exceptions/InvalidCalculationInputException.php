<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Входные данные расчёта не прошли нормализацию типов и обязательных полей.
 */
final class InvalidCalculationInputException extends InvalidArgumentException
{
    /**
     * @param array<string, list<string>> $errors
     */
    public function __construct(
        public readonly array $errors,
        string $message = 'The calculation input is invalid.',
    ) {
        parent::__construct($message);
    }
}
