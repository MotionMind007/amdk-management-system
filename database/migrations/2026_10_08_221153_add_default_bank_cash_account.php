<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::table('cash_accounts')->where('code', 'BANK')->exists()) {
            return;
        }

        DB::table('cash_accounts')->insert([
            'code' => 'BANK',
            'name' => 'Bank',
            'type' => 'bank',
            'balance' => 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('cash_accounts')
            ->where('code', 'BANK')
            ->where('balance', 0)
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('cash_transactions')->whereColumn('cash_transactions.cash_account_id', 'cash_accounts.id'))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('sales')->whereColumn('sales.cash_account_id', 'cash_accounts.id'))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('purchase_orders')->whereColumn('purchase_orders.cash_account_id', 'cash_accounts.id'))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('customer_payments')->whereColumn('customer_payments.cash_account_id', 'cash_accounts.id'))
            ->whereNotExists(fn ($query) => $query->selectRaw('1')->from('supplier_payments')->whereColumn('supplier_payments.cash_account_id', 'cash_accounts.id'))
            ->delete();
    }
};
