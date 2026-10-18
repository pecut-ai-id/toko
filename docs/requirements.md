# Kebutuhan Produk --- TokoFlow MVP

Dokumen ini merangkum kebutuhan fungsional dan kriteria penerimaan MVP
TokoFlow. Isinya menjadi acuan implementasi, pengujian, dan definisi
selesai; keputusan teknis yang mengikat ada di `DECISIONS.md`.

## 1. Tujuan produk

TokoFlow adalah aplikasi web untuk membantu distributor dan reseller
mengelola produk, supplier, pelanggan, pembelian, persediaan, penjualan,
pembayaran, piutang/utang, dan laporan.

Tujuan MVP adalah membuktikan alur transaksi yang benar dan dapat diuji,
bukan sekadar membuat dashboard yang menarik.

## 2. Teknologi dan batasan

-   Laravel 13, PHP 8.4-compatible, MySQL 8/InnoDB.
-   Ikuti pola starter project yang sudah ada; jangan menambah framework
    frontend atau dependency besar tanpa alasan.
-   Jangan commit `.env`, token, password, private key, dump database,
    atau data rahasia.
-   Di luar MVP: multi-gudang, WhatsApp, AI di dalam aplikasi, payment
    gateway, aplikasi mobile native, dan akuntansi penuh.

## 3. Peran dan akses

-   Owner/Admin: mengelola data master, pengguna, transaksi, dan
    laporan.
-   Staff: melakukan operasional sesuai izin.
-   Semua otorisasi wajib diperiksa di server. Jika pemisahan data per
    bisnis diterapkan, setiap query dan perubahan harus dibatasi pada
    bisnis yang boleh diakses pengguna.

## 4. Modul dan kriteria penerimaan

### Autentikasi dan otorisasi

-   Login/logout berfungsi; route operasional dilindungi.
-   Izin diperiksa di server, bukan hanya dengan menyembunyikan tombol.
-   Pengguna tidak bisa mengakses data di luar cakupannya.

### Dashboard

-   Ringkasan penjualan, pembelian, stok, piutang, dan utang berasal
    dari data transaksi nyata, bukan angka dummy.
-   Definisi metrik dan rentang tanggal didokumentasikan.

### Produk

-   CRUD produk dengan SKU/kode, nama, satuan, harga jual, harga pokok
    default, status aktif.
-   SKU unik dalam cakupan yang ditentukan; harga dan kuantitas
    divalidasi.
-   Produk yang sudah dipakai transaksi tidak dihapus secara destruktif.

### Supplier dan pelanggan/reseller

-   CRUD nama, kontak/alamat opsional, dan status aktif.
-   Riwayat transaksi tetap bisa dibaca jika data master dinonaktifkan.

### Pembelian dan penerimaan

-   Pembelian memiliki nomor unik, supplier, tanggal, item, kuantitas,
    harga beli, subtotal, total, dan status.
-   Stok bertambah hanya saat penerimaan dikonfirmasi.
-   Header, item, dan stock movement disimpan secara atomik dalam
    database transaction.
-   Pembatalan transaksi yang memengaruhi stok harus membuat reversal
    yang tercatat.

### Persediaan

-   Stok per produk dapat dilihat.
-   Semua perubahan stok dicatat dalam `inventory_movements` dengan
    produk, kuantitas masuk/keluar, referensi transaksi, waktu, dan
    pengguna.
-   Cegah stok negatif dan lindungi operasi bersamaan menggunakan
    transaksi serta locking yang sesuai, misalnya `lockForUpdate()`.
-   Jangan mengubah angka stok tanpa movement yang bisa ditelusuri.

### Penjualan

-   Penjualan berisi pelanggan/reseller, nomor unik, tanggal, item,
    kuantitas, harga jual saat transaksi, harga pokok, subtotal, total,
    pembayaran, dan sisa piutang.
-   Harga transaksi disimpan sebagai snapshot agar perubahan harga
    produk tidak mengubah riwayat.
