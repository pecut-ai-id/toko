# Keputusan Teknis --- TokoFlow

Dokumen ini merekam keputusan teknis yang mengikat implementasi TokoFlow
beserta alasannya, agar pilihan yang sudah diambil tidak ditinjau ulang
tanpa konteks. Penomoran keputusan tidak seluruhnya berurutan: beberapa
keputusan bersifat internal/operasional dan tidak disertakan di sini.

## D-001 --- Local-first development

-   Status: dipilih.
-   Kembangkan dan uji lokal terlebih dahulu; deploy ke VPS setelah MVP
    diverifikasi.
-   Alasan: mengurangi risiko terhadap situs yang sudah berjalan di VPS.

## D-002 --- Integritas transaksi

-   Status: dipilih.
-   Stok, penjualan, pembelian, dan pembayaran harus konsisten serta
    dapat ditelusuri.

## D-004 --- MySQL 8/InnoDB sebagai database development

-   Status: dipilih.
-   Database lokal `tokoflow` (development, port 3306, hanya bind
    `127.0.0.1`) dan `tokoflow_test` (pengujian).
-   Charset `utf8mb4`, collation `utf8mb4_unicode_ci`, engine InnoDB.
-   Alasan: `requirements.md` menargetkan MySQL 8/InnoDB; perilaku transaksi dan
    locking lebih setia diuji di MySQL daripada SQLite.
-   Konsekuensi: `.env` lokal memakai koneksi mysql; `.env.example`
    mencerminkan MySQL dengan placeholder tanpa rahasia.

## D-005 --- Multi-bisnis sejak MVP

-   Status: dipilih.
-   Aplikasi mendukung banyak bisnis: entitas `businesses` beserta
    keanggotaan pengguna, bukan single-tenant.
-   Semua query dan perubahan dibatasi pada bisnis yang boleh diakses
    pengguna; otorisasi diperiksa di server, bukan hanya menyembunyikan
    tombol.
-   Alasan: kebutuhan distributor/reseller yang mengelola lebih dari satu
    entitas bisnis.
-   Catatan: tabel dan middleware belum dibuat. Keputusan ini mengikat
    desain autentikasi/otorisasi dan tahap berikutnya.

## D-006 --- Database pengujian terpisah di MySQL

-   Status: dipilih.
-   Suite PHPUnit berjalan di database `tokoflow_test` (MySQL), bukan
    SQLite `:memory:`.
-   Alasan: pengujian stok negatif, rollback transaksi, dan konkurensi
    harus mencerminkan MySQL/InnoDB.
-   Konsekuensi: test yang menyentuh database memakai trait
    `RefreshDatabase`; waktu eksekusi test lebih lambat.

## D-008 --- Autentikasi ringan tanpa starter kit

-   Status: dipilih.
-   Login/logout dibuat manual (`LoginController` + `LoginRequest`)
    memakai guard `web` bawaan Laravel, tanpa memasang Laravel Breeze,
    Jetstream, atau Fortify.
-   Alasan: `requirements.md` meminta menghindari dependency besar; kebutuhan
    autentikasi MVP hanya login/logout dengan pembatasan percobaan.
-   Konsekuensi: tidak ada fitur registrasi mandiri, verifikasi email,
    atau reset password pada tahap ini. Pembatasan 5 percobaan per
    email + IP memakai `RateLimiter`.

## D-009 --- Peran disimpan di pivot, konteks bisnis per request

-   Status: dipilih.
-   Peran (`owner`/`admin`/`staff`) disimpan pada pivot
    `business_user.role`, bukan pada tabel `users`, agar satu pengguna
    dapat memiliki peran berbeda di setiap bisnis.
-   Bisnis aktif ditentukan per request oleh middleware
    `ResolveCurrentBusiness` (urutan: session -> keanggotaan aktif
    pertama) dan diverifikasi ulang keanggotaannya di server.
-   Konteks dibagikan lewat singleton `App\Support\CurrentBusiness`.
    Query domain pada tahap berikutnya **wajib** dibatasi dengan
    `CurrentBusiness::id()` atau relasi `belongsToMany` pengguna.
-   Alasan: konsisten dengan D-005 (multi-bisnis) dan kebutuhan
    "pengguna tidak bisa mengakses data di luar cakupannya".

