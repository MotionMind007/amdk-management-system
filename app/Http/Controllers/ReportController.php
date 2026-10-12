<?php

namespace App\Http\Controllers;

use App\ExpenseCategory;
use App\Models\CashTransaction;
use App\Models\DailyProductionItem;
use App\Models\GoodsReceipt;
use App\Models\PurchaseReturn;
use App\Models\Sale;
use App\Models\SalesReturn;
use App\Models\SupplierInvoice;
use App\ProductionType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['start_date' => ['nullable', 'date'], 'end_date' => ['nullable', 'date', 'after_or_equal:start_date']]);
        $startDate = $data['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $data['end_date'] ?? today()->format('Y-m-d');

        $returnedItems = DB::table('sales_return_items')
            ->select('sale_item_id')
            ->selectRaw('SUM(quantity) as returned_quantity')
            ->selectRaw('SUM(line_total) as returned_total')
            ->groupBy('sale_item_id');

        $productSales = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->join('units', 'units.id', '=', 'products.unit_id')
            ->leftJoinSub($returnedItems, 'returned_items', fn ($join) => $join->on('returned_items.sale_item_id', '=', 'sale_items.id'))
            ->whereNotIn('sales.status', ['draft', 'cancelled'])
            ->whereDate('sales.sale_date', '>=', $startDate)
            ->whereDate('sales.sale_date', '<=', $endDate)
            ->groupBy('products.id', 'products.name', 'units.code')
            ->orderBy('products.name')
            ->select(['products.id', 'products.name', 'units.code as unit_code'])
            ->selectRaw('SUM(sale_items.quantity - COALESCE(returned_items.returned_quantity, 0)) as quantity_sold')
            ->selectRaw('SUM(sale_items.line_total - COALESCE(returned_items.returned_total, 0)) as sales_amount')
            ->get();

        $grossSales = Sale::query()->whereNotIn('status', ['draft', 'cancelled'])->whereDate('sale_date', '>=', $startDate)->whereDate('sale_date', '<=', $endDate)->sum('total');
        $salesReturns = SalesReturn::query()->where('status', 'posted')->whereDate('return_date', '>=', $startDate)->whereDate('return_date', '<=', $endDate)->sum('total');
        $grossPurchases = GoodsReceipt::query()->whereDate('receipt_date', '>=', $startDate)->whereDate('receipt_date', '<=', $endDate)->sum('total');
        $purchaseReturns = PurchaseReturn::query()->where('status', 'posted')->whereDate('return_date', '>=', $startDate)->whereDate('return_date', '<=', $endDate)->sum('total');

        return view('reports.index', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'salesTotal' => (float) $grossSales - (float) $salesReturns,
            'purchaseTotal' => (float) $grossPurchases - (float) $purchaseReturns,
            'cashIn' => CashTransaction::query()->where('direction', 'in')->whereDate('transaction_date', '>=', $startDate)->whereDate('transaction_date', '<=', $endDate)->sum('amount'),
            'cashOut' => CashTransaction::query()->where('direction', 'out')->whereDate('transaction_date', '>=', $startDate)->whereDate('transaction_date', '<=', $endDate)->sum('amount'),
            'receivableTotal' => Sale::query()->sum('outstanding_amount'),
            'payableTotal' => SupplierInvoice::query()->sum('outstanding_amount'),
            'productionTotal' => DailyProductionItem::query()->whereHas('production', fn ($query) => $query->where('production_type', ProductionType::FinishedGood)->where('status', 'posted')->whereDate('production_date', '>=', $startDate)->whereDate('production_date', '<=', $endDate))->sum('quantity'),
            'productSales' => $productSales,
        ]);
    }

    public function profitLoss(Request $request): View
    {
        $data = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);
        $startDate = $data['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $data['end_date'] ?? today()->format('Y-m-d');

        $salesRevenue = Sale::query()
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereDate('sale_date', '>=', $startDate)
            ->whereDate('sale_date', '<=', $endDate)
            ->sum('total');
        $salesReturns = SalesReturn::query()
            ->where('status', 'posted')
            ->whereDate('return_date', '>=', $startDate)
            ->whereDate('return_date', '<=', $endDate)
            ->sum('total');
        $salesRevenue = (float) $salesRevenue - (float) $salesReturns;

        $otherIncome = CashTransaction::query()
            ->where('direction', 'in')
            ->whereNull('source_type')
            ->whereDate('transaction_date', '>=', $startDate)
            ->whereDate('transaction_date', '<=', $endDate)
            ->sum('amount');

        $expenseRows = DB::table('cash_transactions')
            ->where('direction', 'out')
            ->whereNull('source_type')
            ->whereDate('transaction_date', '>=', $startDate)
            ->whereDate('transaction_date', '<=', $endDate)
            ->groupBy('expense_category')
            ->orderBy('expense_category')
            ->select('expense_category')
            ->selectRaw('SUM(amount) as total')
            ->get()
            ->map(function (object $expense): object {
                $category = is_string($expense->expense_category)
                    ? ExpenseCategory::tryFrom($expense->expense_category)
                    : null;

                $expense->label = $category?->label() ?? 'Tanpa Kategori';

                return $expense;
            });

        $operatingExpenses = $expenseRows->sum('total');
        $totalIncome = (float) $salesRevenue + (float) $otherIncome;

        return view('reports.profit-loss', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'salesRevenue' => $salesRevenue,
            'otherIncome' => $otherIncome,
            'totalIncome' => $totalIncome,
            'expenseRows' => $expenseRows,
            'operatingExpenses' => $operatingExpenses,
            'profitBeforeHpp' => $totalIncome - (float) $operatingExpenses,
            'purchaseReceived' => (float) GoodsReceipt::query()
                ->where('status', 'posted')
                ->whereDate('receipt_date', '>=', $startDate)
                ->whereDate('receipt_date', '<=', $endDate)
                ->sum('total') - (float) PurchaseReturn::query()
                ->where('status', 'posted')
                ->whereDate('return_date', '>=', $startDate)
                ->whereDate('return_date', '<=', $endDate)
                ->sum('total'),
        ]);
    }
}
