<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Права на просмотр тарифов. Изменение только через импорт, не через этот policy.
 */
final class DeliveryChannelPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }
}
