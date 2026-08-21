<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/**
 * Активация или rollback ревизии запрещены по бизнес-правилам.
 */
final class TariffRevisionActivationException extends DomainException
{
}
