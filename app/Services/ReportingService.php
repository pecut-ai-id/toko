<?php

namespace App\Services;

use App\Enums\PurchaseStatus;
use App\Enums\SaleStatus;
use App\Models\Business;
use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Support\Money;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Satu-satunya tempat definisi angka ringkasan dan laporan.
 *
 * Dashboard dan halaman laporan memanggil service yang sama supaya
 * definisi metrik tidak bisa menyimpang: kalau angka "penjualan bulan
 * ini" di dashboard dan di laporan berbeda, salah satunya tidak bisa
 * dipercaya (requirements.md Bagian 4 "Dashboard" dan "Laporan").
 *
 * Definisi metrik (docs/DECISIONS.md D-029):
 * - **Penjualan** = `SUM(sales.total)` untuk penjualan berstatus
 *   `completed` pada rentang `sold_at`. Penjualan yang dibatalkan tidak
 *   dihitung karena stoknya sudah dikembalikan lewat reversal.
 * - **Pembelian** = `SUM(purchases.total)` untuk pembelian berstatus
 *   `received` pada rentang `purchased_at`. Draf belum mengubah stok dan
 *   pembelian yang dibatalkan sudah dibalik, jadi keduanya tidak dihitung.
 * - **HPP** = `SUM(sale_items.quantity * sale_items.unit_cost)` dari
 *   snapshot biaya saat penjualan dibuat (D-019), bukan harga pokok
 *   produk saat ini.
 * - **Margin kotor** = Penjualan - HPP. Bukan laba bersih: biaya
 *   operasional belum ada di cakupan TokoFlow.
 * - **Piutang** = untuk setiap penjualan `completed`:
 *   `total - SUM(pembayaran berlaku)`, dengan pembayaran yang sudah
 *   di-void dikecualikan (D-026). Tidak dibatasi rentang tanggal karena
 *   piutang adalah saldo berjalan.
 * - **Utang** = rumus yang sama untuk pembelian `received`.
 * - **Nilai persediaan** = `SUM(products.stock * products.cost_price)`
 *   untuk seluruh produk bisnis, termasuk yang dinonaktifkan --- barangnya
 *   masih ada di gudang.
 * - **Stok menipis** = produk **aktif** dengan `stock <= 5`.
 */
class ReportingService
{
    /**
     * Ambang "stok menipis". Bukan angka dari `requirements.md`; dipilih sebagai
     * default yang aman untuk warung dan ditampilkan di UI supaya tidak
     * menjadi angka rahasia.
     */
    public const LOW_STOCK_THRESHOLD = 5;

    // --- Dashboard -----------------------------------------------------

    /**
     * Seluruh angka dashboard dalam satu panggilan, supaya view tidak
     * menyusun query sendiri.
     *
     * @return array<string, mixed>
     */
    public function dashboard(Business $business): array
    {
        $monthToDate = ReportPeriod::currentMonth();
        $today = CarbonImmutable::now()->startOfDay();
        $todayOnly = new ReportPeriod($today, $today);

        return [
            'period' => $monthToDate,
            'salesToday' => $this->salesTotals($business, $todayOnly),
            'salesMonth' => $this->salesTotals($business, $monthToDate),
            'purchasesMonth' => $this->purchaseTotals($business, $monthToDate),
            'receivable' => $this->receivableTotal($business),
            'payable' => $this->payableTotal($business),
            'inventoryValue' => $this->inventoryValue($business),
            'lowStockThreshold' => self::LOW_STOCK_THRESHOLD,
            'lowStock' => $this->lowStockProducts($business, 5),
            'lowStockCount' => $this->lowStockProducts($business)->count(),
            'recentSales' => $this->recentSales($business, 5),
            'draftPurchases' => $this->draftPurchases($business, 5),
            'draftPurchaseCount' => $this->draftPurchases($business)->count(),
        ];
    }

    /**
     * @return array{count: int, total: string, cost: string, margin: string}
     */
    public function salesTotals(Business $business, ReportPeriod $period): array
    {
        $row = Sale::query()
            ->forBusiness($business)
            ->where('status', SaleStatus::Completed)
            ->whereBetween('sold_at', $period->dates())
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('COALESCE(SUM(total), 0) as sales_total')
            ->first();

        $total = self::decimal($row?->sales_total);
        $cost = $this->salesCostTotal($business, $period);

        return [
            'count' => (int) ($row?->sales_count ?? 0),
            'total' => $total,
            'cost' => $cost,
            'margin' => self::subtract($total, $cost),
        ];
    }

    /**
     * @return array{count: int, total: string}
     */
    public function purchaseTotals(Business $business, ReportPeriod $period): array
    {
        $row = Purchase::query()
            ->forBusiness($business)
            ->where('status', PurchaseStatus::Received)
            ->whereBetween('purchased_at', $period->dates())
            ->selectRaw('COUNT(*) as purchase_count')
            ->selectRaw('COALESCE(SUM(total), 0) as purchase_total')
            ->first();

        return [
            'count' => (int) ($row?->purchase_count ?? 0),
            'total' => self::decimal($row?->purchase_total),
        ];
    }

