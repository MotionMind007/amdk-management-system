<?php

namespace App\Http\Controllers;

use App\Models\SalesReturn;
use Illuminate\View\View;

class SalesReturnPrintController extends Controller
{
    public function __invoke(SalesReturn $salesReturn): View
    {
        abort_unless($salesReturn->status === 'posted', 404);
        $salesReturn->load([
            'items.product.unit:id,code',
            'sale.customer:id,name,phone,address',
            'sale.warehouse:id,name',
            'refundAccount:id,name',
        ]);

        return view('documents.sales-return', compact('salesReturn'));
    }
}
