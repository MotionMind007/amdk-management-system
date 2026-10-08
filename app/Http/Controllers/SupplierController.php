<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierRequest;
use App\Models\Supplier;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->toString();
        $suppliers = Supplier::query()
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('supplier_code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('suppliers.index', compact('suppliers', 'search'));
    }

    public function create(): View
    {
        return view('suppliers.form', ['supplier' => new Supplier]);
    }

    public function store(SupplierRequest $request, AuditService $auditService): RedirectResponse
    {
        DB::transaction(function () use ($request, $auditService): void {
            $supplier = Supplier::create($request->validated());
            $auditService->record($request, 'CREATE', 'Supplier', $supplier, newValues: $supplier->toArray());
        });

        return redirect()->route('suppliers.index')->with('success', 'Supplier berhasil ditambahkan.');
    }

    public function edit(Supplier $supplier): View
    {
        return view('suppliers.form', compact('supplier'));
    }

    public function update(SupplierRequest $request, Supplier $supplier, AuditService $auditService): RedirectResponse
    {
        DB::transaction(function () use ($request, $supplier, $auditService): void {
            $oldValues = $supplier->toArray();
            $supplier->update($request->validated());
            $auditService->record($request, 'UPDATE', 'Supplier', $supplier, $oldValues, $supplier->fresh()->toArray());
        });

        return redirect()->route('suppliers.index')->with('success', 'Data supplier berhasil diperbarui.');
    }

    public function destroy(Request $request, Supplier $supplier, AuditService $auditService): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('suppliers.manage'), 403);

        DB::transaction(function () use ($request, $supplier, $auditService): void {
            $oldValues = $supplier->toArray();
            $supplier->update(['status' => 'inactive']);
            $auditService->record($request, 'DEACTIVATE', 'Supplier', $supplier, $oldValues, $supplier->fresh()->toArray());
        });

        return back()->with('success', 'Supplier dinonaktifkan tanpa menghapus histori.');
    }
}
