<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportFilterRequest;
use App\Models\Warehouse;
use App\Services\OperationalReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationalReportController extends Controller
{
    public function show(ReportFilterRequest $request, string $type, OperationalReportService $reports): View
    {
        $report = $reports->report($type, $request->validated());
        $rows = (clone $report['query'])->orderByDesc('report_date')->orderByDesc('reference')->paginate(25)->withQueryString();

        return view('reports.operational', compact('report', 'rows', 'type') + ['warehouses' => Warehouse::query()->where('is_active', true)->orderBy('name')->get()]);
    }

    public function export(ReportFilterRequest $request, string $type, OperationalReportService $reports): StreamedResponse
    {
        $report = $reports->report($type, $request->validated());
        $filename = $type.'-'.$report['start_date'].'-'.$report['end_date'].'.csv';

        return response()->streamDownload(function () use ($report): void {
            $output = fopen('php://output', 'w');
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, ['Tanggal', 'Referensi', 'Relasi / Akun', 'Gudang / Jatuh Tempo', 'Status', $report['quantity_label'], $report['amount_label'], 'Outstanding'], ';', '"', '');
            foreach ((clone $report['query'])->orderBy('report_date')->cursor() as $row) {
                fputcsv($output, [$row->report_date, $this->csvText($row->reference), $this->csvText($row->party), $this->csvText($row->warehouse), $this->csvText($row->status), $row->quantity, $row->amount, $row->outstanding], ';', '"', '');
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function csvText(mixed $value): string
    {
        $text = (string) $value;

        return preg_match('/\A[=+\-@\t\r]/', $text) === 1 ? "'{$text}" : $text;
    }
}
