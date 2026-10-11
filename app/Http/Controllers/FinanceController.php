<?php

namespace App\Http\Controllers;

use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Models\Sale;
use App\Models\SupplierInvoice;
use Illuminate\View\View;

class FinanceController extends Controller
{
    public function index(): View
    {
        return view('finance.index', [
            'accounts' => CashAccount::query()->where('is_active', true)->orderBy('name')->get(),
            'transactions' => CashTransaction::query()->latest('transaction_date')->latest('id')->paginate(15),
            'receivables' => Sale::query()->where('outstanding_amount', '>', 0)->with(['customer:id,name', 'payments.cashAccount'])->oldest('due_date')->limit(10)->get(),
            'payables' => SupplierInvoice::query()->where('outstanding_amount', '>', 0)->with(['purchaseOrder.supplier:id,name', 'payments.cashAccount'])->oldest('due_date')->limit(10)->get(),
        ]);
    }
}
