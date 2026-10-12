<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class OperationalIntegrityService
{
    /** @return array{healthy: bool, checks: array<string, array{healthy: bool, issues: int, message: string}>} */
    public function check(): array
    {
        $checks = [
            'stock' => $this->result(
                $this->stockMismatchCount(),
                'Saldo stok sesuai dengan histori pergerakan.',
                'saldo stok tidak sesuai dengan histori pergerakan.',
            ),
            'receivables' => $this->result(
                $this->receivableMismatchCount(),
                'Saldo piutang sesuai dengan nilai penjualan, pembayaran, dan retur.',
                'transaksi penjualan memiliki saldo piutang yang tidak konsisten.',
            ),
            'payables' => $this->result(
                $this->payableMismatchCount(),
                'Saldo hutang sesuai dengan invoice supplier, pembayaran, dan retur.',
                'invoice supplier memiliki saldo hutang yang tidak konsisten.',
            ),
            'cash' => $this->result(
                $this->cashMismatchCount(),
                'Saldo kas dan bank sesuai dengan histori transaksi.',
                'akun kas atau bank tidak sesuai dengan histori transaksi.',
            ),
        ];

        return [
            'healthy' => collect($checks)->every(fn (array $check): bool => $check['healthy']),
            'checks' => $checks,
        ];
    }

    private function stockMismatchCount(): int
    {
        $movements = DB::table('stock_movements')
            ->select(['product_id', 'warehouse_id'])
            ->selectRaw("SUM(CASE WHEN direction = 'in' THEN quantity ELSE -quantity END) AS calculated_quantity")
            ->groupBy('product_id', 'warehouse_id');

        $balanceMismatches = DB::table('stock_balances')
            ->leftJoinSub(clone $movements, 'movement_totals', function ($join): void {
                $join->on('movement_totals.product_id', '=', 'stock_balances.product_id')
                    ->on('movement_totals.warehouse_id', '=', 'stock_balances.warehouse_id');
            })
            ->whereRaw('ABS(stock_balances.quantity - COALESCE(movement_totals.calculated_quantity, 0)) > 0.0001')
            ->count();

        $missingBalances = DB::query()
            ->fromSub($movements, 'movement_totals')
            ->leftJoin('stock_balances', function ($join): void {
                $join->on('stock_balances.product_id', '=', 'movement_totals.product_id')
                    ->on('stock_balances.warehouse_id', '=', 'movement_totals.warehouse_id');
            })
            ->whereNull('stock_balances.id')
            ->whereRaw('ABS(movement_totals.calculated_quantity) > 0.0001')
            ->count();

        return $balanceMismatches + $missingBalances;
    }

    private function receivableMismatchCount(): int
    {
        return DB::table('sales')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereRaw('ABS((paid_amount + outstanding_amount + returned_amount) - total) > 0.01')
            ->count();
    }

    private function payableMismatchCount(): int
    {
        $returns = DB::table('purchase_return_items')
            ->join('goods_receipt_items', 'goods_receipt_items.id', '=', 'purchase_return_items.goods_receipt_item_id')
            ->join('supplier_invoices', 'supplier_invoices.goods_receipt_id', '=', 'goods_receipt_items.goods_receipt_id')
            ->select('supplier_invoices.id as supplier_invoice_id')
            ->selectRaw('SUM(purchase_return_items.line_total) AS returned_amount')
            ->groupBy('supplier_invoices.id');

        return DB::table('supplier_invoices')
            ->leftJoinSub($returns, 'return_totals', 'return_totals.supplier_invoice_id', '=', 'supplier_invoices.id')
            ->whereRaw('ABS((supplier_invoices.paid_amount + supplier_invoices.outstanding_amount + COALESCE(return_totals.returned_amount, 0)) - supplier_invoices.total) > 0.01')
            ->count();
    }

    private function cashMismatchCount(): int
    {
        $transactions = DB::table('cash_transactions')
            ->select('cash_account_id')
            ->selectRaw("SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END) AS calculated_balance")
            ->groupBy('cash_account_id');

        return DB::table('cash_accounts')
            ->leftJoinSub($transactions, 'transaction_totals', 'transaction_totals.cash_account_id', '=', 'cash_accounts.id')
            ->whereRaw('ABS(cash_accounts.balance - COALESCE(transaction_totals.calculated_balance, 0)) > 0.01')
            ->count();
    }

    /** @return array{healthy: bool, issues: int, message: string} */
    private function result(int $issues, string $healthyMessage, string $issueMessage): array
    {
        return [
            'healthy' => $issues === 0,
            'issues' => $issues,
            'message' => $issues === 0 ? $healthyMessage : "{$issues} {$issueMessage}",
        ];
    }
}
