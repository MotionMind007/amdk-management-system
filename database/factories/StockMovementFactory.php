<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\StockMovementType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'warehouse_id' => Warehouse::factory(),
            'user_id' => null,
            'type' => StockMovementType::OpeningBalance,
            'direction' => 'in',
            'quantity' => 10,
            'balance_before' => 0,
            'balance_after' => 10,
            'reference_number' => null,
            'notes' => null,
            'occurred_at' => now(),
        ];
    }
}
