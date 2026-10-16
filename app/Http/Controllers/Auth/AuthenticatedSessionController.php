<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, AuditService $auditService): RedirectResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);
        $credentials['is_active'] = true;

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Email atau password tidak sesuai.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();
        $request->user()->update(['last_login_at' => now()]);
        $auditService->record($request, 'LOGIN', 'Authentication', $request->user());

        return redirect()->intended(route('modules.index'));
    }

    public function destroy(Request $request, AuditService $auditService): RedirectResponse
    {
        $auditService->record($request, 'LOGOUT', 'Authentication', $request->user());

        foreach ($request->session()->get('data_imports', []) as $pendingImport) {
            $path = is_array($pendingImport) ? ($pendingImport['path'] ?? null) : null;

            if (is_string($path) && str_starts_with($path, 'data-imports/')) {
                Storage::disk('local')->delete($path);
            }
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
