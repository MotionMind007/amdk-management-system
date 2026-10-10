<?php

namespace App\Http\Controllers;

use App\Http\Requests\WarehouseRequest;
use App\Models\Warehouse;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->toString();
        $warehouses = Warehouse::query()
            ->withCount('stockBalances')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            }))
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('warehouses.index', compact('warehouses', 'search'));
    }

    public function create(): View
    {
        return view('warehouses.form', ['warehouse' => new Warehouse]);
    }

    public function store(WarehouseRequest $request, AuditService $auditService): RedirectResponse
    {
        DB::transaction(function () use ($request, $auditService): void {
            $warehouse = Warehouse::create($request->validated());
            $status = $warehouse->is_active ? 'aktif' : 'tidak aktif';
            $auditService->record(
                $request,
                'CREATE',
                'Warehouse',
                $warehouse,
                newValues: $warehouse->toArray(),
                description: "Menambahkan gudang {$warehouse->code} - {$warehouse->name} dengan status {$status}.",
            );
        });

        return redirect()->route('warehouses.index')->with('success', 'Gudang berhasil ditambahkan.');
    }

    public function edit(Warehouse $warehouse): View
    {
        return view('warehouses.form', compact('warehouse'));
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse, AuditService $auditService): RedirectResponse
    {
        DB::transaction(function () use ($request, $warehouse, $auditService): void {
            $oldValues = $warehouse->toArray();
            $warehouse->update($request->validated());
            $status = $warehouse->is_active ? 'aktif' : 'tidak aktif';
            $auditService->record(
                $request,
                'UPDATE',
                'Warehouse',
                $warehouse,
                $oldValues,
                $warehouse->fresh()->toArray(),
                "Memperbarui gudang {$warehouse->code} - {$warehouse->name}. Status saat ini {$status}.",
            );
        });

        return redirect()->route('warehouses.index')->with('success', 'Data gudang berhasil diperbarui.');
    }
}