    public function salesCostTotal(Business $business, ReportPeriod $period): string
    {
        return self::decimal(
            SaleItem::query()
                ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
                ->where('sales.business_id', $business->getKey())
                ->where('sales.status', SaleStatus::Completed)
                ->whereBetween('sales.sold_at', $period->dates())
                ->selectRaw('COALESCE(SUM(sale_items.quantity * sale_items.unit_cost), 0) as cost')
                ->value('cost')
        );
    }

    /**
     * Penjualan dikelompokkan per produk: kuantitas, omzet, HPP, margin.
     *
     * @return Collection<int, object>
     */
    public function salesByProduct(Business $business, ReportPeriod $period): Collection
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.business_id', $business->getKey())
            ->where('sales.status', SaleStatus::Completed)
            ->whereBetween('sales.sold_at', $period->dates())
            ->selectRaw('sale_items.product_id')
            ->selectRaw('products.sku')
            ->selectRaw('products.name')
            ->selectRaw('products.unit')
            ->selectRaw('SUM(sale_items.quantity) as quantity')
            ->selectRaw('SUM(sale_items.subtotal) as revenue')
            ->selectRaw('SUM(sale_items.quantity * sale_items.unit_cost) as cost')
            ->groupBy('sale_items.product_id', 'products.sku', 'products.name', 'products.unit')
            ->orderByDesc('revenue')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function salesByDay(Business $business, ReportPeriod $period): Collection
    {
        return Sale::query()
            ->forBusiness($business)
            ->where('status', SaleStatus::Completed)
            ->whereBetween('sold_at', $period->dates())
            ->selectRaw('sold_at')
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('COALESCE(SUM(total), 0) as sales_total')
            ->groupBy('sold_at')
            ->orderBy('sold_at')
            ->get();
    }

    /**
     * Pembelian yang benar-benar diterima, dikelompokkan per supplier.
     *
     * @return Collection<int, object>
     */
    public function purchasesBySupplier(Business $business, ReportPeriod $period): Collection
    {
        $rows = Purchase::query()
            ->forBusiness($business)
            ->where('purchases.status', PurchaseStatus::Received)
            ->whereBetween('purchases.purchased_at', $period->dates())
            ->selectRaw('purchases.supplier_id')
            ->selectRaw('COUNT(*) as purchase_count')
            ->selectRaw('COALESCE(SUM(purchases.total), 0) as purchase_total')
            ->groupBy('purchases.supplier_id')
            ->orderByDesc('purchase_total')
            ->get();

        return $this->attachNames(
            $rows,
            'supplier_id',
            Supplier::query()->forBusiness($business)->pluck('name', 'id'),
        );
    }

    /**
     * @return Collection<int, object>
     */
    public function purchasesByDay(Business $business, ReportPeriod $period): Collection
    {
        return Purchase::query()
            ->forBusiness($business)
            ->where('status', PurchaseStatus::Received)
            ->whereBetween('purchased_at', $period->dates())
            ->selectRaw('purchased_at')
            ->selectRaw('COUNT(*) as purchase_count')
            ->selectRaw('COALESCE(SUM(total), 0) as purchase_total')
            ->groupBy('purchased_at')
            ->orderBy('purchased_at')
            ->get();
    }

    // --- Saldo berjalan ------------------------------------------------

    /**
     * Piutang berjalan per pelanggan, hanya yang masih bersisa.
     *
     * @return Collection<int, object>
     */
    public function receivables(Business $business): Collection
    {
        $paid = Payment::query()
            ->selectRaw('payable_id, SUM(amount) as paid_total')
            ->where('payable_type', (new Sale)->getMorphClass())
            ->whereNull('voided_at')
            ->groupBy('payable_id');

        $rows = Sale::query()
            ->forBusiness($business)
            ->where('sales.status', SaleStatus::Completed)
            ->leftJoinSub($paid, 'paid', fn ($join) => $join->on('paid.payable_id', '=', 'sales.id'))
            ->selectRaw('sales.customer_id')
            ->selectRaw('COUNT(*) as sales_count')
            ->selectRaw('COALESCE(SUM(sales.total), 0) as document_total')
            ->selectRaw('COALESCE(SUM(paid.paid_total), 0) as paid_total')
            ->groupBy('sales.customer_id')
            ->havingRaw('COALESCE(SUM(sales.total), 0) - COALESCE(SUM(paid.paid_total), 0) > 0')
            ->get();

        return $this->attachNames(
            $rows,
            'customer_id',
            Customer::query()->forBusiness($business)->pluck('name', 'id'),
        );
    }

