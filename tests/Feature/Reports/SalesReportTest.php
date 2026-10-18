<?php

namespace Tests\Feature\Reports;

use App\Enums\BusinessRole;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\ReportingService;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinessUsers;
use Tests\Concerns\CreatesReportableDocuments;
use Tests\TestCase;

/**
 * Laporan penjualan (requirements.md Bagian 4 "Laporan").
 *
 * Angka laporan harus cocok dengan transaksi sumbernya; itulah yang
 * diuji paling keras di sini.
 */
class SalesReportTest extends TestCase
{
    use CreatesBusinessUsers, CreatesReportableDocuments, RefreshDatabase;

    private function october(): ReportPeriod
    {
        return new ReportPeriod(
            CarbonImmutable::parse('2026-10-01'),
            CarbonImmutable::parse('2026-10-18'),
        );
    }

    public function test_sales_totals_match_the_source_transactions(): void
    {
        [$user, $business] = $this->businessWithUser();
        $customer = Customer::factory()->forBusiness($business)->create();
        $selimut = Product::factory()->forBusiness($business)->create();
        $handuk = Product::factory()->forBusiness($business)->create();

        $this->completedSale($business, '2026-10-10', [
            ['product' => $selimut, 'quantity' => 2, 'unit_price' => '85.000', 'unit_cost' => '60.000'],
        ], $customer);

        $this->completedSale($business, '2026-10-18', [
            ['product' => $handuk, 'quantity' => 3, 'unit_price' => '20.000', 'unit_cost' => '10.000'],
        ], $customer);

        // Di luar rentang: tidak boleh ikut terhitung.
        $this->completedSale($business, '2026-09-30', [
            ['product' => $selimut, 'quantity' => 99, 'unit_price' => '85.000', 'unit_cost' => '60.000'],
        ], $customer);

        $totals = app(ReportingService::class)->salesTotals($business, $this->october());

        $this->assertSame(2, $totals['count']);
        $this->assertSame('230000.00', $totals['total']);
        $this->assertSame('150000.00', $totals['cost']);
        $this->assertSame('80000.00', $totals['margin']);

        // Cocok dengan jumlah baris sumber pada rentang yang sama.
        $this->assertSame(
            230000.0,
            (float) Sale::query()->forBusiness($business)->whereBetween('sold_at', $this->october()->dates())->sum('total'),
        );

        $this->actingAs($user)
            ->get(route('reports.sales', ['from' => '2026-10-01', 'to' => '2026-10-18']))
            ->assertOk()
            ->assertSee('Rp230.000')
            ->assertSee('Rp150.000')
            ->assertSee('Rp80.000')
            ->assertSee('2 transaksi')
            ->assertSee('01/10/2026 - 18/10/2026');
    }

    public function test_the_range_is_inclusive_on_both_ends(): void
    {
        [$user, $business] = $this->businessWithUser();
        $customer = Customer::factory()->forBusiness($business)->create();
        $product = Product::factory()->forBusiness($business)->create();

        $this->completedSale($business, '2026-10-01', [
            ['product' => $product, 'quantity' => 1, 'unit_price' => '10.000', 'unit_cost' => '6.000'],
        ], $customer);

        $this->completedSale($business, '2026-10-31', [
            ['product' => $product, 'quantity' => 1, 'unit_price' => '10.000', 'unit_cost' => '6.000'],
        ], $customer);

        $period = new ReportPeriod(CarbonImmutable::parse('2026-10-01'), CarbonImmutable::parse('2026-10-31'));

        $this->assertSame(2, app(ReportingService::class)->salesTotals($business, $period)['count']);
    }

    public function test_cancelled_sales_are_excluded(): void
    {
        [$user, $business] = $this->businessWithUser();
        $customer = Customer::factory()->forBusiness($business)->create();
        $product = Product::factory()->forBusiness($business)->create();

        $sale = $this->completedSale($business, '2026-10-10', [
            ['product' => $product, 'quantity' => 5, 'unit_price' => '10.000', 'unit_cost' => '6.000'],
        ], $customer);

        $sale->update(['status' => SaleStatus::Cancelled]);

        $totals = app(ReportingService::class)->salesTotals($business, $this->october());

        $this->assertSame(0, $totals['count']);
        $this->assertSame('0.00', $totals['total']);
        $this->assertSame('0.00', $totals['cost']);
    }

    public function test_the_product_breakdown_groups_items_by_product(): void
    {
        [$user, $business] = $this->businessWithUser();
        $customer = Customer::factory()->forBusiness($business)->create();
        $selimut = Product::factory()->forBusiness($business)->create(['name' => 'Selimut Tebal']);
        $handuk = Product::factory()->forBusiness($business)->create(['name' => 'Handuk Mandi']);

        // Selimut dijual dua kali: harus digabung jadi satu baris.
        $this->completedSale($business, '2026-10-10', [
            ['product' => $selimut, 'quantity' => 2, 'unit_price' => '85.000', 'unit_cost' => '60.000'],
        ], $customer);

        $this->completedSale($business, '2026-10-12', [
            ['product' => $selimut, 'quantity' => 1, 'unit_price' => '85.000', 'unit_cost' => '60.000'],
            ['product' => $handuk, 'quantity' => 3, 'unit_price' => '20.000', 'unit_cost' => '10.000'],
        ], $customer);

        $rows = app(ReportingService::class)->salesByProduct($business, $this->october());

        $this->assertCount(2, $rows);

        // Diurutkan dari omzet terbesar.
        $top = $rows->first();
        $this->assertSame($selimut->getKey(), (int) $top->product_id);
        $this->assertSame(3.0, (float) $top->quantity);
        $this->assertSame('255000.00', number_format((float) $top->revenue, 2, '.', ''));
        $this->assertSame('180000.00', number_format((float) $top->cost, 2, '.', ''));

        $second = $rows->last();
        $this->assertSame($handuk->getKey(), (int) $second->product_id);
        $this->assertSame('60000.00', number_format((float) $second->revenue, 2, '.', ''));
    }