-   Stok berkurang atomik bersama penyimpanan transaksi; penjualan
    ditolak jika stok tidak cukup.
-   Hitung ulang total di server; jangan percaya total dari browser.

### Pembayaran, piutang, dan utang

-   Mendukung pembayaran penuh dan parsial.
-   Catat tanggal, jumlah, metode opsional, dan referensi transaksi.
-   Piutang = total penjualan kredit dikurangi pembayaran yang tercatat.
-   Utang supplier dihitung jika alur pembayaran pembelian termasuk
    cakupan implementasi.
-   Tolak pembayaran nol/negatif dan pembayaran melebihi saldo, kecuali
    aturan kelebihan bayar secara eksplisit disetujui.

### Laporan

-   Penjualan dan pembelian berdasarkan rentang tanggal.
-   Stok saat ini dan riwayat pergerakan stok.
-   Piutang pelanggan dan utang supplier jika didukung.
-   Total laporan harus cocok dengan transaksi sumber. Ekspor CSV
    opsional setelah laporan dasar benar.

### Audit trail

-   Catat pengguna, aksi, entitas, waktu, dan ringkasan perubahan
    penting.
-   Jangan mencatat password, token, atau rahasia.
-   Pembatalan transaksi dan perubahan pembayaran/stok harus dapat
    ditelusuri.

## 5. Model data baseline

Pertimbangkan tabel `users`, `businesses`/keanggotaan bila diperlukan,
`products`, `suppliers`, `customers`, `purchases`, `purchase_items`,
`sales`, `sale_items`, `payments`, `inventory_movements`, dan
`audit_logs`.

Gunakan foreign key, index, unique constraints, decimal untuk uang
(bukan float), snapshot harga transaksi, serta kebijakan penghapusan
yang tidak menghilangkan histori transaksi. Tinjau relasi sebelum
membuat seluruh tabel.

## 6. Aturan transaksi penting

1.  Simpan transaksi, item, stock movement, dan saldo terkait secara
    konsisten dalam database transaction.
2.  Cegah stok negatif dan race condition.
3.  Gunakan decimal dan aturan pembulatan yang eksplisit untuk uang.
4.  Jangan menghapus transaksi yang memengaruhi stok/saldo; gunakan
    pembatalan/reversal tercatat.
5.  Validasi input dan otorisasi di server.
6.  Jangan menandai fitur selesai sebelum pengujian relevan dijalankan.

## 7. Acceptance test referensi

Skenario:

-   Stok awal: 100 unit.
-   Pembelian diterima: +20 unit.
-   Penjualan: 15 unit.
-   Harga jual: Rp85.000/unit.
-   Harga pokok contoh: Rp60.000/unit.
-   Pembayaran pelanggan: Rp1.000.000.

Hasil yang diharapkan:

-   Stok akhir = 100 + 20 - 15 = **105 unit**.
-   Nilai penjualan = 15 × Rp85.000 = **Rp1.275.000**.
-   Sisa piutang = Rp1.275.000 - Rp1.000.000 = **Rp275.000**.
-   HPP contoh = 15 × Rp60.000 = **Rp900.000**.
-   Margin kotor contoh sebelum biaya lain = **Rp375.000**.

Tes tambahan: penjualan melebihi stok ditolak tanpa perubahan parsial;
kegagalan simpan me-rollback seluruh transaksi; transaksi bersamaan
tidak membuat stok negatif; pembayaran berlebih ditolak sesuai aturan;
pembatalan menghasilkan reversal benar; akses lintas bisnis ditolak.

## 8. Definisi selesai MVP

-   Alur master data → pembelian → stok → penjualan → pembayaran/piutang
    berjalan.
-   Acceptance test Bagian 7 lulus.
-   Tes otorisasi, validasi, stok tidak negatif, dan konsistensi
    transaksi lulus.
-   Dashboard/laporan memakai data nyata.
-   Tidak ada rahasia dalam Git; `.env.example` tersedia tanpa rahasia.
-   Instruksi instalasi lokal dan fitur yang belum selesai
    terdokumentasi.
