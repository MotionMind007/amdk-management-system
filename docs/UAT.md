# User Acceptance Test (UAT)

Dokumen ini menjadi baseline UAT sebelum aplikasi dipakai oleh operator pabrik. UAT dilakukan pada staging atau komputer khusus UAT, bukan database production.

## Persiapan

1. Pastikan migrasi terbaru sudah dijalankan.
2. Atur `UAT_PASSWORD` minimal 12 karakter pada environment non-production.
3. Jalankan `php artisan db:seed --class=UatSeeder`.
4. Jalankan `composer test:uat` sebagai baseline otomatis.
5. Catat versi commit, tanggal, penguji, browser, dan hasil pada lembar persetujuan di bagian akhir.

Seeder dapat dijalankan ulang dan tidak boleh berjalan ketika `APP_ENV=production`.

## Akun UAT

| Role | Email |
| --- | --- |
| Owner | `uat.owner@amdk.local` |
| Super Admin | `uat.superadmin@amdk.local` |
| Admin | `uat.admin@amdk.local` |
| Gudang | `uat.gudang@amdk.local` |
| Sales | `uat.sales@amdk.local` |

Semua akun memakai password dari `UAT_PASSWORD`. Jangan mencantumkan password pada dokumen hasil UAT.

## Matriks Akses Role

| Skenario | Admin | Gudang | Sales | Super Admin | Owner |
| --- | :---: | :---: | :---: | :---: | :---: |
| Melihat dashboard dan notifikasi | Ya | Ya | Ya | Ya | Ya |
| Membuat dan mengubah PO | Ya | Tidak | Tidak | Ya | Ya |
| Preview PO | Ya | Ya | Tidak | Ya | Ya |
| Menyetujui atau menolak PO | Tidak | Tidak | Tidak | Ya | Ya |
| Menerima barang dan unggah bukti | Tidak | Ya | Tidak | Ya | Ya |
| Produksi dan posting hasil produksi | Tidak | Ya | Tidak | Ya | Ya |
| Melihat stok | Ya | Ya | Ya | Ya | Ya |
| Penyesuaian, opname, dan transfer stok | Ya | Ya | Tidak | Ya | Ya |
| Membuat penjualan dan pembayaran pelanggan | Ya | Tidak | Ya | Ya | Ya |
| Membuat surat jalan | Ya | Ya | Tidak | Ya | Ya |
| Keuangan, hutang, dan pembayaran supplier | Ya | Tidak | Tidak | Ya | Ya |
| Master karyawan | Ya | Tidak | Tidak | Ya | Ya |
| User, backup, impor, health, dan audit log | Tidak | Tidak | Tidak | Ya | Ya |
| Tutup atau buka kembali periode | Tidak | Tidak | Tidak | Ya | Ya |

Setiap akses bertanda “Tidak” harus menghasilkan halaman 403 atau menu tidak tersedia. Pengujian tidak cukup hanya dengan memastikan tombol tersembunyi.

## Skenario Bisnis Wajib

### UAT-01 — Pembelian Kredit sampai Pelunasan

1. Admin membuat PO kredit beserta jatuh tempo.
2. Owner atau Super Admin membuka preview lalu menyetujui PO.
3. Gudang menerima barang, mengisi jumlah aktual, dan mengunggah bukti penerimaan.
4. Pastikan stok bertambah hanya setelah penerimaan diposting.
5. Admin membuat invoice supplier lalu mencatat pembayaran.
6. Pastikan hutang dan saldo kas/bank berubah sesuai nominal.

Hasil yang diterima: nomor dokumen unik, status dokumen berurutan, bukti dapat diunduh, notifikasi terkirim ke role yang tepat, dan audit log mencatat setiap pelaku.

### UAT-02 — Produksi Kemasan dan Produk Jadi

1. Gudang membuat produksi botol dengan jumlah preform aktual.
2. Posting produksi dan periksa pengurangan preform serta penambahan botol.
3. Buat produksi produk jadi berdasarkan komposisi.
4. Posting produksi dan periksa pengurangan bahan serta penambahan karton produk jadi.
5. Ulangi dengan stok bahan tidak cukup; sistem wajib menolak posting tanpa perubahan stok parsial.

### UAT-03 — Penjualan Kredit sampai Pelunasan

1. Sales membuat penjualan kredit dan memastikan total draft sudah benar.
2. Posting penjualan; stok belum boleh berkurang.
3. Gudang membuat dan mencetak surat jalan; stok berkurang sesuai jumlah pengiriman.
4. Sales atau Admin mencatat pembayaran Cash atau Transfer.
5. Untuk Transfer, isi bank pengirim dan unggah bukti.
6. Pastikan piutang, saldo akun penerima, invoice, dan audit log sudah benar.

### UAT-04 — Retur dan Koreksi

1. Buat retur pembelian dan penjualan dari dokumen yang memenuhi syarat.
2. Periksa dampaknya terhadap stok, hutang/piutang, serta kas jika transaksi sudah dibayar.
3. Pastikan dokumen yang sudah diposting tidak dapat diedit langsung.
4. Lakukan penyesuaian stok dengan alasan yang jelas dan periksa audit log.

### UAT-05 — Stok Opname Multi-Gudang

1. Buat stok opname untuk satu gudang dan simpan sebagai draft.
2. Periksa stok sistem, stok fisik, dan selisih per produk.
3. Ubah stok melalui transaksi lain sebelum posting draft; sistem wajib meminta opname diperiksa ulang.
4. Posting opname yang valid dan pastikan saldo akhir menjadi acuan periode berikutnya.
5. Lakukan transfer antar-gudang dan pastikan total stok perusahaan tidak berubah.

### UAT-06 — Kontrol Periode dan Keandalan

1. Owner menutup periode.
2. Coba memasukkan transaksi pada periode tertutup; sistem wajib menolak.
3. Jalankan backup dan `php artisan system:verify-backup`.
4. Jalankan `php artisan system:check-integrity` dan pastikan semua area berstatus OK.
5. Pastikan file CSV laporan dapat dibuka dan formula dari data pengguna tidak dieksekusi otomatis.

## Bukti Otomatis

Perintah `composer test:uat` mencakup:

- data UAT yang aman dan dapat diulang;
- siklus lintas role pembelian–produksi–penjualan;
- matriks otorisasi;
- approval dan penerimaan PO;
- notifikasi;
- preview dokumen;
- pemeriksaan integritas operasional.

Hasil otomatis tidak menggantikan persetujuan user bisnis terhadap istilah, urutan kerja, tampilan cetak, dan kemudahan penggunaan.

Hasil eksekusi baseline terbaru tercatat di [Hasil UAT Otomatis 14 Oktober 2026](UAT-RESULT-2026-10-14.md).

## Lembar Persetujuan

| Informasi | Nilai |
| --- | --- |
| Commit yang diuji |  |
| Environment / URL |  |
| Tanggal |  |
| Browser dan perangkat |  |
| Hasil `composer test:uat` |  |
| Temuan terbuka |  |

| Role | Nama penguji | Hasil | Tanda tangan / persetujuan |
| --- | --- | --- | --- |
| Admin |  | Lulus / Tidak |  |
| Gudang |  | Lulus / Tidak |  |
| Sales |  | Lulus / Tidak |  |
| Super Admin |  | Lulus / Tidak |  |
| Owner |  | Lulus / Tidak |  |

UAT dinyatakan selesai jika seluruh skenario kritis lulus, tidak ada temuan severity tinggi, integritas data berstatus OK, dan Owner menyetujui hasilnya.
