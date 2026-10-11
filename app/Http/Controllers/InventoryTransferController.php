<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInventoryTransferRequest;
use App\Models\InventoryTransfer;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\AuditService;
use App\Services\DocumentNumberService;
use App\Services\InventoryTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class InventoryTransferController extends Controller
{
    public function index(): View
    {
        return view('inventory.transfers.index', ['transfers' => InventoryTransfer::query()->with(['sourceWarehouse:id,name', 'destinationWarehouse:id,name', 'items'])->latest('transfer_date')->paginate(20)]);
    }

    public function create(): View
    {
        return view('inventory.transfers.create', ['warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get(), 'products' => Product::query()->where('is_active', true)->with('unit')->orderBy('name')->get(), 'submissionToken' => (string) Str::uuid()]);
    }

    public function store(StoreInventoryTransferRequest $request, DocumentNumberService $numbers, AuditService $audit): RedirectResponse
    {
        $data = $request->validated();
        $transfer = DB::transaction(function () use ($request, $data, $numbers, $audit): InventoryTransfer {
            $transfer = InventoryTransfer::query()->create(['number' => $numbers->next('TRF'), 'source_warehouse_id' => $data['source_warehouse_id'], 'destination_warehouse_id' => $data['destination_warehouse_id'], 'transfer_date' => $data['transfer_date'], 'notes' => $data['notes'] ?? null, 'created_by' => $request->user()->id]);
            $transfer->items()->createMany($data['items']);
            $audit->record($request, 'CREATE', 'InventoryTransfer', $transfer, newValues: $transfer->load('items')->toArray());

            return $transfer;
        });

        return redirect()->route('inventory.transfers.index')->with('success', "Transfer {$transfer->number} disimpan sebagai draft.");
    }

    public function post(Request $request, InventoryTransfer $inventoryTransfer, InventoryTransferService $service, AuditService $audit): RedirectResponse
    {
        $request->validate(['submission_token' => ['required', 'uuid']]);
        $transfer = $service->post($inventoryTransfer, $request->user(), $request->string('submission_token')->toString());
        $audit->record($request, 'POST', 'InventoryTransfer', $transfer, newValues: $transfer->toArray(), description: "Memposting transfer stok {$transfer->number}; stok gudang asal berkurang dan gudang tujuan bertambah.");

        return back()->with('success', "Transfer {$transfer->number} berhasil diposting.");
    }
}
