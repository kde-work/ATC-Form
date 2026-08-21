<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Создаёт единственного администратора из ADMIN_EMAIL / ADMIN_PASSWORD.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('atc.admin_email');
        $password = config('atc.admin_password');

        if (! is_string($email) || $email === '') {
            throw new RuntimeException('ADMIN_EMAIL is not configured.');
        }

        if (! is_string($password) || $password === '') {
            throw new RuntimeException('ADMIN_PASSWORD is not configured.');
        }

        User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Admin',
                'password' => Hash::make($password),
                'email_verified_at' => now(),
            ]
        );
    }
}
