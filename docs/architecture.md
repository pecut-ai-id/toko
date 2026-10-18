# Arsitektur TokoFlow

Dokumen ini menjelaskan susunan aplikasi, model data, dan aturan penting
yang berlaku di dalam kode. Kebutuhan produknya ada di
`requirements.md`; alasan di balik pilihan teknis ada di `DECISIONS.md`.

## 1. Ringkasan teknis

| Aspek | Pilihan |
| --- | --- |
| Framework | Laravel 13 (Blade, tanpa starter kit) |
| Bahasa | PHP 8.4 |
| Database | MySQL 8 / InnoDB, `utf8mb4` |
| Frontend | Blade + Tailwind CSS 4 + Vite |
| Autentikasi | Guard `web` bawaan Laravel, login manual |
| Testing | PHPUnit, database uji terpisah (`tokoflow_test`) |
| Multi-tenant | Satu instalasi melayani banyak bisnis (`businesses`) |

Struktur direktori yang penting:

```
app/
  Enums/            BusinessRole, PurchaseStatus, SaleStatus, AuditAction
  Http/Controllers/ controller per modul (produk, supplier, pelanggan, ...)
  Http/Middleware/  ResolveCurrentBusiness, EnsureCurrentBusiness
  Http/Requests/    validasi per form (+ Concerns/FiltersByPeriod)
  Models/           model Eloquent (+ Concerns: BelongsToBusiness, ...)
  Policies/         otorisasi per peran
  Services/         InventoryService, PurchaseService, SaleService,
                    PaymentService, ReportingService, AuditLogger
  Support/          CurrentBusiness, Money, ReportPeriod, Csv, helpers.php
database/migrations/ 20 tabel domain + tabel bawaan Laravel
resources/views/      Blade per modul + components/
routes/web.php        seluruh route web
tests/                Unit + Feature (per modul)
```

## 2. Model data

Tabel domain (20):

| Tabel | Isi |
| --- | --- |
| `businesses` | Entitas bisnis (tenant) |
| `business_user` | Keanggotaan pengguna di bisnis + `role` |
| `products` | Produk: SKU, nama, satuan, harga jual, harga pokok default, `stock` |
| `suppliers` | Supplier |
| `customers` | Pelanggan/reseller |
| `purchases` | Header pembelian + status + jejak penerimaan/pembatalan |
| `purchase_items` | Item pembelian (kuantitas, `unit_cost` saat transaksi) |
| `sales` | Header penjualan + status + jejak pembatalan |
| `sale_items` | Item penjualan (snapshot `unit_price` dan `unit_cost`) |
| `payments` | Pembayaran polimorfik untuk penjualan maupun pembelian |
| `inventory_movements` | Buku besar pergerakan stok (ledger) |
| `audit_logs` | Ringkasan jejak audit aktivitas penting |

Tabel bawaan Laravel yang juga ada: `users`, `sessions`,
`password_reset_tokens`, `cache`, `cache_locks`, `jobs`, `job_batches`,
`failed_jobs`, dan `migrations`.

Relasi utama:

-   `businesses` 1—N seluruh tabel domain (kolom `business_id`).
-   `businesses` N—N `users` lewat `business_user` (kolom `role`).
-   `purchases` 1—N `purchase_items`; `sales` 1—N `sale_items`.
-   `payments` polimorfik: `payable_type`/`payable_id` menunjuk `sales`
    atau `purchases` (D-021).
-   `inventory_movements.source_type`/`source_id` menunjuk dokumen
    pemicu (pembelian, penjualan, atau `null` untuk penyesuaian manual).

## 3. Konteks bisnis dan otorisasi

-   `ResolveCurrentBusiness` menentukan bisnis aktif per request (dari
    session, atau keanggotaan aktif pertama) dan memverifikasi ulang
    keanggotaan di server; `EnsureCurrentBusiness` menolak request bila
    tidak ada konteks bisnis.
-   Konteks dibagikan lewat singleton `App\Support\CurrentBusiness`.
    Query domain dibatasi `business_id` melalui trait
    `BelongsToBusiness` dan pemeriksaan eksplisit (D-009).
-   Peran disimpan di pivot `business_user.role` (`owner`, `admin`,
    `staff`) sehingga satu pengguna bisa punya peran berbeda di bisnis
    berbeda (D-009).
-   Izin diperiksa di server lewat Policy, bukan hanya menyembunyikan
    tombol di UI. Ringkasnya:

| Aksi | owner | admin | staff |
| --- | --- | --- | --- |
| Lihat data master, laporan, riwayat audit | ya | ya | ya |
| Kelola data master (produk/supplier/pelanggan) | ya | ya | tidak |
| Buat draf pembelian, terima pembelian, catat penjualan | ya | ya | ya |
| Catat pembayaran | ya | ya | ya |
| Batalkan pembelian/penjualan | ya | ya | tidak |
| Batalkan (void) pembayaran | ya | ya | tidak |
| Sesuaikan stok manual | ya | ya | tidak |
| Kelola anggota bisnis | ya | ya | tidak |

-   Penghapusan data master bersifat non-destruktif: baris tidak dihapus,
    hanya `is_active = false` (D-011).

## 4. Alur transaksi

Semua operasi yang mengubah stok atau saldo berjalan di dalam satu
database transaction (D-002, D-015).

### Penerimaan pembelian

1.  Draf pembelian disimpan beserta itemnya.
2.  Saat penerimaan dikonfirmasi (`PurchaseService::receive()`), status
    berubah menjadi `received` dan stok bertambah lewat
    `InventoryService::record()` sehingga setiap perubahan stok punya
    baris ledger dengan `balance_after`.
3.  Harga pokok default produk (`products.cost_price`) disinkronkan ke
    `unit_cost` pembelian tersebut (D-027).

