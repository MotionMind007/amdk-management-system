<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ModuleHubController extends Controller
{
    public function __invoke(Request $request): View
    {
        $modules = collect(config('modules'))
            ->filter(fn (array $module): bool => $request->user()->hasPermission($module['permission']));

        return view('module-hub', ['modules' => $modules]);
    }
}
