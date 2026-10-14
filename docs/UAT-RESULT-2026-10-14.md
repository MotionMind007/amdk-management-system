# Hasil UAT Otomatis — 14 Oktober 2026

## Ringkasan

| Pemeriksaan | Hasil |
| --- | --- |
| Baseline lintas role `composer test:uat` | Lulus — 35 test, 207 assertion |
| Dua full regression awal | Lulus — masing-masing 206 test, 1 skipped, 1.023 assertion |
| Full regression final setelah perbaikan UI | Lulus — 206 test, 1 skipped, 1.025 assertion |
| Dua suite backup/import berjalan bersamaan | Keduanya lulus — masing-masing 12 test dan 53 assertion |
| Integritas stok, piutang, hutang, dan kas | Seluruh area OK |
| Audit dependency PHP dan npm | Tidak ditemukan vulnerability |
| UAT browser desktop dan mobile | Lulus |

Satu test deployment dilewati pada SQLite karena validasi production PostgreSQL dijalankan pada pipeline CI yang menyediakan PostgreSQL 17.

## Cakupan yang Terbukti Otomatis

- Seeder UAT dapat diulang dan ditolak di production.
- Admin, Gudang, Sales, Super Admin, dan Owner memperoleh akses sesuai matriks role.
- Owner dan Super Admin dapat menyetujui PO; role lain ditolak.
- Siklus pembelian, penerimaan, invoice supplier, dan pembayaran tersambung.
- Produksi kemasan dan produk jadi mengubah stok secara konsisten.
- Penjualan, surat jalan, pembayaran pelanggan, kas, dan piutang tersambung.
- Notifikasi, preview dokumen, audit log, dan pemeriksaan integritas berfungsi.
- Test yang memakai local storage terisolasi meskipun dua suite berjalan bersamaan.

## UAT Browser Lokal

Pengujian dilakukan pada server lokal dengan data dummy UAT dan lima role:

- **Owner:** seluruh modul, status kesehatan sistem, preview Purchase Order, dan preview faktur dapat diakses.
- **Admin:** seluruh modul operasional selain Produksi dan Sistem Admin dapat diakses; akses langsung ke dua area tersebut menghasilkan `403`.
- **Gudang:** Produksi, Stok, Pembelian, dan preview PO dapat diakses; Penjualan menghasilkan `403`.
- **Sales:** Penjualan dan Stok dapat diakses; Keuangan menghasilkan `403`.
- **Super Admin:** seluruh modul, termasuk Manajemen User, Produksi, dan Tutup Periode, dapat diakses.

Tampilan halaman modul dan form penjualan juga diperiksa pada viewport ponsel `390 x 844`. Preview PO serta faktur diuji pada mode layar dan cetak. Tata letak dokumen sesuai ukuran kertas continuous form `9,5 x 5,5 inci` dan ditujukan untuk sekali cetak pada kertas NCR tiga rangkap.

Empat template migrasi data—Master Produk, Pelanggan, Supplier, dan Gudang—tersedia pada halaman Sistem Admin. Alur preview dan konfirmasi impor juga tercakup oleh test otomatis.

## Temuan dan Perbaikan

- Label harga pada form penjualan diperjelas dari `Harga/Satuan` menjadi `Harga/Karton`.
- Kartu saldo kini menampilkan nama rekening, misalnya `Saldo Kas UAT` dan `Saldo Bank UAT`, sehingga tidak ambigu ketika tersedia lebih dari satu rekening.
- Kedua perbaikan diverifikasi ulang pada viewport desktop dan ponsel tanpa error console browser.

## Persiapan Release Candidate Lokal

- Backup dibuat melalui halaman Sistem Admin oleh akun Owner dan tercatat pada audit log.
- Backup `database-20261014-121420-sbfavq.sql` berhasil dipulihkan ke database sementara tanpa mengubah database aktif.
- Hasil verifikasi backup: checksum SHA-256 valid, 51 tabel, dan 512 baris.
- Setelah simulasi restore, integritas stok, piutang, hutang, serta kas/bank tetap berstatus OK.
- Rehearsal backup, impor master data, dan alur operasional: 13 test lulus dengan 107 assertion.
- Pemeriksaan halaman melalui browser tidak menemukan error console.

Pemeriksaan kesiapan deployment lokal sengaja belum lulus karena mesin ini masih memakai environment `local`, HTTP, SQLite, debug log, dan belum memiliki binary PostgreSQL. Delapan kegagalan tersebut merupakan konfigurasi yang baru dapat dipenuhi pada staging/VPS. Dua pekerjaan go-live lain yang masih terbuka adalah penyimpanan backup offsite dan optimasi Laravel pada proses deployment.

## Status Persetujuan

Baseline teknis: **LULUS**.

Persetujuan user bisnis: **MENUNGGU SIGN-OFF** menggunakan lembar pada `docs/UAT.md`. Sign-off tetap diperlukan untuk memastikan istilah, tampilan cetak, dan urutan kerja sesuai kondisi nyata pabrik.
