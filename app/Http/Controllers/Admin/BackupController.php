<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditService;
use App\Services\DatabaseBackupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function index(DatabaseBackupService $backups): View
    {
        return view('admin.backups.index', ['backups' => $backups->all()]);
    }

    public function store(Request $request, DatabaseBackupService $backups, AuditService $audit): RedirectResponse
    {
        $backup = $backups->create();
        $audit->record($request, 'BACKUP', 'System', newValues: $backup, description: "Membuat backup database {$backup['filename']}.");

        return back()->with('success', 'Backup database berhasil dibuat.');
    }

    public function download(string $filename): StreamedResponse
    {
        abort_unless(basename($filename) === $filename && preg_match('/\Adatabase-[a-zA-Z0-9._-]+\.sql\z/', $filename) === 1, 404);
        $path = 'backups/'.$filename;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $filename);
    }
}