## D-010 --- Pengelolaan data master hanya untuk owner/admin

-   Status: dipilih.
-   Melihat data master: semua anggota aktif bisnis (owner, admin,
    staff). Menambah/mengubah/menonaktifkan: hanya owner dan admin.
-   Implementasi: `App\Policies\BusinessScopedPolicy` dipakai bersama
    oleh `ProductPolicy`, `SupplierPolicy`, dan `CustomerPolicy`.
-   Alasan: `requirements.md` Bagian 3 --- "Owner/Admin: mengelola data master",
    "Staff: melakukan operasional sesuai izin".
-   Konsekuensi: peran pada pivot benar-benar menentukan izin di server;
    tombol di UI hanya mengikuti `@can`, bukan pengamanannya.

## D-011 --- Penghapusan data master bersifat non-destruktif

-   Status: dipilih.
-   Aksi hapus pada produk, supplier, dan pelanggan tidak menghapus baris
    dari database; statusnya diubah menjadi `is_active = false`.
-   Alasan: `requirements.md` Bagian 4 --- "Produk yang sudah dipakai transaksi
    tidak dihapus secara destruktif" dan "Riwayat transaksi tetap bisa
    dibaca jika data master dinonaktifkan".
-   Konsekuensi: tidak memakai `SoftDeletes`; status `is_active` adalah
    sumber kebenaran. Reaktivasi dilakukan lewat form ubah.

## D-012 --- Format angka dan uang mengikuti konvensi Indonesia

-   Status: dipilih.
-   Input uang menerima format Indonesia: titik sebagai pemisah ribuan,
    koma sebagai pemisah desimal (`1.275.000,50`, `85.000`, `85,5`).
-   Konversi terpusat di `App\Support\Money`:
    -   `parseToDecimal()` mengubah input pengguna menjadi decimal string
        sebelum validasi dan sebelum disimpan.
    -   `format()`/`rupiah()` mengubahnya kembali untuk tampilan; helper
        global `rupiah()` dan `angka()` tersedia lewat `composer.json`
        bagian `autoload.files`.
-   Aturan titik tunggal yang ambigu: satu titik dengan tepat 3 digit di
    belakangnya dibaca sebagai pemisah ribuan (`85.000` -> 85000), selain
    itu dibaca sebagai desimal (`85.5` -> 85,50).
-   Alasan: `requirements.md` Bagian 6 poin 3 mewajibkan decimal dan pembulatan
    eksplisit; pengguna toko mengetik dengan format Indonesia.
-   Konsekuensi: `Money::multiply()` dan `Money::sum()` menerima decimal
    string yang sudah dinormalkan, bukan input mentah pengguna. Nilai
    dinormalkan lebih dulu di `PurchaseService` supaya subtotal dan nilai
    tersimpan selalu dihitung dari angka yang sama.

## D-013 --- Stok: kolom agregat plus buku besar sebagai sumber jejak

-   Status: dipilih.
-   `products.stock` menyimpan saldo berjalan agar pembacaan cepat, dan
    `inventory_movements` mencatat setiap perubahan (kuantitas bertanda,
    `balance_after`, alasan, referensi transaksi, pengguna, waktu).
-   Kolom `stock` sengaja **tidak** masuk `$fillable` dan hanya boleh
    berubah lewat `App\Services\InventoryService::record()`, yang selalu
    menulis satu baris `inventory_movements` pada transaksi database yang
    sama.
-   Alasan: `requirements.md` Bagian 4 --- "Jangan mengubah angka stok tanpa
    movement yang bisa ditelusuri" dan "Stok per produk dapat dilihat".
-   Konsekuensi: ada dua sumber kebenaran yang harus konsisten; konsistensi
    dijaga oleh service tunggal dan diuji dengan membandingkan
    `products.stock` terhadap `SUM(inventory_movements.quantity)`.

## D-014 --- Referensi mutasi stok menunjuk dokumen transaksi

-   Status: dipilih.
-   `inventory_movements` memakai relasi polimorfik `source_type` /
    `source_id` yang menunjuk **dokumen transaksi** (saat ini
    `App\Models\Purchase`; nanti `Sale`), bukan baris item.
