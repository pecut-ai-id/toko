<?php

namespace Tests\Feature\Reports;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinessUsers;
use Tests\Concerns\CreatesReportableDocuments;
use Tests\Concerns\ManagesStock;
use Tests\TestCase;

/**
 * Ekspor CSV laporan (requirements.md Bagian 4 "Laporan": opsional, tapi hanya
 * berguna kalau isinya benar).
 *
 * Pemisah kolom `;` dan angka decimal polos dipilih supaya mudah dibuka
 * di spreadsheet locale Indonesia. Test membaca ulang CSV-nya dengan
 * parser CSV, bukan membandingkan string mentah, supaya hasilnya tidak
 * bergantung pada aturan pengutipan fputcsv.
 */
class ReportCsvExportTest extends TestCase
{
    use CreatesBusinessUsers, CreatesReportableDocuments, ManagesStock, RefreshDatabase;

    /**
     * @return array<int, array<int, string>>
     */
    private function csvRows(string $content): array
    {
        $content = str_replace("\xEF\xBB\xBF", '', $content);

        $rows = [];

        foreach (explode("\n", trim($content)) as $line) {
            if (trim($line) === '') {
                continue;
            }

            $rows[] = str_getcsv($line, ';');
        }

        return $rows;
    }

    public function test_the_sales_report_can_be_exported_as_csv(): void
    {
        [$user, $business] = $this->businessWithUser();
        $customer = Customer::factory()->forBusiness($business)->create();
        $product = Product::factory()->forBusiness($business)->create([
            'sku' => 'SLM-001',
            'name' => 'Selimut Tebal',
            'unit' => 'pcs',
        ]);

        $this->completedSale($business, '2026-10-10', [
            ['product' => $product, 'quantity' => 2, 'unit_price' => '85.000', 'unit_cost' => '60.000'],
        ], $customer);

        $response = $this->actingAs($user)->get(route('reports.sales', [
            'from' => '2026-10-01',
            'to' => '2026-10-18',
            'format' => 'csv',
        ]));

        $response->assertOk();
        $this->assertStringContainsString(
            'laporan-penjualan-20261001-20261018.csv',
            (string) $response->headers->get('content-disposition'),
        );
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $rows = $this->csvRows($response->streamedContent());

        $this->assertSame(['SKU', 'Produk', 'Satuan', 'Kuantitas', 'Omzet', 'HPP', 'Margin'], $rows[0]);
        $this->assertSame(['SLM-001', 'Selimut Tebal', 'pcs', '2.00', '170000.00', '120000.00', '50000.00'], $rows[1]);
        $this->assertCount(2, $rows);
    }

    public function test_the_purchase_report_can_be_exported_as_csv(): void
    {
        [$user, $business] = $this->businessWithUser();
        $supplier = Supplier::factory()->forBusiness($business)->create(['name' => 'PT Utang Jaya']);

        $this->receivedPurchase($business, '2026-10-05', [
            [
                'product' => Product::factory()->forBusiness($business)->create(),
                'quantity' => 10,
                'unit_cost' => '20.000',
            ],
        ], $supplier);

        $rows = $this->csvRows($this->actingAs($user)->get(route('reports.purchases', [
            'from' => '2026-10-01',
            'to' => '2026-10-18',
            'format' => 'csv',
        ]))->streamedContent());

        $this->assertSame(['Supplier', 'Jumlah pembelian', 'Total'], $rows[0]);
        $this->assertSame(['PT Utang Jaya', '1', '200000.00'], $rows[1]);
    }

    public function test_the_stock_report_can_be_exported_as_csv(): void
    {
        [$user, $business] = $this->businessWithUser();

        $this->setStock(Product::factory()->forBusiness($business)->create([
            'sku' => 'SLM-001',
            'name' => 'Selimut Tebal',
            'unit' => 'pcs',
            'cost_price' => '60000.00',
        ]), 12, $user);

        $rows = $this->csvRows($this->actingAs($user)
            ->get(route('reports.stock', ['format' => 'csv']))
            ->streamedContent());

        $this->assertSame(
            ['SKU', 'Produk', 'Satuan', 'Status', 'Stok', 'Harga pokok', 'Nilai persediaan'],
            $rows[0],
        );
        $this->assertSame(['SLM-001', 'Selimut Tebal', 'pcs', 'Aktif', '12.00', '60000.00', '720000.00'], $rows[1]);
    }

    public function test_the_receivables_report_can_be_exported_as_csv(): void
    {
        [$user, $business] = $this->businessWithUser();
        $customer = Customer::factory()->forBusiness($business)->create(['name' => 'Toko Andalan']);

        $sale = $this->completedSale($business, '2026-10-05', [
            [
                'product' => Product::factory()->forBusiness($business)->create(),
                'quantity' => 1,
                'unit_price' => '100.000',
                'unit_cost' => '60.000',
            ],
        ], $customer);

        Payment::factory()->forSale($sale)->create(['amount' => '40000.00', 'paid_at' => '2026-10-06']);

        $rows = $this->csvRows($this->actingAs($user)
            ->get(route('reports.receivables', ['format' => 'csv']))
            ->streamedContent());

        $this->assertSame(
            ['Pelanggan', 'Jumlah penjualan', 'Total penjualan', 'Sudah dibayar', 'Sisa piutang'],
            $rows[0],
        );
        $this->assertSame(['Toko Andalan', '1', '100000.00', '40000.00', '60000.00'], $rows[1]);
    }

    public function test_the_payables_report_can_be_exported_as_csv(): void
    {
        [$user, $business] = $this->businessWithUser();
        $supplier = Supplier::factory()->forBusiness($business)->create(['name' => 'PT Utang Jaya']);

        $this->receivedPurchase($business, '2026-10-05', [
            [
                'product' => Product::factory()->forBusiness($business)->create(),
                'quantity' => 10,
                'unit_cost' => '20.000',
            ],
        ], $supplier);

        $rows = $this->csvRows($this->actingAs($user)
            ->get(route('reports.payables', ['format' => 'csv']))
            ->streamedContent());

        $this->assertSame(
            ['Supplier', 'Jumlah pembelian', 'Total pembelian', 'Sudah dibayar', 'Sisa utang'],
            $rows[0],
        );
        $this->assertSame(['PT Utang Jaya', '1', '200000.00', '0.00', '200000.00'], $rows[1]);
    }

    public function test_an_unknown_export_format_is_rejected(): void
    {
        [$user] = $this->businessWithUser();

        $this->actingAs($user)
            ->get(route('reports.sales', ['format' => 'xlsx']))
            ->assertSessionHasErrors('format')
            ->assertRedirect(route('reports.sales'));
    }

    public function test_the_csv_export_is_not_available_to_guests(): void
    {
        $this->get(route('reports.sales', ['format' => 'csv']))->assertRedirect(route('login'));
    }
}
