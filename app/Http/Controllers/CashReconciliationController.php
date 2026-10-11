<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCashReconciliationRequest;
use App\Models\CashAccount;
use App\Models\CashReconciliation;
use App\Services\AuditService;
use App\Services\CashReconciliationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CashReconciliationController extends Controller
{
    public function index(): View
    {
        return view('finance.reconciliations.index', ['accounts' => CashAccount::query()->where('is_active', true)->orderBy('name')->get(), 'reconciliations' => CashReconciliation::query()->with(['cashAccount:id,name', 'creator:id,name'])->latest('reconciliation_date')->paginate(20)]);
    }

    public function store(StoreCashReconciliationRequest $request, CashReconciliationService $service, AuditService $audit): RedirectResponse
    {
        $data = $request->validated();
        $reconciliation = $service->create(CashAccount::query()->findOrFail($data['cash_account_id']), $data['reconciliation_date'], (string) $data['statement_balance'], $data['notes'] ?? null, $request->user());
        $audit->record($request, 'RECONCILE', 'CashReconciliation', $reconciliation, newValues: $reconciliation->toArray(), description: "Rekonsiliasi {$reconciliation->number}: saldo sistem Rp ".number_format((float) $reconciliation->system_balance, 0, ',', '.').', saldo fisik/rekening Rp '.number_format((float) $reconciliation->statement_balance, 0, ',', '.').'.');

        return back()->with('success', 'Rekonsiliasi berhasil dicatat tanpa mengubah saldo sistem.');
    }
}
