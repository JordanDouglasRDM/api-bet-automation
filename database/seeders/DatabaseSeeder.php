<?php

declare(strict_types = 1);

namespace Database\Seeders;

use App\Models\User;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name'     => 'Jordan Douglas',
            'email'    => 'jordandouglas8515@gmail.com',
            'password' => Hash::make('#Password@123'),
        ]);

        User::factory()->create([
            'name'     => 'Samuel henrique',
            'email'    => 'test@example.com',
            'password' => Hash::make('#Password@123'),
        ]);
        User::factory(9)->create();
    }
}