-   Alasan: `requirements.md` Bagian 4 meminta referensi ke transaksi sumber, dan
    dokumen adalah satuan yang dibatalkan/direkonsiliasi.
-   Konsekuensi: beberapa mutasi dari satu dokumen berbagi `source_id`
    yang sama; rincian per produk tetap ada di kolom `product_id`,
    `quantity`, dan `reason`.

## D-015 --- Pembatalan transaksi memakai reversal, bukan penghapusan

-   Status: dipilih.
-   Membatalkan pembelian berstatus `draft` tidak menyentuh stok.
    Membatalkan pembelian berstatus `received` membuat mutasi reversal
    (kuantitas negatif) yang tercatat, lalu statusnya menjadi `cancelled`.
-   Pembatalan **ditolak** bila reversal akan membuat stok negatif (mis.
    barangnya sudah terjual), dengan pesan yang bisa dibaca pengguna dan
    seluruh transaksi database di-rollback.
-   Alasan: `requirements.md` Bagian 4 --- "Pembatalan transaksi yang memengaruhi
    stok harus membuat reversal yang tercatat"; stok negatif dilarang
    (Bagian 6 poin 2).
-   Konsekuensi: riwayat penerimaan dan pembatalan keduanya terlihat di
    buku besar; tidak ada baris yang dihapus.

## D-016 --- Penomoran dokumen pembelian otomatis per bisnis per hari

-   Status: dipilih.
-   Nomor berbentuk `PB-YYYYMMDD-####`, unik per bisnis
    (`unique(business_id, number)`), dibuat di server.
-   Pembuatan nomor terjadi di dalam transaksi yang sama dengan
    penyimpanan dokumen, setelah `lockForUpdate()` pada baris bisnis,
    sehingga dua permintaan bersamaan tidak menghasilkan nomor kembar.
-   Alasan: `requirements.md` Bagian 4 --- "Pembelian memiliki nomor unik".

## D-017 --- Izin pembelian: staff operasional, owner/admin pembatalan

-   Status: dipilih.
-   Melihat, membuat draf, dan mengonfirmasi penerimaan: semua anggota
    aktif bisnis (termasuk staff). Membatalkan: hanya owner/admin.
-   Implementasi: `App\Policies\PurchasePolicy` menurunkan
    `BusinessScopedPolicy` dan menimpa `create`, `receive`, dan `cancel`.
-   Alasan: `requirements.md` Bagian 3 --- "Staff: melakukan operasional sesuai
    izin"; pembatalan mengembalikan stok dan bersifat korektif, jadi
    disamakan dengan pengelolaan data master (D-010).

## D-018 --- Penjualan langsung mengurangi stok dan wajib punya pelanggan

-   Status: dipilih.
-   Penjualan tidak punya fase draf: saat disimpan, statusnya langsung
    `completed` dan stok langsung berkurang. Status lain hanya
    `cancelled`.
-   Setiap penjualan wajib menunjuk satu pelanggan aktif pada bisnis yang
    sama.
-   Alasan: `requirements.md` Bagian 4 --- "Stok berkurang atomik bersama
    penyimpanan transaksi", berbeda dari pembelian yang harus dikonfirmasi
    penerimaannya (Bagian 5). Penjualan pada aplikasi distributor/reseller
    selalu punya pembeli, jadi pelanggan kosong dianggap tidak valid.
-   Konsekuensi: tidak ada jalur "simpan dulu, kurangi stok nanti" untuk
    penjualan; koreksi dilakukan lewat pembatalan (D-015).

## D-019 --- Snapshot harga jual dan harga pokok; HPP dari snapshot biaya

-   Status: dipilih.
-   `sale_items.unit_price` mencatat harga jual saat transaksi (boleh
    disesuaikan dari harga produk) dan `sale_items.unit_cost` mencatat
    harga pokok saat transaksi.
-   `unit_cost` **selalu** diambil di server dari `products.cost_price`;
    nilai `unit_cost` yang dikirim browser diabaikan sepenuhnya.
-   HPP dokumen = jumlah `quantity x unit_cost` per baris; margin kotor =
    total penjualan - HPP.
