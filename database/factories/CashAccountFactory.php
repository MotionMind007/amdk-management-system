<?php

namespace Database\Factories;

use App\Models\CashAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashAccount>
 */
class CashAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'CASH-'.fake()->unique()->numerify('####'),
            'name' => fake()->unique()->company().' Bank',
            'type' => fake()->randomElement(['cash', 'bank']),
            'balance' => 0,
            'is_active' => true,
        ];
    }
}
