<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('auth.password');
    }

    public function update(UpdatePasswordRequest $request, AuditService $auditService): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $user->update(['password' => $validated['password']]);
        $request->session()->regenerate();

        $auditService->record($request, 'CHANGE_PASSWORD', 'Authentication', $user);

        return back()->with('success', 'Password berhasil diubah.');
    }
}
