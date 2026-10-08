<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class ModuleController extends Controller
{
    public function __invoke(Request $request, string $module): View
    {
        $moduleData = config("modules.{$module}");
        abort_if($moduleData === null, 404);
        abort_unless($request->user()->hasPermission($moduleData['permission']), 403);

        return view('modules.overview', [
            'module' => $module,
            'moduleData' => $moduleData,
        ]);
    }
}
