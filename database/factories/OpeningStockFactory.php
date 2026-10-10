<?php

namespace Database\Factories;

use App\Models\OpeningStock;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpeningStock>
 */
class OpeningStockFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'OPEN-'.fake()->unique()->numerify('######'),
            'stock_date' => today(),
            'warehouse_id' => Warehouse::factory(),
            'status' => 'draft',
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
