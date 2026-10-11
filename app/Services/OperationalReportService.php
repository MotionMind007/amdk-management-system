<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationalReportService
{
    /** @return array{title: string, query: Builder, date_column: string, quantity_label: string, amount_label: string} */
    public function report(string $type, array $filters): array
    {
        $report = match ($type) {
            'sales' => $this->sales(),
            'purchases' => $this->purchases(),
            'stock' => $this->stock(),
            'production' => $this->production(),
            'cash' => $this->cash(),
            'receivables' => $this->receivables(),
            'payables' => $this->payables(),
            default => throw ValidationException::withMessages(['report' => 'Jenis laporan tidak tersedia.']),
        };

        $startDate = $filters['start_date'] ?? now()->startOfMonth()->toDateString();
        $endDate = $filters['end_date'] ?? today()->toDateString();
        $report['query']->whereDate($report['date_column'], '>=', $startDate)->whereDate($report['date_column'], '<=', $endDate);

        if (filled($filters['q'] ?? null)) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $filters['q']).'%';
            $report['query']->where(function (Builder $query) use ($term, $report): void {
                $query->where($report['search_columns'][0], 'like', $term)->orWhere($report['search_columns'][1], 'like', $term);
            });
        }

        if (filled($filters['warehouse_id'] ?? null) && in_array($type, ['sales', 'purchases', 'stock', 'production'], true)) {
            $report['query']->where($report['warehouse_filter_column'], $filters['warehouse_id']);
        }

        return [...$report, 'start_date' => $startDate, 'end_date' => $endDate];
    }

    private function sales(): array
    {
        return ['title' => 'Laporan Penjualan', 'date_column' => 'sales.sale_date', 'search_columns' => ['sales.number', 'customers.name'], 'warehouse_filter_column' => 'sales.warehouse_id', 'quantity_label' => 'Item', 'amount_label' => 'Nilai Penjualan', 'query' => DB::table('sales')->join('customers', 'customers.id', '=', 'sales.customer_id')->join('warehouses', 'warehouses.id', '=', 'sales.warehouse_id')->whereNotIn('sales.status', ['draft', 'cancelled'])->selectRaw('sales.sale_date as report_date, sales.number as reference, customers.name as party, warehouses.name as warehouse, warehouses.id as warehouse_id, sales.status, (select count(*) from sale_items where sale_items.sale_id = sales.id) as quantity, sales.total as amount, sales.outstanding_amount as outstanding')];
    }

    private function purchases(): array
    {
        return ['title' => 'Laporan Penerimaan Pembelian', 'date_column' => 'goods_receipts.receipt_date', 'search_columns' => ['goods_receipts.number', 'suppliers.name'], 'warehouse_filter_column' => 'purchase_orders.warehouse_id', 'quantity_label' => 'Item', 'amount_label' => 'Nilai Diterima', 'query' => DB::table('goods_receipts')->join('purchase_orders', 'purchase_orders.id', '=', 'goods_receipts.purchase_order_id')->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')->join('warehouses', 'warehouses.id', '=', 'purchase_orders.warehouse_id')->where('goods_receipts.status', 'posted')->selectRaw('goods_receipts.receipt_date as report_date, goods_receipts.number as reference, suppliers.name as party, warehouses.name as warehouse, warehouses.id as warehouse_id, goods_receipts.status, (select count(*) from goods_receipt_items where goods_receipt_items.goods_receipt_id = goods_receipts.id) as quantity, goods_receipts.total as amount, 0 as outstanding')];
    }

    private function stock(): array
    {
        return ['title' => 'Laporan Pergerakan Stok', 'date_column' => 'stock_movements.occurred_at', 'search_columns' => ['stock_movements.reference_number', 'products.name'], 'warehouse_filter_column' => 'stock_movements.warehouse_id', 'quantity_label' => 'Jumlah', 'amount_label' => 'Saldo Akhir', 'query' => DB::table('stock_movements')->join('products', 'products.id', '=', 'stock_movements.product_id')->join('warehouses', 'warehouses.id', '=', 'stock_movements.warehouse_id')->selectRaw("stock_movements.occurred_at as report_date, COALESCE(stock_movements.reference_number, '-') as reference, products.name as party, warehouses.name as warehouse, warehouses.id as warehouse_id, stock_movements.type as status, CASE WHEN stock_movements.direction = 'out' THEN -stock_movements.quantity ELSE stock_movements.quantity END as quantity, stock_movements.balance_after as amount, 0 as outstanding")];
    }

    private function production(): array
    {
        return ['title' => 'Laporan Produksi', 'date_column' => 'daily_productions.production_date', 'search_columns' => ['daily_productions.number', 'daily_productions.number'], 'warehouse_filter_column' => 'daily_productions.warehouse_id', 'quantity_label' => 'Jumlah Hasil', 'amount_label' => 'Nilai', 'query' => DB::table('daily_productions')->join('warehouses', 'warehouses.id', '=', 'daily_productions.warehouse_id')->join('daily_production_items', 'daily_production_items.daily_production_id', '=', 'daily_productions.id')->where('daily_productions.status', 'posted')->groupBy('daily_productions.id', 'daily_productions.production_date', 'daily_productions.number', 'warehouses.name', 'warehouses.id', 'daily_productions.status')->selectRaw("daily_productions.production_date as report_date, daily_productions.number as reference, 'Produksi' as party, warehouses.name as warehouse, warehouses.id as warehouse_id, daily_productions.status, SUM(daily_production_items.quantity) as quantity, 0 as amount, 0 as outstanding")];
    }

    private function cash(): array
    {
        return ['title' => 'Laporan Kas dan Bank', 'date_column' => 'cash_transactions.transaction_date', 'search_columns' => ['cash_transactions.reference_number', 'cash_accounts.name'], 'warehouse_filter_column' => null, 'quantity_label' => 'Arah', 'amount_label' => 'Nominal', 'query' => DB::table('cash_transactions')->join('cash_accounts', 'cash_accounts.id', '=', 'cash_transactions.cash_account_id')->selectRaw("cash_transactions.transaction_date as report_date, cash_transactions.reference_number as reference, cash_accounts.name as party, '-' as warehouse, NULL as warehouse_id, cash_transactions.direction as status, 0 as quantity, CASE WHEN cash_transactions.direction = 'out' THEN -cash_transactions.amount ELSE cash_transactions.amount END as amount, 0 as outstanding")];
    }

    private function receivables(): array
    {
        return ['title' => 'Laporan Piutang', 'date_column' => 'sales.sale_date', 'search_columns' => ['sales.number', 'customers.name'], 'warehouse_filter_column' => null, 'quantity_label' => 'Hari Lewat', 'amount_label' => 'Total Faktur', 'query' => DB::table('sales')->join('customers', 'customers.id', '=', 'sales.customer_id')->where('sales.outstanding_amount', '>', 0)->selectRaw("sales.sale_date as report_date, sales.number as reference, customers.name as party, COALESCE(CAST(sales.due_date AS TEXT), '-') as warehouse, NULL as warehouse_id, sales.status, 0 as quantity, sales.total as amount, sales.outstanding_amount as outstanding")];
    }

    private function payables(): array
    {
        return ['title' => 'Laporan Hutang', 'date_column' => 'supplier_invoices.invoice_date', 'search_columns' => ['supplier_invoices.number', 'suppliers.name'], 'warehouse_filter_column' => null, 'quantity_label' => 'Hari Lewat', 'amount_label' => 'Total Faktur', 'query' => DB::table('supplier_invoices')->join('purchase_orders', 'purchase_orders.id', '=', 'supplier_invoices.purchase_order_id')->join('suppliers', 'suppliers.id', '=', 'purchase_orders.supplier_id')->where('supplier_invoices.outstanding_amount', '>', 0)->selectRaw("supplier_invoices.invoice_date as report_date, supplier_invoices.number as reference, suppliers.name as party, COALESCE(CAST(supplier_invoices.due_date AS TEXT), '-') as warehouse, NULL as warehouse_id, supplier_invoices.status, 0 as quantity, supplier_invoices.total as amount, supplier_invoices.outstanding_amount as outstanding")];
    }
}
