# SOP Operasional Sistem AMDK

SOP ini mengatur penggunaan harian aplikasi. Semua koreksi harus dilakukan melalui fitur aplikasi agar histori dan audit log tetap utuh. User tidak boleh mengubah database secara langsung.

## Tanggung Jawab Role

- **Sales:** pelanggan, penjualan, invoice, pembayaran pelanggan, dan monitoring piutang.
- **Gudang:** penerimaan barang, bukti penerimaan, produksi, surat jalan, stok, transfer gudang, dan stok opname.
- **Admin:** pembelian, penjualan, keuangan, master data, karyawan, dan laporan; tidak mengelola produksi atau Sistem Admin.
- **Super Admin:** seluruh modul, user, backup, impor data, audit log, health check, approval, dan tutup periode.
- **Owner:** seluruh modul, approval PO, laporan, kontrol periode, dan persetujuan koreksi penting.

Setiap user wajib memakai akun pribadi dan tidak boleh membagikan password.

## Migrasi Awal dan Cutover

Migrasi hanya dilakukan oleh Super Admin atau Owner pada database kosong/terkendali dan selalu diawali backup terverifikasi.

1. Tetapkan tanggal cutover dan hentikan perubahan pada catatan manual setelah proses hitung fisik dimulai.
2. Impor berurutan: gudang, produk, pelanggan, supplier, karyawan, stok awal, lalu saldo awal kas/bank.
3. Gunakan template CSV dari menu **Sistem Admin > Migrasi Data** tanpa mengubah nama kolom.
4. Periksa seluruh baris pada preview. Konfirmasi hanya jika jumlah baris valid sama dengan sumber dan tidak ada baris bermasalah.
5. Admin memeriksa master pelanggan, supplier, dan produk. Karyawan terkait menguji login awal lalu segera mengganti password.
6. Gudang mencocokkan draft Stok Awal dengan hasil hitung fisik. Owner atau Super Admin memposting draft setelah disetujui.
7. Admin mencocokkan saldo kas/bank dengan berita acara kas dan rekening koran pada tanggal cutover.
8. Piutang dan hutang lama tidak boleh dimasukkan sebagai penjualan atau pembelian palsu. Catat melalui dokumen resmi setelah daftar saldo per pihak dan nomor referensinya disetujui Owner.
9. Jalankan `php artisan system:check-integrity`; seluruh pemeriksaan wajib berstatus OK sebelum transaksi harian dibuka.
10. Simpan file sumber, hasil preview, total kontrol, dan persetujuan Owner sebagai bukti migrasi.

Jika ada kesalahan setelah stok awal diposting, gunakan Penyesuaian Stok dengan alasan lengkap. Jangan mengulang impor atau mengubah database langsung.

## Pemeriksaan Awal Hari

1. Login dan periksa notifikasi.
2. Pastikan halaman Sistem Health berstatus normal oleh Super Admin.
3. Periksa transaksi draft dari hari sebelumnya.
4. Gudang memeriksa rencana penerimaan dan pengiriman.
5. Admin memeriksa hutang/piutang mendekati jatuh tempo.

## Pembelian dan Penerimaan

1. Admin membuat PO sebagai draft dan memeriksa supplier, gudang, jumlah, harga, metode pembayaran, serta jatuh tempo.
2. Owner atau Super Admin membuka preview PO sebelum menyetujui atau menolak.
3. Gudang hanya menerima barang dari PO berstatus disetujui.
4. Gudang mencatat jumlah aktual dan mengunggah bukti penerimaan.
5. Admin mencatat invoice supplier berdasarkan penerimaan barang.
6. Admin mencatat pembayaran dari akun kas/bank yang benar.

Jika barang atau nilai salah setelah diposting, gunakan retur atau dokumen koreksi. Jangan mengubah dokumen posted.

## Produksi

1. Gudang memilih gudang produksi dan tanggal yang benar.
2. Untuk produksi kemasan, isi hasil baik, reject, dan bahan aktual seperti preform.
3. Untuk produk jadi, pastikan komposisi produk sudah ditetapkan.
4. Simpan sebagai draft dan periksa kembali jumlah sebelum posting.
5. Posting hanya jika bahan fisik tersedia dan hasil produksi sudah dikonfirmasi.

Posting produksi mengubah stok. Jika sistem menolak karena stok tidak cukup atau komposisi belum tersedia, perbaiki data sumber; jangan membuat penyesuaian stok tanpa alasan nyata.

