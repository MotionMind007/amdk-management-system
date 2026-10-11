<?php

namespace App\Http\Controllers;

use App\Http\Requests\PurchaseReturnRequest;
use App\Models\CashAccount;
use App\Models\GoodsReceipt;
use App\Services\AuditService;
use App\Services\PurchaseReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PurchaseReturnController extends Controller
{
    public function create(GoodsReceipt $goodsReceipt): View
    {
        abort_unless($goodsReceipt->status === 'posted', 422);
        $goodsReceipt->load([
            'items' => fn ($query) => $query->withSum('returnItems', 'quantity'),
            'items.product.unit',
            'purchaseOrder.supplier',
            'purchaseOrder.warehouse',
        ]);

        return view('purchasing.returns.create', [
            'goodsReceipt' => $goodsReceipt,
            'cashAccounts' => CashAccount::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(
        PurchaseReturnRequest $request,
        GoodsReceipt $goodsReceipt,
        PurchaseReturnService $returns,
        AuditService $audit,
    ): RedirectResponse {
        $data = $request->validated();
        $purchaseReturn = $returns->post(
            $goodsReceipt,
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
            'PurchaseReturn',
            $purchaseReturn,
            newValues: $purchaseReturn->toArray(),
            description: "Memposting retur pembelian {$purchaseReturn->number} untuk {$goodsReceipt->number} senilai Rp ".number_format((float) $purchaseReturn->total, 0, ',', '.').". Alasan: {$purchaseReturn->reason}.",
        );

        return redirect()->route('purchasing.index')->with('success', "Retur {$purchaseReturn->number} diposting; stok dan saldo transaksi telah diperbarui.");
    }
}