    /**
     * Utang berjalan per supplier, hanya yang masih bersisa.
     *
     * @return Collection<int, object>
     */
    public function payables(Business $business): Collection
    {
        $paid = Payment::query()
            ->selectRaw('payable_id, SUM(amount) as paid_total')
            ->where('payable_type', (new Purchase)->getMorphClass())
            ->whereNull('voided_at')
            ->groupBy('payable_id');

        $rows = Purchase::query()
            ->forBusiness($business)
            ->where('purchases.status', PurchaseStatus::Received)
            ->leftJoinSub($paid, 'paid', fn ($join) => $join->on('paid.payable_id', '=', 'purchases.id'))
            ->selectRaw('purchases.supplier_id')
            ->selectRaw('COUNT(*) as purchase_count')
            ->selectRaw('COALESCE(SUM(purchases.total), 0) as document_total')
            ->selectRaw('COALESCE(SUM(paid.paid_total), 0) as paid_total')
            ->groupBy('purchases.supplier_id')
            ->havingRaw('COALESCE(SUM(purchases.total), 0) - COALESCE(SUM(paid.paid_total), 0) > 0')
            ->get();

        return $this->attachNames(
            $rows,
            'supplier_id',
            Supplier::query()->forBusiness($business)->pluck('name', 'id'),
        );
    }

    public function receivableTotal(Business $business): string
    {
        return self::sumDue($this->receivables($business));
    }

    public function payableTotal(Business $business): string
    {
        return self::sumDue($this->payables($business));
    }

    // --- Persediaan ----------------------------------------------------

    public function inventoryValue(Business $business): string
    {
        return self::decimal(
            Product::query()
                ->forBusiness($business)
                ->selectRaw('COALESCE(SUM(stock * cost_price), 0) as value')
                ->value('value')
        );
    }

    /**
     * @return Collection<int, Product>
     */
    public function lowStockProducts(Business $business, ?int $limit = null): Collection
    {
        $query = Product::query()
            ->forBusiness($business)
            ->active()
            ->where('stock', '<=', self::LOW_STOCK_THRESHOLD)
            ->orderBy('stock')
            ->orderBy('name');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    /**
     * @return Collection<int, Product>
     */
    public function productsForReport(Business $business): Collection
    {
        return Product::query()
            ->forBusiness($business)
            ->orderBy('name')
            ->get();
    }

    /**
     * Riwayat pergerakan stok, opsional disaring per produk dan rentang.
     */
    public function movements(
        Business $business,
        ReportPeriod $period,
        ?int $productId = null,
        int $perPage = 50,
    ): LengthAwarePaginator {
        return InventoryMovement::query()
            ->forBusiness($business)
            ->with(['product', 'user', 'source'])
            ->when($productId !== null, fn ($query) => $query->where('product_id', $productId))
            ->whereBetween('occurred_at', [$period->from->startOfDay(), $period->to->endOfDay()])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    // --- Daftar pendek untuk dashboard ---------------------------------

    /**
     * @return Collection<int, Sale>
     */
    public function recentSales(Business $business, int $limit = 5): Collection
    {
        return Sale::query()
            ->forBusiness($business)
            ->with(['customer', 'payments'])
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Draf pembelian yang menunggu konfirmasi penerimaan.
     *
     * @return Collection<int, Purchase>
     */
    public function draftPurchases(Business $business, ?int $limit = null): Collection
    {
        $query = Purchase::query()
            ->forBusiness($business)
            ->where('status', PurchaseStatus::Draft)
            ->with('supplier')
            ->orderBy('purchased_at')
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        return $query->get();
    }

    // --- Helper --------------------------------------------------------

    /**
     * Lengkapi baris agregat dengan nama pemiliknya (pelanggan/supplier).
     *
     * `due_total` hanya dihitung bila barisnya punya `paid_total`, supaya
     * laporan yang tidak bicara soal saldo tidak mendapat kolom sisa palsu.
     *
     * @param  Collection<int, object>  $rows
     * @param  Collection<int, string>  $names
     * @return Collection<int, object>
     */
    private function attachNames(Collection $rows, string $foreignKey, Collection $names): Collection
    {
        return $rows->map(function (object $row) use ($foreignKey, $names): object {
            $row->name = $names[$row->{$foreignKey}] ?? '—';

            if (isset($row->paid_total)) {
                $row->due_total = self::subtract(
                    (string) $row->document_total,
                    (string) $row->paid_total,
                );
            }

            return $row;
        });
    }

    /**
     * @param  Collection<int, object>  $rows
     */
    private static function sumDue(Collection $rows): string
    {
        $total = 0.0;

        foreach ($rows as $row) {
            $total += Money::toFloat($row->due_total ?? 0);
        }

        return self::decimal($total);
    }

    private static function decimal(mixed $value): string
    {
        return number_format(round(Money::toFloat($value), 2), 2, '.', '');
    }

    private static function subtract(string $a, string $b): string
    {
        return self::decimal(Money::toFloat($a) - Money::toFloat($b));
    }
}
