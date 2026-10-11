<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDeliveryNoteRequest;
use App\Models\DeliveryNote;
use App\Models\Sale;
use App\Services\AuditService;
use App\Services\DeliveryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DeliveryNoteController extends Controller
{
    public function index(): View
    {
        $sales = Sale::query()
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereIn('delivery_status', ['pending', 'partial'])
            ->with(['customer:id,name', 'warehouse:id,name'])
            ->oldest('sale_date')
            ->paginate(20);
        $deliveryNotes = DeliveryNote::query()
            ->with(['sale.customer:id,name', 'warehouse:id,name'])
            ->latest('delivery_date')
            ->latest('id')
            ->limit(20)
            ->get();

        return view('sales.deliveries.index', compact('sales', 'deliveryNotes'));
    }

    public function create(Sale $sale): View
    {
        abort_if(in_array($sale->status, ['draft', 'cancelled'], true) || $sale->delivery_status === 'complete', 422);
        $sale->load([
            'customer',
            'warehouse',
            'items' => fn ($query) => $query->withSum('deliveryNoteItems', 'quantity'),
            'items.product.unit',
        ]);

        return view('sales.deliveries.create', compact('sale'));
    }

    public function store(
        StoreDeliveryNoteRequest $request,
        Sale $sale,
        DeliveryService $deliveries,
        AuditService $audit,
    ): RedirectResponse {
        $data = $request->validated();
        $deliveryNote = $deliveries->post($sale, $data['quantities'], $data['delivery_date'], $data['notes'] ?? null, $request->user(), $data['submission_token'] ?? null);
        $audit->record(
            $request,
            'POST',
            'DeliveryNote',
            $deliveryNote,
            newValues: $deliveryNote->toArray(),
            description: "Memposting surat jalan {$deliveryNote->number} untuk penjualan {$sale->number}; stok gudang berkurang sesuai barang yang dikirim.",
        );

        return redirect()->route('sales.deliveries.index')->with('success', "Surat jalan {$deliveryNote->number} diposting dan stok telah diperbarui.");
    }
}
