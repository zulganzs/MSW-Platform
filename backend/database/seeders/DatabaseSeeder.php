<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Categories first: reports reference them as FK parents.
        $this->call([
            CategorySeeder::class,
            UserSeeder::class,
        ]);
    }
}
