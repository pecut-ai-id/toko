# Men-deploy TokoFlow ke VPS

Panduan ini menargetkan VPS **Ubuntu 24.04** dengan nginx, PHP-FPM 8.4,
dan MySQL 8. Contoh domain: `tokoflow.example.com`; sesuaikan dengan
domain Anda. Untuk kebutuhan minimum server, **1 vCPU / 1 GB RAM sudah
cukup** untuk pemakaian kecil (nginx, PHP-FPM, dan MySQL berjalan di
mesin yang sama).

> Catatan: aplikasi ini **tidak memakai queue worker maupun task
> scheduler**, jadi tidak diperlukan Supervisor atau cron untuk
> menjalankan aplikasi. Cron hanya disarankan untuk backup database
> (langkah 10).

## 1. Paket dasar

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mysql-server git unzip curl ufw
```

PHP 8.4 belum ada di repositori bawaan Ubuntu 24.04 (bawaannya 8.3),
jadi tambahkan PPA:

```bash
sudo apt install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring \
  php8.4-xml php8.4-curl php8.4-zip php8.4-bcmath php8.4-gd
```

Composer:

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
composer -V
```

Node.js hanya dipakai untuk membangun aset. Bila ingin membangun di
VPS:

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo bash -
sudo apt install -y nodejs
```

(Alternatif: bangun aset di lokal dengan `npm run build`, lalu unggah
folder `public/build` ke VPS. Dengan cara ini Node tidak perlu ada di
VPS.)

## 2. Database

```bash
sudo mysql
```

```sql
CREATE DATABASE tokoflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'tokoflow'@'127.0.0.1' IDENTIFIED BY 'KataSandiProduksi1!';
GRANT ALL PRIVILEGES ON tokoflow.* TO 'tokoflow'@'127.0.0.1';
FLUSH PRIVILEGES;
EXIT;
```

`mysql_secure_installation` disarankan untuk menghapus akun anonim dan
mengunci akses root dari luar.

## 3. Ambil kode

```bash
sudo mkdir -p /var/www/tokoflow
sudo chown -R $USER:www-data /var/www/tokoflow
git clone https://github.com/pecut-ai-id/toko.git /var/www/tokoflow
cd /var/www/tokoflow
```

URL di atas memakai HTTPS sehingga tidak perlu kunci SSH di VPS. Bila
lebih suka SSH, ganti dengan `git@github.com:pecut-ai-id/toko.git` dan
pasang deploy key di repositori.

## 4. Konfigurasi `.env` produksi

```bash
cp .env.example .env
php artisan key:generate
```

Sunting `.env`:

```dotenv
APP_NAME=TokoFlow
APP_ENV=production
APP_KEY=            # terisi otomatis oleh key:generate
APP_DEBUG=false
APP_URL=https://tokoflow.example.com

APP_LOCALE=id
APP_FALLBACK_LOCALE=id

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tokoflow
DB_USERNAME=tokoflow
DB_PASSWORD=KataSandiProduksi1!

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database
MAIL_MAILER=log
```

Catatan penting:

-   `APP_DEBUG=false` wajib; `APP_ENV=production` mematikan halaman
    debug dan menampilkan error 500 yang bersih.
-   `APP_URL` harus sesuai domain dan skema (https), karena dipakai untuk
    membentuk URL aset dan tautan.
-   `SESSION_SECURE_COOKIE=true` hanya bila HTTPS sudah aktif
    (langkah 9). Bila belum, set `false` dulu.
-   Jangan commit `.env`; berkas ini sudah diabaikan Git.

## 5. Pasang dependency dan migrasi

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force --seed
php artisan storage:link
```

-   `--no-dev` tidak memasang perkakas pengujian.
-   `--force` diperlukan karena environment produksi.
-   `--seed` membuat bisnis contoh dan dua akun demo. **Segera ganti
    kata sandi akun demo** setelah login pertama, atau lewati `--seed`
    dan buat pengguna sendiri bila data contoh tidak diinginkan.

## 6. Bangun aset frontend

```bash
npm ci
npm run build
```

