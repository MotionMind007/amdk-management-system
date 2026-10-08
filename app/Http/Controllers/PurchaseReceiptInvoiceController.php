<?php

namespace App\Http\Controllers;

use App\Models\GoodsReceipt;
use Illuminate\View\View;

class PurchaseReceiptInvoiceController extends Controller
{
    public function __invoke(GoodsReceipt $goodsReceipt): View
    {
        abort_unless($goodsReceipt->status === 'posted', 404);

        $goodsReceipt->load([
            'items.product.unit:id,code',
            'purchaseOrder.cashAccount:id,name',
            'purchaseOrder.supplier:id,name,phone,address',
            'purchaseOrder.warehouse:id,name',
        ]);

        return view('documents.purchase-receipt', compact('goodsReceipt'));
    }
}
