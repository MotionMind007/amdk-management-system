<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use Illuminate\View\View;

class PurchaseOrderPrintController extends Controller
{
    public function __invoke(PurchaseOrder $purchaseOrder): View
    {
        abort_if($purchaseOrder->status === 'cancelled', 404);

        $purchaseOrder->load([
            'cashAccount:id,name',
            'items.product.unit:id,code',
            'supplier:id,name,phone,address',
            'warehouse:id,name,address',
        ]);

        return view('documents.purchase-order', compact('purchaseOrder'));
    }
}
