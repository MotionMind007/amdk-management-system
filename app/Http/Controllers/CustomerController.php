<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->toString();
        $customers = Customer::query()
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('customer_code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('customers.index', compact('customers', 'search'));
    }

    public function create(): View
    {
        return view('customers.form', ['customer' => new Customer]);
    }

    public function store(CustomerRequest $request, AuditService $auditService): RedirectResponse
    {
        DB::transaction(function () use ($request, $auditService): void {
            $customer = Customer::create($request->validated());
            $auditService->record($request, 'CREATE', 'Customer', $customer, newValues: $customer->toArray());
        });

        return redirect()->route('customers.index')->with('success', 'Pelanggan berhasil ditambahkan.');
    }

    public function edit(Customer $customer): View
    {
        return view('customers.form', compact('customer'));
    }

    public function update(CustomerRequest $request, Customer $customer, AuditService $auditService): RedirectResponse
    {
        DB::transaction(function () use ($request, $customer, $auditService): void {
            $oldValues = $customer->toArray();
            $customer->update($request->validated());
            $auditService->record($request, 'UPDATE', 'Customer', $customer, $oldValues, $customer->fresh()->toArray());
        });

        return redirect()->route('customers.index')->with('success', 'Data pelanggan berhasil diperbarui.');
    }

    public function destroy(Request $request, Customer $customer, AuditService $auditService): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('customers.manage'), 403);

        DB::transaction(function () use ($request, $customer, $auditService): void {
            $oldValues = $customer->toArray();
            $customer->update(['status' => 'inactive']);
            $auditService->record($request, 'DEACTIVATE', 'Customer', $customer, $oldValues, $customer->fresh()->toArray());
        });

        return back()->with('success', 'Pelanggan dinonaktifkan tanpa menghapus histori.');
    }
}
