<?php

namespace Database\Factories;

use App\Models\StockOpname;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockOpname>
 */
class StockOpnameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'OPN-'.fake()->unique()->numerify('######'),
            'period' => today()->format('Y-m'),
            'opname_date' => today(),
            'warehouse_id' => Warehouse::factory(),
            'status' => 'draft',
            'notes' => fake()->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
