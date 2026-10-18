<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\BusinessSwitchController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Halaman awal
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
})->name('home');

/*
|--------------------------------------------------------------------------
| Autentikasi (tamu)
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store']);
});

/*
|--------------------------------------------------------------------------
| Autentikasi (pengguna masuk)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    // Berpindah bisnis aktif; otorisasi diperiksa BusinessPolicy.
    Route::post('businesses/{business}/switch', BusinessSwitchController::class)
        ->name('businesses.switch');
});

/*
|--------------------------------------------------------------------------
| Route operasional (butuh pengguna masuk + konteks bisnis aktif)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'resolve.business', 'ensure.business'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('products', ProductController::class);
    Route::resource('suppliers', SupplierController::class);
    Route::resource('customers', CustomerController::class);

    // Pembelian: draf dibuat, penerimaan dikonfirmasi, pembatalan dicatat,
    // dan utang ke supplier dibayar.
    Route::resource('purchases', PurchaseController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('purchases/{purchase}/receive', [PurchaseController::class, 'receive'])->name('purchases.receive');
    Route::post('purchases/{purchase}/cancel', [PurchaseController::class, 'cancel'])->name('purchases.cancel');
    Route::post('purchases/{purchase}/payments', [PaymentController::class, 'storeForPurchase'])->name('purchases.payments.store');

    // Penjualan: stok berkurang saat dibuat; pembayaran piutang menyusul.
    Route::resource('sales', SaleController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('sales/{sale}/cancel', [SaleController::class, 'cancel'])->name('sales.cancel');
    Route::post('sales/{sale}/payments', [PaymentController::class, 'storeForSale'])->name('sales.payments.store');

    // Pembatalan (void) satu pembayaran, baik atas penjualan maupun pembelian.
    Route::post('payments/{payment}/void', [PaymentController::class, 'void'])->name('payments.void');

    // Penyesuaian stok manual (stok awal/koreksi) — hanya owner/admin.
    Route::post('products/{product}/stock-adjustments', [ProductController::class, 'adjustStock'])
        ->name('products.stock-adjustments.store');

    // Riwayat aktivitas (jejak audit) --- baca-saja untuk seluruh anggota
    // bisnis aktif (D-031).
    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');

    // Laporan berbasis data transaksi nyata. Setiap halaman menerima
    // `from`/`to` (inklusif) dan bisa diekspor dengan `format=csv`.
    Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
    Route::get('reports/purchases', [ReportController::class, 'purchases'])->name('reports.purchases');
    Route::get('reports/stock', [ReportController::class, 'stock'])->name('reports.stock');
    Route::get('reports/receivables', [ReportController::class, 'receivables'])->name('reports.receivables');
    Route::get('reports/payables', [ReportController::class, 'payables'])->name('reports.payables');
});
