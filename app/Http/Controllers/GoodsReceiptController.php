<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGoodsReceiptRequest;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Services\AuditService;
use App\Services\PurchasingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class GoodsReceiptController extends Controller
{
    public function create(PurchaseOrder $purchaseOrder): View
    {
        abort_unless(in_array($purchaseOrder->status, ['approved', 'partially_received'], true), 422);
        $purchaseOrder->load(['supplier', 'items.product.unit']);

        return view('purchasing.receive', compact('purchaseOrder'));
    }

    public function store(StoreGoodsReceiptRequest $request, PurchaseOrder $purchaseOrder, PurchasingService $purchasing, AuditService $audit): RedirectResponse
    {
        $data = $request->validated();
        $proofPath = $request->file('proof')->store('goods-receipts');

        try {
            DB::transaction(function () use ($request, $purchaseOrder, $data, $proofPath, $purchasing, $audit): void {
                $receipt = $purchasing->receive($purchaseOrder, $data['quantities'], $request->user());
                $receipt->update(['proof_path' => $proofPath]);
                $audit->record($request, 'RECEIVE', 'GoodsReceipt', $receipt, newValues: $receipt->fresh()->toArray());
            });
        } catch (Throwable $exception) {
            Storage::delete($proofPath);

            throw $exception;
        }

        return redirect()->route('purchasing.index')->with('success', 'Penerimaan diposting dan stok bahan telah bertambah.');
    }

    public function proof(GoodsReceipt $goodsReceipt): StreamedResponse
    {
        abort_if($goodsReceipt->proof_path === null || ! Storage::exists($goodsReceipt->proof_path), 404);

        return Storage::download($goodsReceipt->proof_path, 'bukti-'.$goodsReceipt->number.'.'.pathinfo($goodsReceipt->proof_path, PATHINFO_EXTENSION));
    }
}
