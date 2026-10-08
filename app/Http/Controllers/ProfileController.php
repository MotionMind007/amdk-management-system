<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): View
    {
        $user = $request->user()->load('employee');
        $employee = $user->employee ?? Employee::query()
            ->where('email', $user->email)
            ->first();
        $photoUrl = $employee?->photo_path !== null
            ? Storage::disk('public')->url($employee->photo_path)
            : null;

        return view('profile.show', compact('user', 'employee', 'photoUrl'));
    }
}
