<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_SEED_EMAIL');
        $name = env('ADMIN_SEED_NAME');
        $password = env('ADMIN_SEED_PASSWORD');

        if ($email && $name && $password) {
            User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make($password),
                    'role' => 'admin',
                    'account_status' => 'active',
                ]
            );
        }
    }
}
