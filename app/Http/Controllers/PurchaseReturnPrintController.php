<?php

namespace App\Http\Controllers;

use App\Models\PurchaseReturn;
use Illuminate\View\View;

class PurchaseReturnPrintController extends Controller
{
    public function __invoke(PurchaseReturn $purchaseReturn): View
    {
        abort_unless($purchaseReturn->status === 'posted', 404);
        $purchaseReturn->load([
            'items.product.unit:id,code',
            'purchaseOrder.supplier:id,name,phone,address',
            'purchaseOrder.warehouse:id,name',
            'refundAccount:id,name',
        ]);

        return view('documents.purchase-return', compact('purchaseReturn'));
    }
}
