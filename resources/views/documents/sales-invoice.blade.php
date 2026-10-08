@extends('documents.layout')

@section('title', 'Faktur '.$sale->number)
@section('back-url', route('sales.index'))
@section('print-label', 'Cetak Faktur')

@section('document')
    <header class="document-header">
        <div>
            <h1 class="company-name">PT. AIR MINUM JAYAPURA (PERSERODA)</h1>
            <p class="company-brand">ROBONGHOLO NANWANI</p>
        </div>
        <div>
            <h2 class="document-title">FAKTUR PENJUALAN</h2>
            <p class="document-number">{{ $sale->number }}</p>
        </div>
    </header>

    <section class="meta-grid">
        <div>
            <div class="meta-row"><span>Pelanggan</span><span>:</span><strong>{{ $sale->customer->name }}</strong></div>
            <div class="meta-row"><span>Alamat</span><span>:</span><span>{{ $sale->customer->address ?: '-' }}</span></div>
            <div class="meta-row"><span>Telepon</span><span>:</span><span>{{ $sale->customer->phone ?: '-' }}</span></div>
        </div>
        <div>
            <div class="meta-row"><span>Tanggal</span><span>:</span><span>{{ $sale->sale_date->format('d/m/Y') }}</span></div>
            <div class="meta-row"><span>Pembayaran</span><span>:</span><span>{{ $sale->payment_type === 'cash' ? 'Cash' : 'Kredit' }}</span></div>
            <div class="meta-row"><span>Jatuh tempo</span><span>:</span><span>{{ $sale->due_date?->format('d/m/Y') ?? '-' }}</span></div>
        </div>
    </section>

    <table>
        <thead>
            <tr>
                <th class="center" style="width: 34px">No.</th>
                <th>Produk</th>
                <th class="number" style="width: 95px">Jumlah</th>
                <th class="number" style="width: 130px">Harga/Karton</th>
                <th class="number" style="width: 140px">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->items as $item)
                <tr>
                    <td class="center">{{ $loop->iteration }}</td>
                    <td>{{ $item->product->name }}</td>
                    <td class="number">{{ rtrim(rtrim(number_format((float) $item->quantity, 3, ',', '.'), '0'), ',') }} {{ $item->product->unit->code }}</td>
                    <td class="number">{{ number_format((float) $item->unit_price, 0, ',', '.') }}</td>
                    <td class="number">{{ number_format((float) $item->line_total, 0, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <section class="summary">
        <div class="notes"><strong>Catatan:</strong> {{ $sale->notes ?: '-' }}</div>
        <div>
            <div class="total-row total-row--grand"><span>TOTAL</span><span>Rp {{ number_format((float) $sale->total, 0, ',', '.') }}</span></div>
            <div class="total-row"><span>Terbayar</span><span>Rp {{ number_format((float) $sale->paid_amount, 0, ',', '.') }}</span></div>
            <div class="total-row"><span>Sisa</span><span>Rp {{ number_format((float) $sale->outstanding_amount, 0, ',', '.') }}</span></div>
        </div>
    </section>

    <footer class="signatures">
        <div>Penerima<div class="signature-line"></div></div>
        <div>Pengirim<div class="signature-line"></div></div>
        <div>Administrasi<div class="signature-line"></div></div>
    </footer>
@endsection
