<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalesReturnRequest;
use App\Models\CashAccount;
use App\Models\Sale;
use App\Services\AuditService;
use App\Services\SalesReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SalesReturnController extends Controller
{
    public function create(Sale $sale): View
    {
        abort_if(in_array($sale->status, ['draft', 'cancelled'], true), 422);
        $sale->load([
            'customer',
            'warehouse',
            'items' => fn ($query) => $query->withSum('returnItems', 'quantity')->withSum('deliveryNoteItems', 'quantity'),
            'items.product.unit',
        ]);

        return view('sales.returns.create', [
            'sale' => $sale,
            'cashAccounts' => CashAccount::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(
        SalesReturnRequest $request,
        Sale $sale,
        SalesReturnService $returns,
        AuditService $audit,
    ): RedirectResponse {
        $data = $request->validated();
        $salesReturn = $returns->post(
            $sale,
            $data['quantities'],
            $data['return_date'],
            $data['reason'],
            isset($data['refund_account_id']) ? (int) $data['refund_account_id'] : null,
            $request->user(),
            $data['submission_token'] ?? null,
        );
        $audit->record(
            $request,
            'RETURN',
            'SalesReturn',
            $salesReturn,
            newValues: $salesReturn->toArray(),
            description: "Memposting retur penjualan {$salesReturn->number} untuk {$sale->number} senilai Rp ".number_format((float) $salesReturn->total, 0, ',', '.').". Alasan: {$salesReturn->reason}.",
        );

        return redirect()->route('sales.index')->with('success', "Retur {$salesReturn->number} diposting; stok dan saldo transaksi telah diperbarui.");
    }
}
