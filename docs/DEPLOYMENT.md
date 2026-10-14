# Deployment dan Recovery

Panduan ini disiapkan sebelum server production tersedia. Sesuaikan path, user service, domain, dan mekanisme secret dengan penyedia VPS atau cloud yang dipilih.

## Arsitektur Minimum

- Linux server dengan HTTPS
- PHP 8.4 dan ekstensi BCMath, Mbstring, PDO PostgreSQL, OpenSSL, Fileinfo, dan XML
- Nginx atau Apache dengan document root ke folder `public`
- PostgreSQL 17
- Composer 2 dan Node.js 22 pada proses build
- Satu web process, satu queue worker, dan satu scheduler cron
- Penyimpanan backup eksternal yang terpisah dari VPS

Untuk sekitar 30 user internal, satu application server dan satu PostgreSQL terkelola atau terpisah sudah cukup sebagai tahap awal.

## Environment Production

Salin `.env.example` menjadi `.env` di server dan setidaknya ubah:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain-perusahaan.example
APP_TIMEZONE=Asia/Jayapura
LOG_CHANNEL=stderr
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=amdk
DB_USERNAME=amdk_app
DB_PASSWORD=gunakan-secret-manager

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
QUEUE_CONNECTION=database
CACHE_STORE=database
```

Jangan menyimpan `.env`, password database, `APP_KEY`, kredensial email, atau kredensial object storage di Git.

## Provisioning Pertama

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan key:generate
php artisan storage:link
php artisan migrate --force
php artisan db:seed --force
php artisan optimize
php artisan system:check-deployment
php artisan system:check-integrity
```

Sebelum `db:seed`, isi `ADMIN_NAME`, `ADMIN_EMAIL`, dan `ADMIN_PASSWORD`. Setelah akun Super Admin berhasil dibuat, hapus `ADMIN_PASSWORD` dari environment agar deployment berikutnya tidak mengubah password akun tersebut.

Jangan menjalankan `UatSeeder` di production. Seeder tersebut memiliki pengaman environment, tetapi tetap tidak boleh dimasukkan ke prosedur deployment.

## Deployment Versi Berikutnya

1. Buat backup dan verifikasi restore sebelum perubahan besar.
2. Aktifkan maintenance mode.
3. Ambil commit/tag release yang sudah lulus CI dan UAT.
4. Instal dependency production dan build aset.
5. Jalankan migrasi.
6. Bersihkan serta bangun cache Laravel.
7. Restart queue worker dan PHP process.
8. Nonaktifkan maintenance mode.
9. Jalankan health check, integrity check, dan smoke test login.

```bash
php artisan system:verify-backup
php artisan down --retry=60
git fetch --tags
git checkout <release-tag>
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan optimize
php artisan queue:restart
php artisan up
php artisan system:check-deployment
php artisan system:check-integrity
```

Jalankan perintah deployment menggunakan user service yang memiliki akses ke project, bukan sebagai `root`.

## Queue dan Scheduler

Queue worker harus dikelola oleh systemd, Supervisor, atau process manager penyedia hosting:

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=90
```

Tambahkan cron berikut menggunakan user service aplikasi:

```cron
* * * * * cd /path/to/amdk && php artisan schedule:run >> /dev/null 2>&1
```

Scheduler menjalankan pengingat jatuh tempo setiap hari. Pantau `failed_jobs` dan restart worker setelah deployment.

## Backup dan Restore

Backup dari halaman Sistem Admin disimpan pada private storage. Salin backup secara otomatis ke object storage atau server kedua dengan retensi yang disetujui perusahaan.

Baseline retensi yang disarankan:

- harian: 14 salinan;
- mingguan: 8 salinan;
- bulanan: 12 salinan.

Verifikasi backup:

```bash
php artisan system:verify-backup
```

Verifikasi PostgreSQL membuat database sementara. Jalankan melalui prosedur maintenance dengan kredensial khusus yang memiliki izin membuat dan menghapus database; jangan memberikan izin tersebut secara permanen kepada akun aplikasi web.

Prosedur recovery:

1. Aktifkan maintenance mode dan hentikan queue worker.
2. Simpan salinan database rusak untuk investigasi.
3. Pulihkan backup terlebih dahulu ke database sementara.
4. Jalankan migrasi yang sesuai versi release dan `system:check-integrity`.
5. Arahkan aplikasi ke database hasil recovery hanya setelah pemeriksaan lulus.
6. Jalankan smoke test login, stok, piutang, hutang, dan kas.
7. Hidupkan kembali worker lalu nonaktifkan maintenance mode.

Jangan menguji restore dengan menimpa database production aktif.

## Rollback

Rollback kode dilakukan dengan kembali ke tag release sebelumnya. Rollback database hanya boleh dilakukan jika migration `down()` sudah ditinjau dan backup sebelum deployment tersedia. Untuk migrasi yang menghapus atau mengubah data, pilih forward fix atau restore backup setelah mendapat persetujuan Owner.

## Checklist Go-Live

- CI pada commit release lulus.
- UAT ditandatangani semua role dan Owner.
- `APP_DEBUG=false` dan HTTPS aktif.
- PostgreSQL, queue, cache, session, dan scheduler aktif.
- `php artisan system:check-deployment` lulus.
- `php artisan system:check-integrity` lulus.
- Backup offsite dan simulasi restore berhasil.
- Monitoring uptime, error log, kapasitas disk, queue, dan database aktif.
- Akun dummy UAT tidak ada di production.