    public function test_the_daily_breakdown_lists_each_day_once(): void
    {
        [$user, $business] = $this->businessWithUser();
        $customer = Customer::factory()->forBusiness($business)->create();
        $product = Product::factory()->forBusiness($business)->create();

        foreach (['2026-10-10', '2026-10-10', '2026-10-12'] as $date) {
            $this->completedSale($business, $date, [
                ['product' => $product, 'quantity' => 1, 'unit_price' => '10.000', 'unit_cost' => '6.000'],
            ], $customer);
        }

        $rows = app(ReportingService::class)->salesByDay($business, $this->october());

        $this->assertCount(2, $rows);
        $this->assertSame('2026-10-10', CarbonImmutable::parse($rows->first()->sold_at)->toDateString());
        $this->assertSame(2, (int) $rows->first()->sales_count);
        $this->assertSame(20000.0, (float) $rows->first()->sales_total);
    }

    public function test_an_inverted_range_is_rejected(): void
    {
        [$user] = $this->businessWithUser();
        $query = ['from' => '2026-10-18', 'to' => '2026-10-01'];

        $this->actingAs($user)
            ->get(route('reports.sales', $query))
            ->assertSessionHasErrors('to')
            ->assertRedirect(route('reports.sales'));

        // Pesannya benar-benar terlihat di halaman laporan, bukan hilang
        // di halaman sebelumnya.
        $this->actingAs($user)
            ->followingRedirects()
            ->get(route('reports.sales', $query))
            ->assertOk()
            ->assertSee('Tanggal akhir tidak boleh lebih awal dari tanggal mulai.');
    }

    public function test_an_invalid_date_format_is_rejected(): void
    {
        [$user] = $this->businessWithUser();

        $this->actingAs($user)
            ->get(route('reports.sales', ['from' => '18-10-2026']))
            ->assertSessionHasErrors('from')
            ->assertRedirect(route('reports.sales'));
    }

    public function test_a_rolled_over_date_is_rejected(): void
    {
        [$user] = $this->businessWithUser();

        // 45 Oktober: ditolak, bukan digeser diam-diam ke November.
        $this->actingAs($user)
            ->get(route('reports.sales', ['from' => '2026-10-45']))
            ->assertSessionHasErrors('from')
            ->assertRedirect(route('reports.sales'));
    }

    public function test_staff_can_view_the_sales_report(): void
    {
        [$owner, $business] = $this->businessWithUser(BusinessRole::Owner);
        $staff = User::factory()->create();
        $business->users()->attach($staff->getKey(), ['role' => BusinessRole::Staff->value]);

        $this->actingAs($staff)
            ->get(route('reports.sales'))
            ->assertOk()
            ->assertSee('Omzet penjualan');
    }

    public function test_another_business_transactions_are_not_counted(): void
    {
        [$user, $business] = $this->businessWithUser();
        $customer = Customer::factory()->forBusiness($business)->create();
        $product = Product::factory()->forBusiness($business)->create();

        $this->completedSale($business, '2026-10-10', [
            ['product' => $product, 'quantity' => 1, 'unit_price' => '10.000', 'unit_cost' => '6.000'],
        ], $customer);

        // Bisnis lain dengan angka yang sangat berbeda.
        [, $otherBusiness] = $this->businessWithUser();
        $otherCustomer = Customer::factory()->forBusiness($otherBusiness)->create();
        $otherProduct = Product::factory()->forBusiness($otherBusiness)->create();

        $this->completedSale($otherBusiness, '2026-10-10', [
            ['product' => $otherProduct, 'quantity' => 9, 'unit_price' => '99.000', 'unit_cost' => '50.000'],
        ], $otherCustomer);

        $totals = app(ReportingService::class)->salesTotals($business, $this->october());

        $this->assertSame(1, $totals['count']);
        $this->assertSame('10000.00', $totals['total']);
    }

    public function test_the_reports_hub_redirects_to_the_sales_report(): void
    {
        [$user] = $this->businessWithUser();

        $this->actingAs($user)
            ->get(route('reports.index'))
            ->assertRedirect(route('reports.sales'));
    }

    public function test_every_report_is_protected_from_guests(): void
    {
        foreach (['reports.sales', 'reports.purchases', 'reports.stock', 'reports.receivables', 'reports.payables'] as $name) {
            $this->get(route($name))->assertRedirect(route('login'));
        }
    }
}
