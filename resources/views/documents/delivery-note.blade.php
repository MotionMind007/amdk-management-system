@extends('documents.layout')

@section('title', 'Surat Jalan '.$deliveryNote->number)
@section('back-url', route('sales.deliveries.index'))
@section('print-label', 'Cetak Surat Jalan')

@section('document')
    <header class="document-header">
        <div><h1 class="company-name">PT. AIR MINUM JAYAPURA (PERSERODA)</h1><p class="company-brand">ROBONGHOLO NANWANI</p></div>
        <div><h2 class="document-title">SURAT JALAN</h2><p class="document-number">{{ $deliveryNote->number }}</p></div>
    </header>

    <section class="meta-grid">
        <div><div class="meta-row"><span>Pelanggan</span><span>:</span><strong>{{ $deliveryNote->sale->customer->name }}</strong></div><div class="meta-row"><span>Alamat</span><span>:</span><span>{{ $deliveryNote->sale->customer->address ?: '-' }}</span></div></div>
        <div><div class="meta-row"><span>No. Faktur</span><span>:</span><span>{{ $deliveryNote->sale->number }}</span></div><div class="meta-row"><span>Tanggal</span><span>:</span><span>{{ $deliveryNote->delivery_date->format('d/m/Y') }}</span></div><div class="meta-row"><span>Gudang</span><span>:</span><span>{{ $deliveryNote->warehouse->name }}</span></div></div>
    </section>

    <table><thead><tr><th class="center" style="width: 34px">No.</th><th>Produk</th><th class="number" style="width: 140px">Jumlah</th></tr></thead><tbody>
        @foreach ($deliveryNote->items as $item)
            <tr><td class="center">{{ $loop->iteration }}</td><td>{{ $item->product->name }}</td><td class="number">{{ rtrim(rtrim(number_format((float) $item->quantity, 3, ',', '.'), '0'), ',') }} {{ $item->product->unit->code }}</td></tr>
        @endforeach
    </tbody></table>

    <section class="summary"><div class="notes"><strong>Catatan:</strong> {{ $deliveryNote->notes ?: '-' }}</div></section>
    <footer class="signatures"><div>Pengirim/Gudang<div class="signature-line"></div></div><div>Pengemudi<div class="signature-line"></div></div><div>Penerima<div class="signature-line"></div></div></footer>
@endsection
