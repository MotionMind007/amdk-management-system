<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Services\AuditService;
use App\Services\PurchasingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class GoodsReceiptController extends Controller
{
    public function create(PurchaseOrder $purchaseOrder): View
    {
        abort_unless(in_array($purchaseOrder->status, ['approved', 'partially_received'], true), 422);
        $purchaseOrder->load(['supplier', 'items.product.unit']);

        return view('purchasing.receive', compact('purchaseOrder'));
    }

    public function store(Request $request, PurchaseOrder $purchaseOrder, PurchasingService $purchasing, AuditService $audit): RedirectResponse
    {
        $data = $request->validate(['quantities' => ['required', 'array'], 'quantities.*' => ['nullable', 'numeric', 'min:0']]);
        DB::transaction(function () use ($request, $purchaseOrder, $data, $purchasing, $audit): void {
            $receipt = $purchasing->receive($purchaseOrder, $data['quantities'], $request->user());
            $audit->record($request, 'RECEIVE', 'GoodsReceipt', $receipt, newValues: $receipt->toArray());
        });

        return redirect()->route('purchasing.index')->with('success', 'Penerimaan diposting dan stok bahan telah bertambah.');
    }
}
