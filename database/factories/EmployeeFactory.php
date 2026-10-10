<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_code' => 'EMP-'.fake()->unique()->numerify('#####'),
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'address' => fake()->address(),
            'position' => fake()->jobTitle(),
            'department' => fake()->randomElement(['Produksi', 'Gudang', 'Penjualan', 'Keuangan']),
            'join_date' => fake()->optional()->dateTimeBetween('-10 years', 'now')?->format('Y-m-d'),
            'status' => 'active',
        ];
    }
}