-   Alasan: `requirements.md` Bagian 4 --- "Harga transaksi disimpan sebagai
    snapshot agar perubahan harga produk tidak mengubah riwayat";
    contoh HPP di Bagian 7 memakai harga pokok per unit (15 x Rp60.000),
    bukan lapisan persediaan.
-   Konsekuensi: TokoFlow **belum** memakai FIFO atau rata-rata bergerak.
    Bila metode persediaan berubah nanti, HPP historis tidak berubah
    karena sudah tersimpan sebagai snapshot. Menerima pembelian juga belum
    memperbarui `products.cost_price` (kemudian diubah oleh D-027).

## D-020 --- Penomoran dokumen penjualan otomatis per bisnis per hari

-   Status: dipilih.
-   Nomor berbentuk `PJ-YYYYMMDD-####`, unik per bisnis
    (`unique(business_id, number)`), dibuat di server.
-   Dibuat di dalam transaksi yang sama dengan penyimpanan dokumen,
    setelah `lockForUpdate()` pada baris bisnis, sehingga dua permintaan
    bersamaan tidak menghasilkan nomor kembar. Sama seperti D-016.

## D-021 --- Satu tabel `payments` polimorfik untuk saldo dokumen

-   Status: dipilih.
-   Pembayaran disimpan di tabel `payments` dengan relasi polimorfik
    `payable` (`payable_type` + `payable_id`), bukan kolom
    `paid_amount` di tabel penjualan.
-   `business_id` dan `payable` diisi dari dokumen sumber, bukan dari
    input pengguna (`business_id` sengaja tidak fillable).
-   Sisa saldo selalu **dihitung** dari total dikurangi jumlah pembayaran,
    tidak ada saldo yang disimpan ganda.
-   Alasan: `requirements.md` Bagian 5 hanya mencantumkan satu tabel `payments`,
    sementara Bagian 4 memisahkan piutang pelanggan dan utang supplier.
    Relasi polimorfik membuat tabel yang sama melayani keduanya tanpa
    migrasi tambahan; perhitungan (bukan penyimpanan) saldo menghilangkan
    risiko nilai basi.

## D-022 --- Kelebihan bayar ditolak dan saldo diperiksa ulang terkunci

-   Status: dipilih.
-   Pembayaran nol atau negatif ditolak; pembayaran melebihi sisa saldo
    ditolak.
-   Sebelum menilai, service mengunci baris dokumen (`lockForUpdate()`)
    lalu membaca ulang total pembayaran dengan pembacaan terkunci,
    sehingga dua pembayaran bersamaan tidak bisa melampaui total.
-   Alasan: `requirements.md` Bagian 4 --- "Tolak pembayaran nol/negatif dan
    pembayaran melebihi saldo, kecuali aturan kelebihan bayar secara
    eksplisit disetujui". TokoFlow **belum** menyetujui aturan kelebihan
    bayar, jadi jalur itu ditutup.
-   Konsekuensi: nilai kembalian/uang muka belum didukung. Bila nanti
    disetujui, aturannya harus menggantikan butir ini.

## D-023 --- Penjualan yang sudah menerima pembayaran tidak dapat dibatalkan

-   Status: **direvisi oleh D-026**. Aturan dasarnya
    tetap berlaku; yang bertambah adalah jalan keluar resminya.
-   Pembatalan penjualan ditolak bila masih ada baris `payments` yang
    **berlaku** (belum di-void) untuk dokumen tersebut, dan tombol
    "Batalkan penjualan" tidak ditampilkan untuk kondisi itu.
-   Alasan: pembatalan penjualan mengembalikan stok, dan pengembalian dana
    (refund) belum ada di cakupan TokoFlow. Membiarkan pembatalan hanya
    akan membuat buku besar berbohong: stok kembali sementara uang tetap
    tercatat diterima.
-   Konsekuensi: koreksi penjualan yang sudah dibayar dilakukan dengan
    **membatalkan (void) pembayarannya lebih dulu** (D-026), bukan dengan
    menghapus jejak. Voiding tetap tercatat, jadi buku besar tidak
    berbohong; hanya saja urutannya kini punya langkah keluar.

## D-024 --- Izin penjualan: staff operasional, owner/admin pembatalan

