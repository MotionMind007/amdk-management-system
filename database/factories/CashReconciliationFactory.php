<?php

namespace Database\Factories;

use App\Models\CashAccount;
use App\Models\CashReconciliation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashReconciliation>
 */
class CashReconciliationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'number' => 'RECON-'.fake()->unique()->numerify('########'),
            'cash_account_id' => CashAccount::factory(),
            'reconciliation_date' => today(),
            'system_balance' => 100000,
            'statement_balance' => 100000,
            'difference' => 0,
            'notes' => null,
            'created_by' => User::factory(),
        ];
    }
}
