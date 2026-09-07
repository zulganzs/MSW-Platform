<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seed one user per role (idempotent via firstOrCreate on email).
     * Password relies on the User model's 'hashed' cast.
     */
    public function run(): void
    {
        $users = [
            ['name' => 'Budi Santoso', 'email' => 'citizen@test.com', 'role' => 'citizen'],
            ['name' => 'Siti Rahayu', 'email' => 'staff@test.com', 'role' => 'staff'],
            ['name' => 'Joko Prasetyo', 'email' => 'crew@test.com', 'role' => 'crew'],
        ];

        foreach ($users as $user) {
            User::firstOrCreate(
                ['email' => $user['email']],
                [
                    'name' => $user['name'],
                    'role' => $user['role'],
                    'password' => 'Password123!',
                ],
            );
        }
    }
}
