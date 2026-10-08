<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\Unit;
use App\ProductType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sku' => 'SKU-'.fake()->unique()->numerify('#####'),
            'name' => fake()->words(3, true),
            'unit_id' => Unit::factory(),
            'type' => ProductType::RawMaterial,
            'minimum_stock' => 0,
            'maximum_stock' => null,
            'purchase_price' => 0,
            'selling_price' => 0,
            'is_active' => true,
            'notes' => null,
        ];
    }
}
