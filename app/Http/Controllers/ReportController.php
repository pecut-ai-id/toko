<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportRequest;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Services\ReportingService;
use App\Support\Csv;
use App\Support\CurrentBusiness;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Laporan berbasis data transaksi nyata (requirements.md Bagian 4 "Laporan").
 *
 * Semua angka dihitung oleh `ReportingService`, jadi definisi metriknya
 * sama persis dengan dashboard. Setiap tabel bisa diekspor sebagai CSV
 * dengan menambahkan `format=csv` pada query string.
 */
class ReportController extends Controller
{
    public function __construct(protected ReportingService $reports) {}

    public function index(): RedirectResponse
    {
        return redirect()->route('reports.sales');
    }

    public function sales(ReportRequest $request, CurrentBusiness $current): View|StreamedResponse
    {
        $this->authorize('viewAny', Sale::class);

        $business = $current->require();
        $period = $request->period();

        $totals = $this->reports->salesTotals($business, $period);
        $byProduct = $this->reports->salesByProduct($business, $period);
        $byDay = $this->reports->salesByDay($business, $period);

        if ($request->wantsCsv()) {
            return Csv::download(
                "laporan-penjualan-{$period->slug()}.csv",
                ['SKU', 'Produk', 'Satuan', 'Kuantitas', 'Omzet', 'HPP', 'Margin'],
                $byProduct->map(fn (object $row): array => [
                    $row->sku,
                    $row->name,
                    $row->unit,
                    Csv::number($row->quantity),
                    Csv::number($row->revenue),
                    Csv::number($row->cost),
                    Csv::number((float) $row->revenue - (float) $row->cost),
                ]),
            );
        }

        return view('reports.sales', [
            'period' => $period,
            'totals' => $totals,
            'byProduct' => $byProduct,
            'byDay' => $byDay,
        ]);
    }

    public function purchases(ReportRequest $request, CurrentBusiness $current): View|StreamedResponse
    {
        $this->authorize('viewAny', Purchase::class);

        $business = $current->require();
        $period = $request->period();

        $totals = $this->reports->purchaseTotals($business, $period);
        $bySupplier = $this->reports->purchasesBySupplier($business, $period);
        $byDay = $this->reports->purchasesByDay($business, $period);

        if ($request->wantsCsv()) {
            return Csv::download(
                "laporan-pembelian-{$period->slug()}.csv",
                ['Supplier', 'Jumlah pembelian', 'Total'],
                $bySupplier->map(fn (object $row): array => [
                    $row->name,
                    $row->purchase_count,
                    Csv::number($row->purchase_total),
                ]),
            );
        }

        return view('reports.purchases', [
            'period' => $period,
            'totals' => $totals,
            'bySupplier' => $bySupplier,
            'byDay' => $byDay,
        ]);
    }

    public function stock(ReportRequest $request, CurrentBusiness $current): View|StreamedResponse
    {
        $this->authorize('viewAny', Product::class);

        $business = $current->require();
        $period = $request->period();
        $products = $this->reports->productsForReport($business);

        if ($request->wantsCsv()) {
            return Csv::download(
                'laporan-stok-'.now()->format('Ymd').'.csv',
                ['SKU', 'Produk', 'Satuan', 'Status', 'Stok', 'Harga pokok', 'Nilai persediaan'],
                $products->map(fn (Product $product): array => [
                    $product->sku,
                    $product->name,
                    $product->unit,
                    $product->is_active ? 'Aktif' : 'Nonaktif',
                    Csv::number($product->stock),
                    Csv::number($product->cost_price),
                    Csv::number(Money::multiply($product->stock, $product->cost_price)),
                ]),
            );
        }

        return view('reports.stock', [
            'period' => $period,
            'products' => $products,
            'inventoryValue' => $this->reports->inventoryValue($business),
            'lowStockThreshold' => ReportingService::LOW_STOCK_THRESHOLD,
            'lowStockCount' => $this->reports->lowStockProducts($business)->count(),
            'movements' => $this->reports->movements($business, $period, $request->productId()),
            'productId' => $request->productId(),
        ]);
    }

    public function receivables(ReportRequest $request, CurrentBusiness $current): View|StreamedResponse
    {
        $this->authorize('viewAny', Sale::class);

        $business = $current->require();
        $rows = $this->reports->receivables($business);

        if ($request->wantsCsv()) {
            return Csv::download(
                'laporan-piutang-'.now()->format('Ymd').'.csv',
                ['Pelanggan', 'Jumlah penjualan', 'Total penjualan', 'Sudah dibayar', 'Sisa piutang'],
                $rows->map(fn (object $row): array => [
                    $row->name,
                    $row->sales_count,
                    Csv::number($row->document_total),
                    Csv::number($row->paid_total),
                    Csv::number($row->due_total),
                ]),
            );
        }

        return view('reports.receivables', [
            'period' => $request->period(),
            'rows' => $rows,
            'total' => $this->reports->receivableTotal($business),
        ]);
    }

    public function payables(ReportRequest $request, CurrentBusiness $current): View|StreamedResponse
    {
        $this->authorize('viewAny', Purchase::class);

        $business = $current->require();
        $rows = $this->reports->payables($business);

        if ($request->wantsCsv()) {
            return Csv::download(
                'laporan-utang-'.now()->format('Ymd').'.csv',
                ['Supplier', 'Jumlah pembelian', 'Total pembelian', 'Sudah dibayar', 'Sisa utang'],
                $rows->map(fn (object $row): array => [
                    $row->name,
                    $row->purchase_count,
                    Csv::number($row->document_total),
                    Csv::number($row->paid_total),
                    Csv::number($row->due_total),
                ]),
            );
        }

        return view('reports.payables', [
            'period' => $request->period(),
            'rows' => $rows,
            'total' => $this->reports->payableTotal($business),
        ]);
    }
}
