@extends('documents.layout')

@section('title', 'Retur Pembelian '.$purchaseReturn->number)
@section('back-url', route('purchasing.index'))
@section('print-label', 'Cetak Nota Retur')

@section('document')
    @php($purchaseOrder = $purchaseReturn->purchaseOrder)
    <header class="document-header">
        <div><h1 class="company-name">PT. AIR MINUM JAYAPURA (PERSERODA)</h1><p class="company-brand">ROBONGHOLO NANWANI</p></div>
        <div><h2 class="document-title">NOTA RETUR PEMBELIAN</h2><p class="document-number">{{ $purchaseReturn->number }}</p></div>
    </header>

    <section class="meta-grid">
        <div>
            <div class="meta-row"><span>Supplier</span><span>:</span><strong>{{ $purchaseOrder->supplier->name }}</strong></div>
            <div class="meta-row"><span>Alamat</span><span>:</span><span>{{ $purchaseOrder->supplier->address ?: '-' }}</span></div>
            <div class="meta-row"><span>Telepon</span><span>:</span><span>{{ $purchaseOrder->supplier->phone ?: '-' }}</span></div>
        </div>
        <div>
            <div class="meta-row"><span>No. PO</span><span>:</span><span>{{ $purchaseOrder->number }}</span></div>
            <div class="meta-row"><span>Tanggal retur</span><span>:</span><span>{{ $purchaseReturn->return_date->format('d/m/Y') }}</span></div>
            <div class="meta-row"><span>Gudang</span><span>:</span><span>{{ $purchaseOrder->warehouse->name }}</span></div>
        </div>
    </section>

    <table>
        <thead><tr><th class="center" style="width: 34px">No.</th><th>Barang</th><th class="number" style="width: 95px">Jumlah</th><th class="number" style="width: 130px">Harga/Satuan</th><th class="number" style="width: 140px">Subtotal</th></tr></thead>
        <tbody>
            @foreach ($purchaseReturn->items as $item)
                <tr><td class="center">{{ $loop->iteration }}</td><td>{{ $item->product->name }}</td><td class="number">{{ rtrim(rtrim(number_format((float) $item->quantity, 3, ',', '.'), '0'), ',') }} {{ $item->product->unit->code }}</td><td class="number">{{ number_format((float) $item->unit_price, 0, ',', '.') }}</td><td class="number">{{ number_format((float) $item->line_total, 0, ',', '.') }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <section class="summary">
        <div class="notes"><strong>Alasan retur:</strong> {{ $purchaseReturn->reason }}</div>
        <div>
            <div class="total-row total-row--grand"><span>TOTAL RETUR</span><span>Rp {{ number_format((float) $purchaseReturn->total, 0, ',', '.') }}</span></div>
            <div class="total-row"><span>Potong hutang</span><span>Rp {{ number_format((float) $purchaseReturn->credit_amount, 0, ',', '.') }}</span></div>
            <div class="total-row"><span>Refund</span><span>Rp {{ number_format((float) $purchaseReturn->refund_amount, 0, ',', '.') }}</span></div>
        </div>
    </section>

    <footer class="signatures"><div>Gudang<div class="signature-line"></div></div><div>Supplier<div class="signature-line"></div></div><div>Administrasi<div class="signature-line"></div></div></footer>
@endsection