Yakinkan `public/build/manifest.json` ada. Aplikasi akan gagal
menampilkan halaman tanpa berkas build (error "Vite manifest not
found"). Pada deploy berikutnya cukup jalankan `npm ci && npm run build`
lagi (atau unggah ulang `public/build`).

## 7. Izin berkas

```bash
sudo chown -R www-data:www-data /var/www/tokoflow/storage /var/www/tokoflow/bootstrap/cache
sudo chmod -R 775 /var/www/tokoflow/storage /var/www/tokoflow/bootstrap/cache
```

Sisa berkas aplikasi cukup dibaca oleh `www-data`; hanya `storage/` dan
`bootstrap/cache/` yang perlu bisa ditulis.

## 8. nginx

Buat `/etc/nginx/sites-available/tokoflow`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name tokoflow.example.com;
    root /var/www/tokoflow/public;

    index index.php;
    charset utf-8;
    client_max_body_size 20m;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.4-fpm.sock;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/tokoflow /etc/nginx/sites-enabled/tokoflow
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

Pastikan nama socket cocok dengan milik sistem
(`ls /run/php/`). Bila berbeda, sesuaikan `fastcgi_pass`.

Optimalkan Laravel untuk produksi:

```bash
php artisan optimize
```

(`config:cache`, `route:cache`, `view:cache` dalam satu perintah.
Jalankan `php artisan optimize:clear` setiap kali `.env` berubah,
lalu `php artisan optimize` lagi.)

## 9. HTTPS dan firewall

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d tokoflow.example.com
```

Certbot akan memasang sertifikat dan mengalihkan HTTP ke HTTPS, serta
mengatur perpanjangan otomatis. Setelah HTTPS aktif, pastikan
`APP_URL=https://...` dan `SESSION_SECURE_COOKIE=true`, lalu jalankan
`php artisan optimize:clear && php artisan optimize`.

Firewall:

```bash
sudo ufw allow OpenSSH
sudo ufw allow 'Nginx Full'
sudo ufw enable
sudo ufw status
```

MySQL tidak perlu dibuka; klien dari dalam server memakai
`127.0.0.1`.

## 10. Backup

Backup database harian (pukul 02.00) dengan cron:

```bash
sudo mkdir -p /var/backups/tokoflow
sudo tee /etc/cron.daily/tokoflow-backup >/dev/null <<'SH'
#!/bin/sh
set -e
mkdir -p /var/backups/tokoflow
mysqldump --single-transaction tokoflow | gzip > /var/backups/tokoflow/tokoflow-$(date +%F).sql.gz
find /var/backups/tokoflow -name 'tokoflow-*.sql.gz' -mtime +14 -delete
SH
sudo chmod +x /etc/cron.daily/tokoflow-backup
```

Kredensial `mysqldump` sebaiknya ditaruh di `~/.my.cnf` (mode `600`)
milik root agar tidak muncul di daftar proses. Salin hasil backup ke
luar VPS secara berkala. Berkas unggahan tidak dipakai aplikasi ini,
jadi backup database sudah mencakup seluruh data operasional.

## 11. Deploy ulang (pembaruan versi)

```bash
cd /var/www/tokoflow
php artisan down --render="errors::503"     # opsional: mode perawatan
git pull origin main

composer install --no-dev --optimize-autoloader
php artisan migrate --force
npm ci && npm run build

php artisan optimize:clear
php artisan optimize
sudo systemctl reload php8.4-fpm
php artisan up
```

Periksa `storage/logs/laravel.log` bila halaman gagal tampil.

## 12. Bila ada masalah

| Gejala | Penyebab & solusi |
| --- | --- |
| 502 Bad Gateway | `fastcgi_pass` menunjuk socket yang salah; cek `ls /run/php/` dan status `systemctl status php8.4-fpm`. |
| 403 Forbidden | `root` nginx bukan `.../public`, atau izin berkas tidak bisa dibaca `www-data`. |
| Halaman putih / 500 | Baca `storage/logs/laravel.log`; biasanya `APP_KEY` kosong, `.env` salah, atau aset belum dibangun. |
| `Vite manifest not found` | Jalankan `npm ci && npm run build`. |
| Perubahan `.env` tidak berefek | `php artisan optimize:clear && php artisan optimize`. |
| `Access denied` MySQL | Host user harus `127.0.0.1` (bukan `localhost`) dan kata sandi `.env` harus sama. |
| Gaya tampilan tidak muncul | Aset ter-cache di browser atau `public/build` belum tersalin; muat ulang dengan cache bersih. |

## 13. Checklist keamanan produksi

-   `APP_DEBUG=false`, `APP_ENV=production`.
-   Tidak ada rahasia di Git; `.env` hanya ada di server (mode `600`).
-   HTTPS aktif dan cookie sesi aman.
-   Akun demo sudah diganti atau dihapus.
-   `ufw` aktif, hanya SSH dan nginx yang terbuka.
-   Backup berjalan dan pernah diuji dipulihkan.