-   Status: dipilih.
-   Melihat dan membuat penjualan, serta mencatat pembayaran piutang:
    semua anggota aktif bisnis (termasuk staff). Membatalkan penjualan:
    hanya owner/admin. Sama seperti D-017 untuk pembelian.
-   Implementasi: `App\Policies\SalePolicy` menurunkan
    `BusinessScopedPolicy` dan menimpa `create`, `recordPayment`, dan
    `cancel`. Tidak ada policy terpisah untuk `Payment` karena pembayaran
    hanya dibuat lewat route bersarang di bawah penjualan.

## D-025 --- Dokumen yang dibatalkan tidak punya sisa piutang

-   Status: dipilih.
-   `dueAmount()` mengembalikan nol untuk dokumen yang sudah dibatalkan,
    meskipun totalnya tidak nol.
-   Alasan: ditemukan saat verifikasi HTTP --- penjualan yang
    dibatalkan masih menampilkan "sisa piutang" sebesar totalnya di daftar
    transaksi, padahal tagihannya sudah gugur. Perhitungan piutang
    pelanggan di `customers.show` memang sudah menyaring status, tetapi
    kolom per baris masih menyesatkan.
-   Konsekuensi: riwayat tetap utuh (total asli masih tersimpan dan
    terlihat di halaman detail), hanya angka sisa yang tidak lagi
    ditampilkan untuk dokumen yang sudah batal.

## D-026 --- Pembatalan pembayaran (void) sebagai koreksi, bukan penghapusan

-   Status: dipilih. Revisi atas D-023.
-   Tabel `payments` bertambah `voided_at`, `voided_by`, dan
    `void_reason`. Barisnya **tidak dihapus**: satu baris tetap ada
    sebagai jejak, hanya berhenti dihitung sebagai pelunasan.
-   `paidAmount()` dan `dueAmount()` hanya memakai pembayaran dengan
    `voided_at IS NULL`. Hal yang sama berlaku pada agregat piutang/utang
    di `customers.show` dan `suppliers.show` (`withSum` dengan
    `whereNull('voided_at')`) serta pada pembacaan terkunci di
    `PaymentService::record()`.
-   Void hanya boleh atas dokumen yang masih terbuka; dokumen yang sudah
    dibatalkan tidak perlu di-void lagi (ditolak dengan pesan jelas).
-   Otorisasi: hanya owner/admin (`PaymentPolicy::void`), dan alasan wajib
    diisi (minimal 3 karakter). Mencatat pembayaran tetap boleh staff
    (D-024).
-   Pembatalan dokumen memakai `hasActivePayments()`: dokumen yang masih
    punya pembayaran **berlaku** ditolak; dokumen yang seluruh
    pembayarannya sudah di-void boleh dibatalkan.
-   Alasan: merevisi D-023 tanpa melanggar D-015 (reversal tercatat, bukan
    penghapusan). Voiding adalah "reversal pencatatan pembayaran".
-   Konsekuensi: pengembalian uang secara fisik tetap di luar sistem ---
    TokoFlow mencatat koreksinya, penyerahan uangnya dilakukan di luar
    aplikasi. Setelah void, dokumen bisa dibatalkan dan stoknya kembali
    lewat pergerakan reversal seperti biasa.

## D-027 --- Harga pokok default produk mengikuti pembelian terakhir yang berlaku

-   Status: dipilih. Kandidat ditinjau ulang bila nanti
    perlu rata-rata tertimbang.
-   Saat penerimaan pembelian dikonfirmasi, `products.cost_price` disetel
    ke `unit_cost` item pembelian itu. Bukan rata-rata tertimbang, bukan
    FIFO.
-   Saat pembelian yang sudah diterima dibatalkan, harga pokok
    diselaraskan ulang ke pembelian terakhir yang masih berstatus
    `received`. Bila tidak ada lagi, nilai terakhir **dibiarkan apa
    adanya** --- lebih baik daripada menebak nol.
-   Hanya kolom `cost_price` yang disentuh, lewat query builder, supaya
    nilai `stock` hasil penerimaan tidak tertimpa model yang sudah basi.
-   Penjualan yang sudah terjadi tidak terpengaruh karena
    `sale_items.unit_cost` sudah berupa snapshot (D-019); harga pokok baru
    hanya memengaruhi penjualan berikutnya.
