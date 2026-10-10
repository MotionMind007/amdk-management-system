<?php

namespace Database\Factories;

use App\Models\OpeningStock;
use App\Models\OpeningStockItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpeningStockItem>
 */
class OpeningStockItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'opening_stock_id' => OpeningStock::factory(),
            'product_id' => Product::factory(),
            'quantity' => fake()->randomFloat(3, 1, 1000),
        ];
    }
}