## Penjualan dan Pengiriman

1. Sales memilih pelanggan, gudang, produk, jumlah karton, harga, dan metode pembayaran.
2. Untuk kredit, isi jatuh tempo. Untuk transfer langsung, isi nama bank pengirim.
3. Simpan draft dan periksa total sebelum posting.
4. Gudang membuat surat jalan dari transaksi posted dan mengisi jumlah aktual yang dikirim.
5. Stok baru berkurang ketika surat jalan diposting.
6. Cetak faktur satu kali pada kertas continuous form tiga rangkap.
7. Catat pembayaran pelanggan pada akun penerima yang tepat. Transfer wajib memiliki bukti.

## Stok dan Gudang

- Gunakan transfer gudang untuk perpindahan antar-lokasi.
- Gunakan penyesuaian hanya untuk koreksi yang tidak memiliki dokumen transaksi lain, serta isi alasan lengkap.
- Stok awal hanya dipakai saat migrasi dari pencatatan manual atau pembukaan lokasi baru dengan persetujuan Owner.
- Jangan menghapus produk yang sudah memiliki transaksi; nonaktifkan produk jika tidak lagi digunakan.

## Stok Opname Bulanan

1. Hentikan sementara transaksi stok pada gudang yang sedang dihitung.
2. Buat draft opname dan catat stok fisik setiap produk.
3. Isi penyebab untuk setiap selisih material.
4. Periksa ulang jika sistem memberi tahu bahwa stok berubah setelah draft dibuat.
5. Owner atau Super Admin memeriksa dan memposting hasil opname.
6. Simpan hasil opname sebagai bukti saldo akhir bulan dan acuan bulan berikutnya.

## Kas, Bank, Hutang, dan Piutang

- Pilih akun kas untuk Cash dan akun bank untuk Transfer.
- Pengeluaran manual wajib memiliki kategori dan keterangan spesifik, misalnya pembayaran listrik atau gaji.
- Lakukan rekonsiliasi kas/bank dengan rekening koran atau hasil hitung fisik.
- Periksa aging hutang/piutang dan notifikasi jatuh tempo setiap hari.
- Selisih rekonsiliasi tidak boleh langsung disesuaikan sebelum penyebab ditemukan dan disetujui.

## Tutup Periode

1. Pastikan seluruh penerimaan, produksi, pengiriman, pembayaran, retur, dan opname periode tersebut sudah diposting.
2. Jalankan `php artisan system:check-integrity`.
3. Buat backup dan verifikasi restore.
4. Owner atau Super Admin menutup periode.
5. Transaksi bertanggal pada periode tertutup tidak boleh dipaksakan. Jika koreksi diperlukan, buka kembali periode dengan persetujuan Owner dan catat alasannya.

Laporan laba-rugi belum menjadi laporan resmi sampai metode HPP disepakati dan diaktifkan.

## Akhir Hari

1. Pastikan tidak ada transaksi yang seharusnya posted masih tertinggal sebagai draft.
2. Cocokkan penerimaan dan pengiriman fisik dengan dokumen hari itu.
3. Cocokkan kas dan transfer dengan bukti pembayaran.
4. Tinjau notifikasi gagal, aktivitas tidak biasa, dan audit log bila diperlukan.
5. Logout setelah selesai menggunakan aplikasi.

## Penanganan Kesalahan

- Catat nomor dokumen, waktu, user, dan tangkapan layar pesan error.
- Jangan mengulang tombol simpan berkali-kali; muat ulang halaman dan periksa apakah dokumen sudah tercatat.
- Jangan menghapus file server, menjalankan SQL manual, atau mengubah status dokumen dari database.
- Untuk transaksi salah: gunakan edit ketika masih draft, cancel jika belum memiliki dampak lanjutan, atau retur/koreksi bila sudah posted.
- Jika saldo tidak konsisten, hentikan transaksi terkait dan jalankan pemeriksaan integritas sebelum melanjutkan.

## Backup dan Insiden

- Super Admin membuat backup sebelum deployment, migrasi besar, atau impor data massal.
- Backup dinyatakan layak hanya setelah `system:verify-backup` berhasil.
- Salinan backup harus tersedia di lokasi eksternal terpisah.
- Saat aplikasi tidak dapat diakses, jangan kembali ke pencatatan sistem secara sebagian. Gunakan formulir manual bernomor, lalu masukkan transaksi secara berurutan setelah sistem pulih dan tandai referensi dokumen manualnya.
