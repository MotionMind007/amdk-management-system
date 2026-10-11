<?php

namespace App\Http\Controllers;

use App\Models\SupplierInvoice;
use Illuminate\View\View;

class SupplierInvoicePrintController extends Controller
{
    public function __invoke(SupplierInvoice $supplierInvoice): View
    {
        $supplierInvoice->load(['purchaseOrder.supplier', 'goodsReceipt.items.product.unit', 'payments.cashAccount']);

        return view('documents.supplier-invoice', compact('supplierInvoice'));
    }
}
