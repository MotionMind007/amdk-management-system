<?php

namespace App\Http\Controllers;

use App\ExpenseCategory;
use App\Http\Requests\StoreManualCashTransactionRequest;
use App\Models\CashAccount;
use App\Models\CashTransaction;
use App\Services\AuditService;
use App\Services\DocumentNumberService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ManualCashTransactionController extends Controller
{
    public function create(): View
    {
        return view('finance.manual-transaction', [
            'accounts' => CashAccount::query()->where('is_active', true)->orderBy('name')->get(),
            'expenseCategories' => ExpenseCategory::cases(),
        ]);
    }

    public function store(StoreManualCashTransactionRequest $request, DocumentNumberService $numbers, AuditService $audit): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($request, $data, $numbers, $audit): void {
            $account = CashAccount::query()->whereKey($data['cash_account_id'])->lockForUpdate()->firstOrFail();

            if ($data['direction'] === 'out' && bccomp($account->balance, (string) $data['amount'], 2) === -1) {
                throw ValidationException::withMessages(['amount' => 'Saldo kas atau bank tidak mencukupi.']);
            }

            $transaction = CashTransaction::create([
                'cash_account_id' => $account->id,
                'transaction_date' => $data['transaction_date'],
                'direction' => $data['direction'],
                'expense_category' => $data['expense_category'] ?? null,
                'amount' => $data['amount'],
                'reference_number' => $numbers->next($data['direction'] === 'in' ? 'CASH-IN' : 'CASH-OUT'),
                'description' => $data['description'],
                'user_id' => $request->user()->id,
            ]);

            $data['direction'] === 'in'
                ? $account->increment('balance', $data['amount'])
                : $account->decrement('balance', $data['amount']);

            $directionLabel = $transaction->direction === 'out' ? 'Pengeluaran' : 'Pemasukan';
            $categoryLabel = $transaction->expense_category?->label();
            $auditDescription = "{$directionLabel} Rp ".number_format((float) $transaction->amount, 0, ',', '.')." melalui {$account->name}";
            $auditDescription .= $categoryLabel ? ". Kategori: {$categoryLabel}" : '';
            $auditDescription .= ". Keterangan: {$transaction->description}. Referensi: {$transaction->reference_number}.";

            $audit->record($request, 'CREATE', 'Finance', $transaction, newValues: $transaction->toArray(), description: $auditDescription);
        });

        return redirect()->route('finance.index')->with('success', 'Transaksi kas berhasil dicatat.');
    }
}
