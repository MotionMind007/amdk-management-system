# Sistem Operasional AMDK

Sistem internal PT Air Minum Jayapura untuk pencatatan produksi, stok multi-gudang, pembelian, penjualan, kas/bank, piutang, hutang, karyawan, laporan operasional, dan audit aktivitas.

## Kebutuhan Lokal

- PHP 8.4 dengan ekstensi BCMath, PDO, Mbstring, dan driver database
- Composer 2
- Node.js 22 dan npm
- SQLite untuk pengembangan lokal atau PostgreSQL 17 untuk staging/production

## Instalasi Lokal

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
npm ci
npm run build
php artisan serve
```

Atur `ADMIN_PASSWORD` minimal delapan karakter sebelum menjalankan seeder awal:

```bash
php artisan db:seed
```

Setelah akun dibuat, kosongkan kembali `ADMIN_PASSWORD` dari environment dan ubah password melalui aplikasi.

## Quality Check

```bash
php artisan test --compact
php artisan system:check-integrity
composer audit
npm audit --audit-level=high
```

Baseline UAT lintas role dapat dijalankan berulang dengan:

```bash
composer test:uat
```

## Data UAT

Data dummy hanya boleh dibuat di lingkungan non-production. Atur `UAT_PASSWORD`, lalu jalankan:

```bash
php artisan db:seed --class=UatSeeder
```

Detail skenario, akun, bukti hasil, dan formulir persetujuan tersedia di [Panduan UAT](docs/UAT.md).

## Operasional dan Deployment

- [Panduan deployment dan recovery](docs/DEPLOYMENT.md)
- [SOP operasional](docs/SOP-OPERASIONAL.md)

Jangan menyalin `.env`, database lokal, file backup, bukti transaksi, atau folder `storage` ke repository.
