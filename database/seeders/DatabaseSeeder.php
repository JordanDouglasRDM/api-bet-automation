<?php

declare(strict_types = 1);

namespace Database\Seeders;

use App\Models\License;
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
            'code'     => 'administrator',
            'login'    => 'jordan',
            'level'    => 'super',
            'password' => Hash::make('#Jordan@23'),
        ]);
        User::factory()->create([
            'code'     => 'lotoico.vip',
            'login'    => 'jhonny',
            'level'    => 'admin',
            'password' => Hash::make('404040'),
        ]);

        User::factory(20)->create();

        $users = User::all();

        foreach ($users as $user) {
            License::factory()->create([
                'user_id' => $user->id,
            ]);
        }
    }
}
