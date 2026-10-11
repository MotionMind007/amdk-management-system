<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\StockBalance;
use App\Models\SupplierInvoice;
use App\Models\User;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        /** @var User $user */
        $user = request()->user();
        $todaySales = Sale::query()->whereDate('sale_date', today())->whereNot('status', 'draft')->sum('total');
        $receivables = Sale::query()->sum('outstanding_amount');
        $payables = SupplierInvoice::query()->sum('outstanding_amount');
        $lowStockCount = StockBalance::query()->join('products', 'products.id', '=', 'stock_balances.product_id')->whereColumn('stock_balances.quantity', '<', 'products.minimum_stock')->where('products.is_active', true)->count();
        $stats = [];

        if ($user->hasPermission('sales.view')) {
            $stats[] = ['label' => 'Penjualan hari ini', 'value' => 'Rp '.number_format((float) $todaySales, 0, ',', '.'), 'tone' => 'blue'];
            $stats[] = ['label' => 'Piutang berjalan', 'value' => 'Rp '.number_format((float) $receivables, 0, ',', '.'), 'tone' => 'orange'];
        }
        if ($user->hasPermission('finance.view')) {
            $stats[] = ['label' => 'Hutang berjalan', 'value' => 'Rp '.number_format((float) $payables, 0, ',', '.'), 'tone' => 'orange'];
        }
        if ($user->hasPermission('inventory.view')) {
            $stats[] = ['label' => 'Stok kritis', 'value' => $lowStockCount.' item', 'tone' => 'red'];
        }

        $tasks = collect();
        if ($user->hasPermission('purchasing.approve')) {
            $tasks->push(['label' => 'PO menunggu persetujuan', 'count' => PurchaseOrder::query()->where('status', 'pending_approval')->count(), 'route' => 'purchasing.index']);
        }
        if ($user->hasPermission('purchasing.receive')) {
            $tasks->push(['label' => 'PO menunggu penerimaan', 'count' => PurchaseOrder::query()->whereIn('status', ['approved', 'partially_received'])->count(), 'route' => 'purchasing.index']);
        }
        if ($user->hasPermission('sales.deliver')) {
            $tasks->push(['label' => 'Penjualan menunggu pengiriman', 'count' => Sale::query()->whereNotIn('status', ['draft', 'cancelled'])->whereIn('delivery_status', ['pending', 'partial'])->count(), 'route' => 'sales.deliveries.index']);
        }
        if ($user->hasPermission('finance.view')) {
            $tasks->push(['label' => 'Tagihan melewati jatuh tempo', 'count' => SupplierInvoice::query()->where('outstanding_amount', '>', 0)->whereDate('due_date', '<', today())->count(), 'route' => 'finance.aging']);
        }

        return view('dashboard', [
            'stats' => $stats,
            'tasks' => $tasks->filter(fn (array $task): bool => $task['count'] > 0),
            'latestSales' => $user->hasPermission('sales.view') ? Sale::query()->with('customer:id,name')->whereNot('status', 'draft')->latest('sale_date')->latest('id')->limit(5)->get() : collect(),
            'lowStocks' => $user->hasPermission('inventory.view') ? StockBalance::query()->select('stock_balances.*')->join('products', 'products.id', '=', 'stock_balances.product_id')->whereColumn('stock_balances.quantity', '<', 'products.minimum_stock')->where('products.is_active', true)->with(['product.unit:id,code', 'warehouse:id,name'])->orderBy('stock_balances.quantity')->limit(5)->get() : collect(),
            'canViewSales' => $user->hasPermission('sales.view'),
            'canViewInventory' => $user->hasPermission('inventory.view'),
        ]);
    }
}
