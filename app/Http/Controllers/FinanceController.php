<?php

namespace App\Http\Controllers;

use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function index(): View
    {
        return view('finance.index', [
            'accounts' => CashAccount::query()->where('is_active', true)->orderBy('name')->get(),
            'transactions' => CashTransaction::query()->latest('transaction_date')->latest('id')->paginate(15),
            'receivables' => Sale::query()->where('outstanding_amount', '>', 0)->with('customer:id,name')->oldest('due_date')->limit(10)->get(),
            'payables' => PurchaseOrder::query()->where('outstanding_amount', '>', 0)->with('supplier:id,name')->oldest('expected_date')->limit(10)->get(),
        ]);
    }
}
