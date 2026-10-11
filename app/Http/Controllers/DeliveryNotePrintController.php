<?php

namespace App\Http\Controllers;

use App\Models\DeliveryNote;
use Illuminate\View\View;

class DeliveryNotePrintController extends Controller
{
    public function __invoke(DeliveryNote $deliveryNote): View
    {
        $deliveryNote->load(['sale.customer', 'warehouse', 'items.product.unit']);

        return view('documents.delivery-note', compact('deliveryNote'));
    }
}