-   Alasan: `requirements.md` Bagian 4 menyebut harga pokok sebagai nilai default
    produk. Tanpa pembaruan, harga pokok harus diubah manual setiap kali
    harga beli berubah dan cepat basi, sehingga HPP penjualan berikutnya
    salah.
-   Konsekuensi: satu pembelian promosi bisa menurunkan harga pokok default
    sampai pembelian berikutnya. Harga pokok tetap bisa diubah manual di
    form produk.
-   **Tinjauan ulang: tetap dipakai, belum
    diubah ke rata-rata tertimbang.** Alasan: rata-rata tertimbang menuntut
    keputusan yang belum ada di `requirements.md` (perhitungan per gudang atau per
    bisnis, perlakuan pembatalan pembelian, dan cara membagi biaya dengan
    stok awal hasil penyesuaian manual). HPP penjualan sudah aman dari
    fluktuasi harga beli karena memakai snapshot `sale_items.unit_cost`
    (D-019), jadi yang terpengaruh hanya harga pokok default untuk penjualan
    **berikutnya** --- memang itu tujuan pembaruannya. Menundanya berbiaya
    rendah; mengubahnya tanpa aturan yang jelas justru berbahaya.

## D-028 --- Penyesuaian stok manual oleh owner/admin, alasan wajib

-   Status: dipilih.
-   Form penyesuaian stok ada di `products.show`: arah (masuk/keluar),
    jumlah, alasan wajib (minimal 3 karakter), dan tanggal opsional.
-   Otorisasi: hanya owner/admin (`ProductPolicy::adjustStock`). Staff
    tetap boleh mengubah stok lewat jalur operasional yang dokumennya
    tercatat (penerimaan pembelian dan penjualan).
-   Tetap lewat `InventoryService`, sehingga invariant stok tetap
    terjaga: satu baris `inventory_movements` per perubahan, produk
    dikunci, dan stok tidak boleh negatif.
-   Alasan: menutup celah yang sebelumnya terbuka --- stok awal dan
    koreksi hitung sebelumnya hanya bisa dilakukan lewat tinker, padahal
    `requirements.md` Bagian 7 mengasumsikan stok awal bisa diisi sebelum skenario
    penerimaan dijalankan.
-   Konsekuensi: penyesuaian tidak menempel pada dokumen transaksi
    (`source` null), jadi jejaknya hanya `reason`, pengguna, dan waktu.
    Karena itu alasannya wajib.

## D-029 --- Definisi metrik dashboard dan laporan

-   Status: dipilih.
-   Satu service, `App\Services\ReportingService`, memegang semua definisi
    angka ringkasan. Dashboard dan halaman laporan memanggil service yang
    sama, sehingga angka "penjualan bulan ini" tidak mungkin berbeda antara
    dua halaman.
-   **Rentang tanggal inklusif**: `from` dan `to` ikut dihitung, satu
    tanggal berarti satu hari penuh. Bila tidak diisi, rentangnya adalah
    **awal bulan berjalan sampai hari ini**, bukan 30 hari terakhir.
-   Definisi tiap metrik:
    -   Penjualan = `SUM(sales.total)` untuk status `completed` pada
        `sold_at` di rentang. Penjualan yang dibatalkan tidak dihitung
        karena stoknya sudah dikembalikan lewat reversal (D-015).
    -   Pembelian = `SUM(purchases.total)` untuk status `received` pada
        `purchased_at` di rentang. Draf belum mengubah stok, dan pembelian
        yang dibatalkan sudah dibalik.
    -   HPP = `SUM(sale_items.quantity * sale_items.unit_cost)`, memakai
        snapshot biaya saat penjualan dibuat (D-019) --- bukan harga pokok
        produk saat ini.
    -   Margin kotor = Penjualan - HPP. Bukan laba bersih; biaya
        operasional belum ada di cakupan TokoFlow.
    -   Piutang/utang = saldo **berjalan** seluruh dokumen
        (`total - pembayaran berlaku`), tidak dibatasi rentang tanggal,
        dan pembayaran yang sudah di-void tidak dihitung (D-026).
    -   Nilai persediaan = `SUM(products.stock * products.cost_price)`
        untuk seluruh produk bisnis, **termasuk yang nonaktif**, karena
        barangnya masih ada di gudang.
    -   Stok menipis = produk **aktif** dengan `stock <= 5`. Ambang 5
        bukan angka dari `requirements.md`; dipilih sebagai default warung dan
        ditampilkan di UI supaya bukan angka rahasia
        (`ReportingService::LOW_STOCK_THRESHOLD`).
