<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\SupplierInvoice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;

class FinanceAgingController extends Controller
{
    public function __invoke(): View
    {
        $receivables = Sale::query()->where('outstanding_amount', '>', 0)->with('customer:id,name')->oldest('due_date')->get();
        $payables = SupplierInvoice::query()->where('outstanding_amount', '>', 0)->with('purchaseOrder.supplier:id,name')->oldest('due_date')->get();

        return view('finance.aging', ['receivables' => $receivables, 'payables' => $payables, 'receivableBuckets' => $this->buckets($receivables), 'payableBuckets' => $this->buckets($payables)]);
    }

    /** @return array<string, float> */
    private function buckets(Collection $documents): array
    {
        $buckets = ['Belum jatuh tempo' => 0.0, '1–30 hari' => 0.0, '31–60 hari' => 0.0, '> 60 hari' => 0.0];

        foreach ($documents as $document) {
            $days = $document->due_date === null || $document->due_date->isFuture() ? 0 : (int) $document->due_date->diffInDays(today());
            $bucket = $days === 0 ? 'Belum jatuh tempo' : ($days <= 30 ? '1–30 hari' : ($days <= 60 ? '31–60 hari' : '> 60 hari'));
            $buckets[$bucket] += (float) $document->outstanding_amount;
        }

        return $buckets;
    }
}
