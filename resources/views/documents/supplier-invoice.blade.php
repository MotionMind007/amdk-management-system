@extends('documents.layout')

@section('title', 'Tagihan Supplier '.$supplierInvoice->number)
@section('back-url', route('finance.index'))
@section('print-label', 'Cetak Tagihan')

@section('document')
    <header class="document-header">
        <div><h1 class="company-name">PT. AIR MINUM JAYAPURA (PERSERODA)</h1><p class="company-brand">ROBONGHOLO NANWANI</p></div>
        <div><h2 class="document-title">TAGIHAN SUPPLIER</h2><p class="document-number">{{ $supplierInvoice->number }}</p></div>
    </header>

    <section class="meta-grid">
        <div><div class="meta-row"><span>Supplier</span><span>:</span><strong>{{ $supplierInvoice->purchaseOrder->supplier->name }}</strong></div><div class="meta-row"><span>Faktur Supplier</span><span>:</span><span>{{ $supplierInvoice->supplier_invoice_number }}</span></div><div class="meta-row"><span>No. PO</span><span>:</span><span>{{ $supplierInvoice->purchaseOrder->number }}</span></div></div>
        <div><div class="meta-row"><span>Penerimaan</span><span>:</span><span>{{ $supplierInvoice->goodsReceipt->number }}</span></div><div class="meta-row"><span>Tanggal Faktur</span><span>:</span><span>{{ $supplierInvoice->invoice_date->format('d/m/Y') }}</span></div><div class="meta-row"><span>Jatuh Tempo</span><span>:</span><span>{{ $supplierInvoice->due_date?->format('d/m/Y') ?? '-' }}</span></div></div>
    </section>

    <table><thead><tr><th class="center" style="width: 34px">No.</th><th>Barang Diterima</th><th class="number" style="width: 100px">Jumlah</th><th class="number" style="width: 130px">Nilai Terima</th></tr></thead><tbody>
        @foreach ($supplierInvoice->goodsReceipt->items as $item)
            <tr><td class="center">{{ $loop->iteration }}</td><td>{{ $item->product->name }}</td><td class="number">{{ rtrim(rtrim(number_format((float) $item->quantity, 3, ',', '.'), '0'), ',') }} {{ $item->product->unit->code }}</td><td class="number">{{ number_format((float) $item->line_total, 0, ',', '.') }}</td></tr>
        @endforeach
    </tbody></table>

    <section class="summary">
        <div class="notes"><strong>Catatan:</strong> {{ $supplierInvoice->notes ?: '-' }}</div>
        <div><div class="total-row total-row--grand"><span>TOTAL TAGIHAN</span><span>Rp {{ number_format((float) $supplierInvoice->total, 0, ',', '.') }}</span></div><div class="total-row"><span>Sudah Dibayar</span><span>Rp {{ number_format((float) $supplierInvoice->paid_amount, 0, ',', '.') }}</span></div><div class="total-row"><span>Sisa Hutang</span><span>Rp {{ number_format((float) $supplierInvoice->outstanding_amount, 0, ',', '.') }}</span></div></div>
    </section>
    <footer class="signatures"><div>Dibuat Oleh<div class="signature-line"></div></div><div>Diperiksa Oleh<div class="signature-line"></div></div><div>Disetujui Oleh<div class="signature-line"></div></div></footer>
@endsection
