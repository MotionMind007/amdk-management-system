<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAccountingPeriodRequest;
use App\Models\AccountingPeriod;
use App\Services\AccountingPeriodService;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountingPeriodController extends Controller
{
    public function index(): View
    {
        return view('finance.periods.index', [
            'periods' => AccountingPeriod::query()
                ->whereNotNull('closed_by')
                ->with('closer:id,name')
                ->latest('period')
                ->paginate(24),
        ]);
    }

    public function store(StoreAccountingPeriodRequest $request, AccountingPeriodService $service, AuditService $audit): RedirectResponse
    {
        $period = $service->close($request->string('period')->toString(), $request->input('notes'), $request->user());
        $audit->record($request, 'CLOSE', 'AccountingPeriod', $period, newValues: $period->toArray(), description: "Menutup periode akuntansi {$period->period}. Transaksi pada periode tersebut dikunci.");

        return back()->with('success', "Periode {$period->period} berhasil ditutup.");
    }

    public function reopen(Request $request, AccountingPeriod $accountingPeriod, AccountingPeriodService $service, AuditService $audit): RedirectResponse
    {
        $period = $service->reopen($accountingPeriod, $request->user());
        $audit->record($request, 'REOPEN', 'AccountingPeriod', $period, newValues: $period->toArray(), description: "Membuka kembali periode akuntansi {$period->period}.");

        return back()->with('success', "Periode {$period->period} dibuka kembali.");
    }
}
