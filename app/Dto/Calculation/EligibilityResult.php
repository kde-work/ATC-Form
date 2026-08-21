<?php

declare(strict_types=1);

namespace App\Dto\Calculation;

/**
 * Результат проверки eligibility: все нарушения сразу.
 */
final readonly class EligibilityResult
{
    /**
     * @param list<string> $errors
     * @param list<string> $warnings
     */
    public function __construct(
        public bool $eligible,
        public array $errors = [],
        public array $warnings = [],
    ) {
    }

    /**
     * @param list<string> $errors
     * @param list<string> $warnings
     */
    public static function fromErrors(array $errors, array $warnings = []): self
    {
        return new self(
            eligible: $errors === [],
            errors: $errors,
            warnings: $warnings,
        );
    }
}
