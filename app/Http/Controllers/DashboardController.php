<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\StockBalance;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $todaySales = Sale::query()->whereDate('sale_date', today())->whereNot('status', 'draft')->sum('total');
        $receivables = Sale::query()->sum('outstanding_amount');
        $payables = PurchaseOrder::query()->sum('outstanding_amount');
        $lowStockCount = StockBalance::query()->join('products', 'products.id', '=', 'stock_balances.product_id')->whereColumn('stock_balances.quantity', '<', 'products.minimum_stock')->where('products.is_active', true)->count();

        return view('dashboard', [
            'stats' => [
                ['label' => 'Penjualan hari ini', 'value' => 'Rp '.number_format((float) $todaySales, 0, ',', '.'), 'tone' => 'blue'],
                ['label' => 'Piutang berjalan', 'value' => 'Rp '.number_format((float) $receivables, 0, ',', '.'), 'tone' => 'orange'],
                ['label' => 'Hutang berjalan', 'value' => 'Rp '.number_format((float) $payables, 0, ',', '.'), 'tone' => 'orange'],
                ['label' => 'Stok kritis', 'value' => $lowStockCount.' item', 'tone' => 'red'],
            ],
            'latestSales' => Sale::query()->with('customer:id,name')->whereNot('status', 'draft')->latest('sale_date')->latest('id')->limit(5)->get(),
        ]);
    }
}
