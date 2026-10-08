<?php

namespace App\Http\Controllers;

use App\Models\CashTransaction;
use App\Models\DailyProductionItem;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\Sale;
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

        $productSales = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->join('units', 'units.id', '=', 'products.unit_id')
            ->whereNot('sales.status', 'draft')
            ->whereDate('sales.sale_date', '>=', $startDate)
            ->whereDate('sales.sale_date', '<=', $endDate)
            ->groupBy('products.id', 'products.name', 'units.code')
            ->orderBy('products.name')
            ->select(['products.id', 'products.name', 'units.code as unit_code'])
            ->selectRaw('SUM(sale_items.quantity) as quantity_sold')
            ->selectRaw('SUM(sale_items.line_total) as sales_amount')
            ->get();

        return view('reports.index', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'salesTotal' => Sale::query()->whereNot('status', 'draft')->whereDate('sale_date', '>=', $startDate)->whereDate('sale_date', '<=', $endDate)->sum('total'),
            'purchaseTotal' => GoodsReceipt::query()->whereDate('receipt_date', '>=', $startDate)->whereDate('receipt_date', '<=', $endDate)->sum('total'),
            'cashIn' => CashTransaction::query()->where('direction', 'in')->whereDate('transaction_date', '>=', $startDate)->whereDate('transaction_date', '<=', $endDate)->sum('amount'),
            'cashOut' => CashTransaction::query()->where('direction', 'out')->whereDate('transaction_date', '>=', $startDate)->whereDate('transaction_date', '<=', $endDate)->sum('amount'),
            'receivableTotal' => Sale::query()->sum('outstanding_amount'),
            'payableTotal' => PurchaseOrder::query()->sum('outstanding_amount'),
            'productionTotal' => DailyProductionItem::query()->whereHas('production', fn ($query) => $query->where('status', 'posted')->whereDate('production_date', '>=', $startDate)->whereDate('production_date', '<=', $endDate))->sum('quantity'),
            'productSales' => $productSales,
        ]);
    }
}
