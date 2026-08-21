<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TariffImport;
use App\Models\User;

/**
 * Права на импорты тарифов. В MVP любой аутентифицированный user = admin.
 */
final class TariffImportPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, TariffImport $tariffImport): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function activate(User $user, TariffImport $tariffImport): bool
    {
        return true;
    }

    public function rollback(User $user, TariffImport $tariffImport): bool
    {
        return true;
    }
}
