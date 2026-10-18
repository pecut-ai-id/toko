<?php

namespace Tests\Feature\Reports;

use App\Models\Customer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Services\ReportingService;
use App\Support\ReportPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinessUsers;
use Tests\Concerns\CreatesReportableDocuments;
use Tests\Concerns\ManagesStock;
use Tests\TestCase;

/**
 * Dashboard dengan angka nyata (requirements.md Bagian 4 "Dashboard").
 *
 * Yang diuji di sini bukan hanya "halaman bisa dibuka", tetapi bahwa
 * angkanya berasal dari transaksi nyata dan tidak ada sisa angka
 * placeholder.
 */
class DashboardSummaryTest extends TestCase
{
    use CreatesBusinessUsers, CreatesReportableDocuments, ManagesStock, RefreshDatabase;

    private string $lastMonth;

    private string $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->today = now()->toDateString();
        $this->lastMonth = now()->subMonthNoOverflow()->startOfMonth()->toDateString();
    }

    public function test_the_dashboard_shows_only_today_and_this_month_sales(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();
        $customer = Customer::factory()->forBusiness($business)->create();

        $this->completedSale($business, $this->today, [
            ['product' => $product, 'quantity' => 1, 'unit_price' => '100.000', 'unit_cost' => '60.000'],
        ], $customer);

        // Bulan lalu: di luar periode dashboard.
        $this->completedSale($business, $this->lastMonth, [
            ['product' => $product, 'quantity' => 1, 'unit_price' => '999.000', 'unit_cost' => '1.000'],
        ], $customer);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Penjualan hari ini')
            ->assertSee('Penjualan bulan ini')
            ->assertSee('Rp100.000')
            ->assertSee('Rp40.000')
            ->assertDontSee('Ringkasan operasional belum tersedia')
            ->assertDontSee('masih placeholder');

        // Penjualan bulan lalu tidak ikut dihitung sebagai "bulan ini".
        $this->assertSame(
            '100000.00',
            app(ReportingService::class)->salesTotals($business, ReportPeriod::currentMonth())['total'],
        );
    }

    public function test_the_dashboard_shows_receivables_payables_and_inventory_value(): void
    {
        [$user, $business] = $this->businessWithUser();

        $this->setStock(Product::factory()->forBusiness($business)->create(['cost_price' => '60000.00']), 10, $user);

        $customer = Customer::factory()->forBusiness($business)->create();
        $product = Product::factory()->forBusiness($business)->create();

        $sale = $this->completedSale($business, $this->today, [
            ['product' => $product, 'quantity' => 1, 'unit_price' => '100.000', 'unit_cost' => '60.000'],
        ], $customer);

        Payment::factory()->forSale($sale)->create(['amount' => '30000.00', 'paid_at' => $this->today]);

        $supplier = Supplier::factory()->forBusiness($business)->create();
        $purchase = $this->receivedPurchase($business, $this->today, [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '50.000'],
        ], $supplier);

        Payment::factory()->forPurchase($purchase)->create(['amount' => '200000.00', 'paid_at' => $this->today]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Piutang berjalan')
            ->assertSee('Rp70.000')
            ->assertSee('Utang berjalan')
            ->assertSee('Rp300.000')
            ->assertSee('Nilai persediaan')
            ->assertSee('Rp600.000');
    }

    public function test_the_dashboard_lists_products_that_need_restocking(): void
    {
        [$user, $business] = $this->businessWithUser();

        Product::factory()->forBusiness($business)->create(['name' => 'Selimut Cukup']);
        $this->setStock(Product::factory()->forBusiness($business)->create(['name' => 'Selimut Menipis']), 2, $user);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Perlu restock (2)')
            ->assertSee('Selimut Menipis')
            ->assertSee('Selimut Cukup');
    }

    public function test_the_dashboard_lists_draft_purchases_waiting_to_be_received(): void
    {
        [$user, $business] = $this->businessWithUser();

        $draft = Purchase::factory()->forBusiness($business)->create([
            'purchased_at' => $this->today,
            'total' => '150000.00',
            'subtotal' => '150000.00',
        ]);

        $this->receivedPurchase($business, $this->today, [
            ['product' => Product::factory()->forBusiness($business)->create(), 'quantity' => 1, 'unit_cost' => '10.000'],
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Draf pembelian menunggu penerimaan (1)')
            ->assertSee($draft->number)
            ->assertSee('Rp150.000');
    }

    public function test_recent_sales_show_the_outstanding_amount_per_sale(): void
    {
        [$user, $business] = $this->businessWithUser();
        $customer = Customer::factory()->forBusiness($business)->create(['name' => 'Toko Andalan']);

        $sale = $this->completedSale($business, $this->today, [
            ['product' => Product::factory()->forBusiness($business)->create(), 'quantity' => 1, 'unit_price' => '50.000', 'unit_cost' => '30.000'],
        ], $customer);

        Payment::factory()->forSale($sale)->create(['amount' => '20000.00', 'paid_at' => $this->today]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Penjualan terbaru')
            ->assertSee($sale->number)
            ->assertSee('Toko Andalan')
            ->assertSee('Rp30.000');
    }

    public function test_the_dashboard_does_not_leak_another_business_transactions(): void
    {
        [$user, $business] = $this->businessWithUser();

        [, $otherBusiness] = $this->businessWithUser();
        $this->completedSale($otherBusiness, $this->today, [
            [
                'product' => Product::factory()->forBusiness($otherBusiness)->create(),
                'quantity' => 1,
                'unit_price' => '888.000',
                'unit_cost' => '1.000',
            ],
        ], Customer::factory()->forBusiness($otherBusiness)->create());

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Rp888.000');

        $this->assertSame(
            0.0,
            (float) Sale::query()->forBusiness($business)->whereDate('sold_at', $this->today)->sum('total'),
        );
    }
}
