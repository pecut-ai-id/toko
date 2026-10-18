# Menjalankan TokoFlow di Komputer Lokal

Panduan ini menuntun dari nol sampai aplikasi berjalan di laptop/PC
(development), termasuk cara menjalankan tes. Untuk server produksi,
lihat `vps.md`.

## 1. Prasyarat

| Perangkat | Versi yang dipakai saat pengembangan |
| --- | --- |
| PHP | 8.4 (minimal 8.3) |
| Ekstensi PHP | `ctype`, `curl`, `dom`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml` |
| Composer | 2.x |
| Node.js + npm | Node 20+ (diuji di Node 24) |
| MySQL | 8.0 (InnoDB, `utf8mb4`) |

Cek cepat:

```bash
php -v && composer -V && node -v && npm -v && mysql --version
```

## 2. Ambil kode dan pasang dependency

```bash
git clone https://github.com/pecut-ai-id/toko.git tokoflow
cd tokoflow

composer install
cp .env.example .env
php artisan key:generate
npm install
```

## 3. Siapkan database

Aplikasi memakai dua database: satu untuk development (`tokoflow`) dan
satu untuk pengujian (`tokoflow_test`, dipakai otomatis oleh PHPUnit
lewat `phpunit.xml`).

Masuk ke MySQL sebagai root:

```bash
sudo mysql
```

lalu jalankan (ganti kata sandinya dengan kata sandi kuat milik Anda;
bila `validate_password` aktif, kata sandi wajib memuat huruf besar,
huruf kecil, angka, dan karakter khusus):

```sql
CREATE DATABASE tokoflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE tokoflow_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'tokoflow'@'127.0.0.1' IDENTIFIED BY 'GantiKataSandiIni1!';
GRANT ALL PRIVILEGES ON tokoflow.* TO 'tokoflow'@'127.0.0.1';
GRANT ALL PRIVILEGES ON tokoflow_test.* TO 'tokoflow'@'127.0.0.1';
FLUSH PRIVILEGES;
EXIT;
```

Sesuaikan koneksi di `.env`:

```dotenv
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tokoflow
DB_USERNAME=tokoflow
DB_PASSWORD=GantiKataSandiIni1!
```

## 4. Migrasi dan data contoh

```bash
php artisan migrate --seed
```

Perintah ini membuat seluruh tabel sekaligus satu bisnis contoh
(`TokoFlow Demo`) dengan dua akun:

| Peran | Email | Kata sandi |
| --- | --- | --- |
| Owner | `owner@tokoflow.test` | `password` |
| Staff | `staff@tokoflow.test` | `password` |

Kedua akun itu **hanya untuk demo**. Ganti kata sandinya (atau buat
pengguna baru) sebelum dipakai dengan data sungguhan. Seeder membuat
data contoh lewat factory, jadi menjalankannya dua kali pada database
yang sama akan gagal karena email akun demo sudah terpakai (unique
constraint). Untuk mengulang dari nol:

```bash
php artisan migrate:fresh --seed
```

## 5. Build aset frontend dan jalankan aplikasi

```bash
npm run build          # kompilasi Tailwind + JS ke public/build
php artisan serve      # http://127.0.0.1:8000
```

Buka `http://127.0.0.1:8000`, lalu login dengan akun demo di atas. Root
`/` otomatis mengarahkan ke halaman login (pengunjung) atau dashboard
(pengguna yang sudah login).

Untuk kerja harian ada satu perintah yang menjalankan server, Vite,
penampil log, dan queue listener sekaligus:

```bash
composer run dev
```

## 6. Menjalankan tes

```bash
npm run build          # aset harus ada karena beberapa tes merender halaman
php artisan test
```

-   Tes berjalan di database `tokoflow_test` (lihat `phpunit.xml`), jadi
    data development Anda tidak tersentuh.
-   Database uji harus sudah dibuat (langkah 3) dan boleh berisi tabel
    apa pun: setiap tes memakai `RefreshDatabase`.
-   `composer test` menjalankan hal yang sama dengan pembersihan cache
    konfigurasi lebih dulu.

## 7. Ringkasan alur singkat

```bash
# sekaligus, untuk lingkungan baru yang database-nya sudah ada
composer run setup     # composer install + .env + key + migrate + npm build
php artisan migrate --seed
php artisan serve
```

`composer run setup` tidak membuat database, tidak menjalankan seeder,
dan tidak menyiapkan `tokoflow_test`; langkah 3 tetap perlu dilakukan
sekali.

## 8. Bila ada masalah

| Gejala | Penyebab & solusi |
| --- | --- |
| `Vite manifest not found` | Aset belum dibangun. Jalankan `npm run build` (atau `npm run dev` saat mengembangkan). |
| `SQLSTATE[HY000] [1045] Access denied` | Kredensial di `.env` salah, atau host user MySQL bukan `127.0.0.1`. |
| `SQLSTATE[HY000] [1049] Unknown database` | Database `tokoflow`/`tokoflow_test` belum dibuat (langkah 3). |
| Perubahan `.env` tidak berefek | Jalankan `php artisan config:clear`. |
| Halaman putih / error 500 | Lihat `storage/logs/laravel.log`; pastikan `.env` berisi `APP_KEY` hasil `php artisan key:generate`. |
| Tes gagal semua karena koneksi | `tokoflow_test` belum ada atau user MySQL belum diberi hak ke database itu. |
