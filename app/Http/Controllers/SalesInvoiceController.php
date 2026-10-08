<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\View\View;

class SalesInvoiceController extends Controller
{
    public function __invoke(Sale $sale): View
    {
        abort_if($sale->status === 'draft', 404);

        $sale->load([
            'cashAccount:id,name',
            'customer:id,name,phone,address',
            'items.product.unit:id,code',
            'warehouse:id,name',
        ]);

        return view('documents.sales-invoice', compact('sale'));
    }
}
