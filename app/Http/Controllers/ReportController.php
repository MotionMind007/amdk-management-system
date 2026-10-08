<?php

namespace App\Http\Controllers;

use App\Models\CashTransaction;
use App\Models\DailyProductionItem;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $data = $request->validate(['start_date' => ['nullable', 'date'], 'end_date' => ['nullable', 'date', 'after_or_equal:start_date']]);
        $startDate = $data['start_date'] ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $data['end_date'] ?? today()->format('Y-m-d');

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
        ]);
    }
}
