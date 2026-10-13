@extends('documents.layout')

@section('title', 'Purchase Order '.$purchaseOrder->number)
@section('back-url', route('purchasing.index'))
@section('print-label', 'Cetak PO')

@section('document')
    <header class="document-header">
        <div>
            <h1 class="company-name">PT. AIR MINUM JAYAPURA (PERSERODA)</h1>
            <p class="company-brand">ROBONGHOLO NANWANI</p>
        </div>
        <div>
            <h2 class="document-title">PURCHASE ORDER</h2>
            <p class="document-number">{{ $purchaseOrder->number }}</p>
        </div>
    </header>

    @if ($purchaseOrder->status === 'draft')
        <section style="margin-top: 7px; border: 1px solid #000; padding: 5px; text-align: center; font-weight: 700; letter-spacing: .06em;">
            DRAFT — BELUM DISETUJUI
        </section>
    @elseif ($purchaseOrder->status === 'rejected')
        <section style="margin-top: 7px; border: 1px solid #000; padding: 5px; text-align: center; font-weight: 700; letter-spacing: .06em;">
            DITOLAK — PERLU DIREVISI
        </section>
    @endif

    <section class="meta-grid">
        <div>
            <div class="meta-row"><span>Supplier</span><span>:</span><strong>{{ $purchaseOrder->supplier->name }}</strong></div>
            <div class="meta-row"><span>Alamat</span><span>:</span><span>{{ $purchaseOrder->supplier->address ?: '-' }}</span></div>
            <div class="meta-row"><span>Telepon</span><span>:</span><span>{{ $purchaseOrder->supplier->phone ?: '-' }}</span></div>
        </div>
        <div>
            <div class="meta-row"><span>Tanggal PO</span><span>:</span><span>{{ $purchaseOrder->order_date->format('d/m/Y') }}</span></div>
            <div class="meta-row"><span>Perkiraan tiba</span><span>:</span><span>{{ $purchaseOrder->expected_date?->format('d/m/Y') ?? '-' }}</span></div>
            <div class="meta-row"><span>Gudang</span><span>:</span><span>{{ $purchaseOrder->warehouse->name }}</span></div>
            <div class="meta-row"><span>Status</span><span>:</span><span>{{ str($purchaseOrder->status)->replace('_', ' ')->title() }}</span></div>
        </div>
    </section>

    <table>
        <thead>
            <tr><th class="center" style="width: 34px">No.</th><th>Barang</th><th class="number" style="width: 95px">Jumlah</th><th class="number" style="width: 130px">Harga/Satuan</th><th class="number" style="width: 140px">Subtotal</th></tr>
        </thead>
        <tbody>
            @foreach ($purchaseOrder->items as $item)
                <tr>
                    <td class="center">{{ $loop->iteration }}</td>
                    <td>{{ $item->product->name }}</td>
                    <td class="number">{{ rtrim(rtrim(number_format((float) $item->ordered_quantity, 3, ',', '.'), '0'), ',') }} {{ $item->product->unit->code }}</td>
                    <td class="number">{{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                    <td class="number">{{ number_format((float) $item->line_total, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <section class="summary">
        <div class="notes"><strong>Catatan:</strong> {{ $purchaseOrder->notes ?: '-' }}</div>
        <div>
            <div class="total-row total-row--grand"><span>TOTAL PO</span><span>Rp {{ number_format((float) $purchaseOrder->total, 0, ',', '.') }}</span></div>
            <div class="total-row"><span>Pembayaran</span><span>{{ $purchaseOrder->payment_type === 'cash' ? 'Cash' : 'Kredit' }}</span></div>
            <div class="total-row"><span>Jatuh tempo</span><span>{{ $purchaseOrder->due_date?->format('d/m/Y') ?? '-' }}</span></div>
        </div>
    </section>

    <footer class="signatures">
        <div>Dibuat Oleh<div class="signature-line"></div></div>
        <div>Disetujui Oleh<div class="signature-line"></div></div>
        <div>Supplier<div class="signature-line"></div></div>
    </footer>
@endsection
