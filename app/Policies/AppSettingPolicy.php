<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AppSetting;
use App\Models\User;

/**
 * Права на настройки приложения. В MVP любой аутентифицированный user = admin.
 */
final class AppSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function update(User $user, AppSetting $appSetting): bool
    {
        return true;
    }
}
