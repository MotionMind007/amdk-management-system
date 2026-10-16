<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PreviewDataImportRequest;
use App\Services\AuditService;
use App\Services\MasterDataImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataImportController extends Controller
{
    public function index(MasterDataImportService $importService): View
    {
        return view('admin.data-imports.index', ['definitions' => $importService->definitions()]);
    }

    public function template(string $type, MasterDataImportService $importService): StreamedResponse
    {
        abort_unless(in_array($type, MasterDataImportService::TYPES, true), 404);
        $definition = $importService->definition($type);

        return response()->streamDownload(function () use ($definition): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, $definition['columns'], ';', '"', '\\');
            fputcsv($output, $definition['example'], ';', '"', '\\');
            fclose($output);
        }, "template-{$type}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function preview(PreviewDataImportRequest $request, MasterDataImportService $importService): View|RedirectResponse
    {
        foreach ($request->session()->get('data_imports', []) as $previousToken => $previousImport) {
            if (is_array($previousImport) && isset($previousImport['path'])) {
                Storage::disk('local')->delete($previousImport['path']);
            }

            $request->session()->forget("data_imports.{$previousToken}");
        }

        $type = $request->string('type')->toString();
        $token = (string) Str::uuid();
        $path = $request->file('file')->storeAs("data-imports/{$request->user()->id}", "{$token}.csv", 'local');

        if (! is_string($path)) {
            return back()->withErrors(['file' => 'File tidak dapat disimpan. Silakan coba lagi.'])->withInput();
        }

        try {
            $preview = $importService->preview($type, $path);
        } catch (RuntimeException $exception) {
            Storage::disk('local')->delete($path);

            return back()->withErrors(['file' => $exception->getMessage()])->withInput();
        }

        $request->session()->put("data_imports.{$token}", [
            'user_id' => $request->user()->id,
            'type' => $type,
            'path' => $path,
            'original_name' => $request->file('file')->getClientOriginalName(),
        ]);

        return view('admin.data-imports.index', [
            'definitions' => $importService->definitions(),
            'preview' => $preview,
            'token' => $token,
            'originalName' => $request->file('file')->getClientOriginalName(),
        ]);
    }

    public function confirm(Request $request, string $token, MasterDataImportService $importService, AuditService $auditService): RedirectResponse
    {
        $metadata = $request->session()->get("data_imports.{$token}");
        abort_unless(is_array($metadata) && $metadata['user_id'] === $request->user()->id, 404);
        abort_unless(Storage::disk('local')->exists($metadata['path']), 404);

        try {
            $preview = $importService->preview($metadata['type'], $metadata['path']);
            if ($preview['errors'] !== [] || $preview['invalid_count'] > 0) {
                return redirect()->route('admin.data-imports.index')->withErrors([
                    'file' => 'Data berubah atau sudah tidak valid. Unggah ulang file dan periksa preview terbaru.',
                ]);
            }

            $count = DB::transaction(function () use ($request, $metadata, $preview, $importService, $auditService): int {
                $count = $importService->import($metadata['type'], $preview['rows'], $request->user());
                $auditService->record(
                    $request,
                    'IMPORT',
                    'Master Data',
                    newValues: ['type' => $metadata['type'], 'count' => $count, 'filename' => $metadata['original_name']],
                    description: "Mengimpor {$count} data {$preview['label']} dari file {$metadata['original_name']}.",
                );

                return $count;
            });
        } finally {
            Storage::disk('local')->delete($metadata['path']);
            $request->session()->forget("data_imports.{$token}");
        }

        return redirect()->route('admin.data-imports.index')->with('success', "{$count} data {$preview['label']} berhasil diimpor.");
    }
}
