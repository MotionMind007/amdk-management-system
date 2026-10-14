<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Models\User;
use App\Services\AuditService;
use App\Services\PrivilegedAccountGuard;
use App\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('search')->trim()->toString();
        $users = User::query()
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('name', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'search'));
    }

    public function create(): View
    {
        return view('admin.users.form', ['user' => new User, 'roles' => UserRole::cases()]);
    }

    public function store(UserRequest $request, AuditService $auditService): RedirectResponse
    {
        DB::transaction(function () use ($request, $auditService): void {
            $user = User::create($request->validated());
            $auditService->record($request, 'CREATE', 'User', $user, newValues: Arr::except($user->toArray(), ['password']));
        });

        return redirect()->route('admin.users.index')->with('success', 'User berhasil ditambahkan.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', ['user' => $user, 'roles' => UserRole::cases()]);
    }

    public function update(UserRequest $request, User $user, AuditService $auditService, PrivilegedAccountGuard $privilegedAccounts): RedirectResponse
    {
        DB::transaction(function () use ($request, $user, $auditService, $privilegedAccounts): void {
            $oldValues = $user->toArray();
            $values = $request->validated();
            $privilegedAccounts->ensureCanChange($user, $values['role'], (bool) $values['is_active']);
            if (($values['password'] ?? null) === null) {
                unset($values['password']);
            }
            $user->update($values);
            $auditService->record($request, 'UPDATE', 'User', $user, $oldValues, $user->fresh()->toArray());
        });

        return redirect()->route('admin.users.index')->with('success', 'User berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user, AuditService $auditService, PrivilegedAccountGuard $privilegedAccounts): RedirectResponse
    {
        abort_if($request->user()->is($user), 422, 'Anda tidak dapat menonaktifkan akun sendiri.');

        DB::transaction(function () use ($request, $user, $auditService, $privilegedAccounts): void {
            $oldValues = $user->toArray();
            $privilegedAccounts->ensureCanChange($user, $user->role, false);
            $user->update(['is_active' => false]);
            $auditService->record($request, 'DEACTIVATE', 'User', $user, $oldValues, $user->fresh()->toArray());
        });

        return back()->with('success', 'User berhasil dinonaktifkan.');
    }
}
