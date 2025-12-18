<?php

declare(strict_types=1);

namespace Database\Factories;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\License>
 */
class LicenseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $statuses = ['active', 'expired', 'revoked'];
        $start = Carbon::now()->addDays($this->faker->numberBetween(1, 30));
        $expires = $start->copy()->addDays($this->faker->numberBetween(3, 30));
        return [
            'status'     => $this->faker->randomElement($statuses),
            'start_at'   => $start,
            'expires_at' => $expires,
        ];
    }
}