-   Definisi yang sama diulang di halaman dashboard (blok "Definisi
    metrik") supaya pemilik toko bisa memeriksa sendiri artinya.
-   Alasan: `requirements.md` Bagian 4 mensyaratkan ringkasan "berasal dari data
    transaksi nyata" dan "definisi metrik dan rentang tanggal
    didokumentasikan". Menaruh definisi di satu tempat adalah cara paling
    murah untuk menjaga janji itu.
-   Konsekuensi: angka laporan selalu dihitung ulang dari tabel transaksi,
    jadi tidak ada tabel ringkasan yang bisa basi --- dengan konsekuensi
    laporan akan melambat bila data sudah sangat besar (belum jadi masalah
    untuk skala MVP).

## D-030 --- Ekspor CSV laporan dan perlakuan input tanggal yang salah

-   Status: dipilih.
-   Setiap halaman laporan menerima `format=csv` pada query string dan
    mengembalikan **satu tabel** --- tabel rincian utama halaman itu
    (produk untuk penjualan, supplier untuk pembelian, daftar produk untuk
    stok, pelanggan untuk piutang, supplier untuk utang).
-   Format: pemisah kolom `;`, BOM UTF-8, angka sebagai decimal polos
    (`1275000.00`) bukan `Rp1.275.000`. Alasannya: pemisah `;` + BOM
    adalah kombinasi yang terbaca benar di Excel locale Indonesia, dan
    angka polos bisa langsung dihitung ulang di spreadsheet
    (`App\Support\Csv`).
-   Input tanggal yang tidak bisa dibaca (`18-10-2026`, `2026-10-45`)
    ditolak dengan pesan, bukan digeser diam-diam. `2026-10-45` ditolak
    eksplisit karena `CarbonImmutable::createFromFormat()` akan
    menggelindingkannya ke November tanpa peringatan.
-   Bila validasi gagal, pengguna dikembalikan ke halaman laporan itu
    sendiri **tanpa query string**, bukan lewat `back()` bawaan Laravel.
    `back()` bergantung pada Referer/sesi, sehingga URL bermasalah yang
    dibuka langsung dari bookmark bisa melempar pengguna ke halaman lain
    dan pesan kesalahannya tidak pernah terlihat
    (`ReportRequest::failedValidation`).
-   Alasan: `requirements.md` Bagian 4 menyebut ekspor CSV "opsional setelah
    laporan dasar benar". Karena laporan dasarnya sudah punya test yang
    membandingkan dengan transaksi sumber, ekspor ditambahkan sekalian
    dengan test yang membaca ulang CSV-nya.
-   Konsekuensi: rentang tanggal tidak berlaku pada laporan
    piutang/utang (keduanya saldo berjalan); ini dinyatakan langsung di
    halaman laporan supaya tidak menyesatkan.

## D-031 --- Jejak audit: ditulis satu pintu, dibaca semua anggota, tidak pernah diubah

-   Status: dipilih.
-   Tabel `audit_logs` menyimpan **ringkasan kejadian**, bukan salinan
    baris tabel lain: pelaku (`user_id`), bisnis (`business_id`), jenis
    kejadian (`action`), dokumen/data yang bersangkutan
    (`subject_type`/`subject_id`), keterangan singkat (`summary`), dan
    ringkasan perubahan penting (`changes`).
-   Barisnya **tidak pernah diubah atau dihapus**: tidak ada `updated_at`,
    dan tidak ada route tulis (`create`/`update`/`delete` selalu ditolak
    `AuditLogPolicy`). Yang salah dikoreksi dengan aksi baru yang ikut
    tercatat, bukan dengan menyunting riwayat.
-   Penulisan hanya lewat satu kelas, `App\Services\AuditLogger`. Alasan:
    bentuk catatan harus seragam dan bisa diperiksa di satu tempat;
    menulis langsung dari banyak controller akan cepat berbeda-beda.
-   Yang dicatat: pembelian (dibuat/diterima/dibatalkan), penjualan
    (dibuat/dibatalkan), pembayaran (dicatat/di-void), penyesuaian stok,
    dan perubahan data master (produk/supplier/pelanggan: dibuat, diubah,
    dinonaktifkan). Mutasi stok per barang **tidak** dicatat ulang di sini
    karena sudah punya ledger sendiri (`inventory_movements`).
-   `changes` hanya memuat kolom yang **berubah** dan layak dibaca
    (`stock`, `status`, `sell_price`, `is_active`, ...). Password/token
    tidak pernah masuk. Pencocokan nilai memakai cast model sebagai acuan,
    supaya `1` dari database tidak dianggap berbeda dari `true` di model.
-   Pelaku diambil dari pengguna yang sedang login. Operasi yang berjalan
    **tanpa pengguna** (seeder, command, sebagian test) tidak menghasilkan
    baris audit --- baris audit yang tidak menyebut siapa pelakunya tidak
    berguna untuk diaudit.
-   Penulisan terjadi **di dalam transaction yang sama** dengan
    operasinya. Jadi operasi yang gagal (mis. kelebihan bayar yang ditolak)
    tidak meninggalkan baris audit, dan sebaliknya jejak audit tidak
    pernah bolong demi menyelamatkan transaksi.
-   Hak baca: seluruh anggota aktif bisnis, termasuk staff
    (`AuditLogPolicy extends BusinessScopedPolicy`, hanya dibatasi
    `business_id`). Alasannya: isinya adalah tindakan atas data yang
    memang sudah boleh dilihat anggota tersebut, dan menutupnya untuk
    staff membuat staff tidak bisa memeriksa kenapa stok atau saldo
    berubah.
-   Halaman `/audit-logs` menyaring per rentang tanggal (aturan sama
    dengan laporan, D-029), jenis aktivitas, dan pelaku; urutannya terbaru
    dulu; 50 baris per halaman. Tidak ada ekspor CSV untuk tahap ini.
-   Konsekuensi: jejak audit hanya terisi untuk aktivitas setelah tabel ini
    dibuat. Transaksi lama tidak punya riwayat, dan itu memang tidak
    dikarang-karang: halaman riwayat kosong sampai ada aktivitas baru.

## D-032 --- Penyaring "belum lunas"/"sudah lunas" mengikuti definisi sisa yang sama

-   Status: dipilih.
-   Daftar penjualan dan pembelian punya penyaring kedua: `pembayaran=belum`
    dan `pembayaran=sudah`. Nilai lain diabaikan (dianggap "semua").
-   Rumusnya sama dengan `dueAmount()` (D-021, D-025):
    `total` dibandingkan dengan jumlah pembayaran yang **belum di-void**
    untuk dokumen itu. Karena sisa tidak disimpan sebagai kolom, penyaring
    memakai subquery terhadap `payments`.
-   Hanya dokumen yang **terbuka** yang ikut: penjualan `completed` dan
    pembelian `received`. Draf pembelian tidak pernah "belum lunas" karena
    utangnya baru ada setelah penerimaan (D-025), dan dokumen yang
    dibatalkan tidak muncul sama sekali --- kalau tidak, kolom "Sisa" di
    baris itu akan menampilkan `Rp0` di daftar "belum lunas".
-   Supaya "terbuka" tidak ditulis dua kali (sekali di `isOpen()` dan
    sekali di SQL), model penjualan/pembelian menyediakan
    `openStatus()`, dan `isOpen()` kini dibaca dari sana. Trait
    `HasPayments` mendeklarasikannya sebagai abstract, sehingga penambah
    dokumen baru tidak bisa lupa.
-   Alasan: ini kebutuhan nyata pemilik toko ("siapa yang belum bayar"),
    tapi harus memakai definisi sisa yang sudah ada --- bukan definisi
    kedua yang bisa berbeda dari laporan piutang/utang.
-   Konsekuensi: penyaring ini memakai subquery per baris; pada data
    besar perlu index pada `payments(payable_type, payable_id)` (sudah ada
    lewat index polimorfik bawaan `nullableMorphs`/relasi) dan tetap
    dihitung ulang tiap request --- sejalan dengan D-029.
