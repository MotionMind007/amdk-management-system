@extends('documents.layout')

@section('title', 'Retur Penjualan '.$salesReturn->number)
@section('back-url', route('sales.index'))
@section('print-label', 'Cetak Nota Retur')

@section('document')
    @php($sale = $salesReturn->sale)
    <header class="document-header">
        <div><h1 class="company-name">PT. AIR MINUM JAYAPURA (PERSERODA)</h1><p class="company-brand">ROBONGHOLO NANWANI</p></div>
        <div><h2 class="document-title">NOTA RETUR PENJUALAN</h2><p class="document-number">{{ $salesReturn->number }}</p></div>
    </header>

    <section class="meta-grid">
        <div>
            <div class="meta-row"><span>Pelanggan</span><span>:</span><strong>{{ $sale->customer->name }}</strong></div>
            <div class="meta-row"><span>Alamat</span><span>:</span><span>{{ $sale->customer->address ?: '-' }}</span></div>
            <div class="meta-row"><span>Telepon</span><span>:</span><span>{{ $sale->customer->phone ?: '-' }}</span></div>
        </div>
        <div>
            <div class="meta-row"><span>No. Faktur</span><span>:</span><span>{{ $sale->number }}</span></div>
            <div class="meta-row"><span>Tanggal retur</span><span>:</span><span>{{ $salesReturn->return_date->format('d/m/Y') }}</span></div>
            <div class="meta-row"><span>Gudang</span><span>:</span><span>{{ $sale->warehouse->name }}</span></div>
        </div>
    </section>

    <table>
        <thead><tr><th class="center" style="width: 34px">No.</th><th>Produk</th><th class="number" style="width: 95px">Jumlah</th><th class="number" style="width: 130px">Harga/Karton</th><th class="number" style="width: 140px">Subtotal</th></tr></thead>
        <tbody>
            @foreach ($salesReturn->items as $item)
                <tr><td class="center">{{ $loop->iteration }}</td><td>{{ $item->product->name }}</td><td class="number">{{ rtrim(rtrim(number_format((float) $item->quantity, 3, ',', '.'), '0'), ',') }} {{ $item->product->unit->code }}</td><td class="number">{{ number_format((float) $item->unit_price, 0, ',', '.') }}</td><td class="number">{{ number_format((float) $item->line_total, 0, ',', '.') }}</td></tr>
            @endforeach
        </tbody>
    </table>

    <section class="summary">
        <div class="notes"><strong>Alasan retur:</strong> {{ $salesReturn->reason }}</div>
        <div>
            <div class="total-row total-row--grand"><span>TOTAL RETUR</span><span>Rp {{ number_format((float) $salesReturn->total, 0, ',', '.') }}</span></div>
            <div class="total-row"><span>Potong piutang</span><span>Rp {{ number_format((float) $salesReturn->credit_amount, 0, ',', '.') }}</span></div>
            <div class="total-row"><span>Refund</span><span>Rp {{ number_format((float) $salesReturn->refund_amount, 0, ',', '.') }}</span></div>
        </div>
    </section>

    <footer class="signatures"><div>Pelanggan<div class="signature-line"></div></div><div>Sales<div class="signature-line"></div></div><div>Administrasi<div class="signature-line"></div></div></footer>
@endsection
