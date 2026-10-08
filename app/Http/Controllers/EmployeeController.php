<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeRequest;
use App\Models\Employee;
use App\Models\User;
use App\Services\AuditService;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Throwable;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->toString();
        $employees = Employee::query()
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('employee_code', 'like', "%{$search}%")
                    ->orWhere('position', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%");
            }))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('employees.index', compact('employees', 'search'));
    }

    public function create(): View
    {
        return view('employees.form', ['employee' => new Employee, 'roles' => UserRole::cases()]);
    }

    public function store(EmployeeRequest $request, AuditService $auditService): RedirectResponse
    {
        $data = $request->validated();
        unset($data['photo']);

        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'],
            'is_active' => $data['status'] === 'active',
        ];
        unset($data['password'], $data['password_confirmation'], $data['role']);

        $photoPath = $request->file('photo')?->store('employees', 'public');

        if ($photoPath !== null) {
            $data['photo_path'] = $photoPath;
        }

        try {
            DB::transaction(function () use ($request, $auditService, $data, $userData): void {
                $user = User::create($userData);
                $employee = $user->employee()->create($data);

                $auditService->record($request, 'CREATE', 'User', $user, newValues: $user->toArray());
                $auditService->record($request, 'CREATE', 'Employee', $employee, newValues: $employee->toArray());
            });
        } catch (Throwable $exception) {
            if ($photoPath !== null) {
                Storage::disk('public')->delete($photoPath);
            }

            throw $exception;
        }

        return redirect()->route('employees.index')->with('success', 'Karyawan berhasil ditambahkan.');
    }

    public function edit(Employee $employee): View
    {
        $employee->load('user');

        return view('employees.form', ['employee' => $employee, 'roles' => UserRole::cases()]);
    }

    public function update(EmployeeRequest $request, Employee $employee, AuditService $auditService): RedirectResponse
    {
        $data = $request->validated();
        unset($data['photo']);

        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'is_active' => $data['status'] === 'active',
        ];

        if (($data['password'] ?? null) !== null) {
            $userData['password'] = $data['password'];
        }

        unset($data['password'], $data['password_confirmation'], $data['role']);

        $oldPhotoPath = $employee->photo_path;
        $newPhotoPath = $request->file('photo')?->store('employees', 'public');

        if ($newPhotoPath !== null) {
            $data['photo_path'] = $newPhotoPath;
        }

        try {
            DB::transaction(function () use ($request, $employee, $auditService, $data, $userData): void {
                $oldValues = $employee->toArray();
                $user = $employee->user;

                if ($user === null) {
                    $user = User::create($userData);
                    $data['user_id'] = $user->id;
                    $auditService->record($request, 'CREATE', 'User', $user, newValues: $user->toArray());
                } else {
                    $oldUserValues = $user->toArray();
                    $user->update($userData);
                    $auditService->record($request, 'UPDATE', 'User', $user, $oldUserValues, $user->fresh()->toArray());
                }

                $employee->update($data);
                $auditService->record($request, 'UPDATE', 'Employee', $employee, $oldValues, $employee->fresh()->toArray());
            });
        } catch (Throwable $exception) {
            if ($newPhotoPath !== null) {
                Storage::disk('public')->delete($newPhotoPath);
            }

            throw $exception;
        }

        if ($newPhotoPath !== null && $oldPhotoPath !== null) {
            Storage::disk('public')->delete($oldPhotoPath);
        }

        return redirect()->route('employees.index')->with('success', 'Data karyawan berhasil diperbarui.');
    }
}