### Penjualan

1.  Penjualan langsung mengurangi stok (tanpa fase draf), status
    `completed` (D-018).
2.  Harga jual dan harga pokok disimpan sebagai snapshot di
    `sale_items` (`unit_price`, `unit_cost`), sehingga perubahan harga
    produk tidak mengubah riwayat (D-019).
3.  Total dihitung ulang di server; penjualan ditolak bila stok tidak
    cukup, dan tidak ada perubahan parsial (rollback penuh).

### Pembayaran

1.  Satu tabel `payments` melayani penjualan (piutang) dan pembelian
    (utang) lewat relasi polimorfik (D-021).
2.  Sisa saldo selalu dihitung dari total dikurangi pembayaran aktif,
    tidak disimpan sebagai kolom agar tidak bisa tidak sinkron.
3.  Pembayaran nol/negatif dan pembayaran melebihi saldo ditolak; saldo
    dibaca ulang dengan `lockForUpdate()` (D-022).
4.  Pembayaran yang salah dibatalkan dengan **void**, bukan dihapus:
    barisnya tetap ada dengan `voided_at`, `voided_by`, dan
    `void_reason`, tetapi tidak dihitung pada saldo (D-026).
    Pengembalian dana fisik dilakukan di luar sistem.

### Pembatalan dokumen

-   Pembelian/penjualan yang dibatalkan tidak dihapus: statusnya
    berubah dan stok dikembalikan lewat pergerakan reversal yang
    tercatat (D-015, D-020).
-   Pembatalan ditolak seluruhnya (rollback) bila akan membuat stok
    negatif, dan hanya bisa dilakukan oleh owner/admin (D-017, D-024).

### Penyesuaian stok manual

-   Owner/admin dapat menambah/mengurangi stok dengan alasan wajib
    (minimal 3 karakter) lewat `InventoryService` (D-028); pergerakan
    seperti ini tidak punya dokumen sumber (`source = null`).
-   Angka `products.stock` tidak pernah diubah langsung dari luar
    `InventoryService` (D-013).

## 5. Pelaporan

`App\Services\ReportingService` adalah satu-satunya sumber definisi
angka dashboard dan laporan (D-029):

-   Rentang tanggal inklusif; bawaan = awal bulan berjalan sampai hari
    ini. Validasi di `ReportRequest` (memanfaatkan
    `Http/Requests/Concerns/FiltersByPeriod`).
-   Piutang dan utang adalah saldo berjalan (tidak dibatasi tanggal).
-   Nilai persediaan mencakup produk nonaktif; "stok menipis" memakai
    ambang `ReportingService::LOW_STOCK_THRESHOLD` (5) untuk produk
    aktif.
-   Setiap laporan dapat diekspor CSV (`?format=csv`) dengan pemisah
    `;`, BOM UTF-8, dan angka desimal polos (D-030).

## 6. Jejak audit

-   `audit_logs` menyimpan ringkasan perubahan, bukan salinan baris, dan
    barisnya tidak pernah diubah (tanpa `updated_at`, tanpa route tulis).
-   Penulisan hanya lewat `App\Services\AuditLogger`, di dalam
    transaction milik pemanggil: aksi yang ditolak tidak meninggalkan
    baris audit (D-031).
-   Yang dicatat: pembelian (dibuat/diterima/dibatalkan), penjualan
    (dibuat/dibatalkan), pembayaran (dicatat/di-void), penyesuaian stok,
    serta pembuatan/perubahan/penonaktifan produk, supplier, dan
    pelanggan. Kolom `changes` hanya memuat field yang berubah dan
    aman-diaudit (tanpa password/token).
-   Pelaku diambil dari pengguna yang sedang login; operasi tanpa
    pengguna (seeder, perintah artisan) tidak menulis jejak.

## 7. Konvensi

-   **Uang**: kolom `decimal(15,2)`; parsing input Indonesia
    (mis. `85.000` → `85000.00`) lewat `App\Support\Money`. Helper
    global `rupiah()` dan `angka()` memformat tampilan.
-   **Nomor dokumen**: `PB-YYYYMMDD-####` untuk pembelian dan
    `PJ-YYYYMMDD-####` untuk penjualan, unik per bisnis, dibuat di
    dalam transaction setelah `lockForUpdate()` (D-016, D-020).
-   **Tanggal**: dokumen memakai kolom `date` (`purchased_at`,
    `sold_at`), sedangkan jejak waktu teknis memakai timestamp.
-   **Status**: enum PHP (`PurchaseStatus`, `SaleStatus`) dengan status
    terbuka untuk pembelian = `received` dan penjualan = `completed`;
    keduanya menjadi dasar penyaring "belum lunas"/"sudah lunas"
    (D-032).

## 8. Batasan yang diketahui

-   Pengembalian dana fisik tidak tercatat di sistem; pembayaran yang
    dibatalkan hanya di-void.
-   `products.cost_price` adalah harga beli terakhir yang valid, bukan
    rata-rata tertimbang (D-027).
-   Pergerakan stok dari penyesuaian manual tidak punya dokumen sumber.
-   Belum ada pengujian konkurensi dengan proses paralel sungguhan;
    proteksi bergantung pada transaction + `lockForUpdate()`.
-   Angka dashboard dan laporan dihitung ulang setiap request (belum ada
    tabel ringkasan/cache).
-   Ambang stok menipis adalah konstanta kode (5), belum dapat diatur
    dari UI.
-   Jejak audit tidak punya ekspor CSV maupun kebijakan retensi, dan
    hanya berisi aktivitas setelah tabel audit dibuat.
-   Interaksi baris dinamis di form (tambah/hapus item) mengandalkan
    JavaScript bawaan tanpa pengujian browser otomatis.
