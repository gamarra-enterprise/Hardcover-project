<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Development accounts, one per role. All use the password "password".
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $accounts = [
            ['Super Admin', 'superadmin@hardcover.test', UserRole::SUPER_ADMIN],
            ['Admin', 'admin@hardcover.test', UserRole::ADMIN],
            ['Cliente', 'cliente@hardcover.test', UserRole::CUSTOMER],
        ];

        foreach ($accounts as [$name, $email, $role]) {
            // role is not mass assignable, so build the account unguarded.
            User::unguarded(fn () => User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'role' => $role,
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]));
        }
    }
}
