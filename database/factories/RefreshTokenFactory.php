<?php

declare(strict_types = 1);

namespace Database\Factories;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RefreshTokenFactory extends Factory
{
    protected $model = RefreshToken::class;

    public function definition(): array
    {
        $faker = $this->faker;

        return [
            'user_id'    => User::factory(),
            'token'      => Str::uuid()->toString(),
            'ip'         => $faker->ipv4(),
            'user_agent' => $faker->randomElement([
                'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0.0.0',
                'Mozilla/5.0 (Macintosh; Intel Mac OS X 13_5_1) Safari/605.1.15',
                'Mozilla/5.0 (Linux; Android 13) Mobile Firefox/122.0',
            ]),
            'device_id'  => Str::uuid()->toString(),
            'revoked_at' => null,
            'expires_at' => now()->addDays(7),
        ];
    }

    /**
     * Estado: token revogado.
     */
    public function revoked(): self
    {
        return $this->state(fn (): array => [
            'revoked_at' => now(),
        ]);
    }

    /**
     * Estado: token expirado.
     */
    public function expired(): self
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    /**
     * Estado: token ativo.
     */
    public function active(): self
    {
        return $this->state(fn (): array => [
            'revoked_at' => null,
            'expires_at' => now()->addDays(7),
        ]);
    }
}
