<?php

namespace Database\Factories;

use App\Models\AccountingPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountingPeriod>
 */
class AccountingPeriodFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'period' => fake()->unique()->dateTimeBetween('-3 years')->format('Y-m'),
            'status' => 'closed',
            'notes' => null,
            'closed_by' => User::factory(),
            'closed_at' => now(),
            'reopened_by' => null,
            'reopened_at' => null,
        ];
    }
}
