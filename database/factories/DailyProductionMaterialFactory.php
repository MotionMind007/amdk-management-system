<?php

namespace Database\Factories;

use App\Models\DailyProduction;
use App\Models\DailyProductionMaterial;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyProductionMaterial>
 */
class DailyProductionMaterialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'daily_production_id' => DailyProduction::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->numberBetween(1, 1000),
        ];
    }
}
