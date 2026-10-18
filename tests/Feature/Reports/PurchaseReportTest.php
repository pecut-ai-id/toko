<?php

namespace Tests\Feature\Reports;

use App\Enums\BusinessRole;
use App\Enums\PurchaseStatus;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ReportingService;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinessUsers;
use Tests\Concerns\CreatesReportableDocuments;
use Tests\TestCase;

/**
 * Laporan pembelian.
 *
 * Aturan yang dipegang: hanya pembelian yang **diterima** yang dihitung,
 * karena hanya penerimaan yang menambah stok dan menimbulkan utang.
 */
class PurchaseReportTest extends TestCase
{
    use CreatesBusinessUsers, CreatesReportableDocuments, RefreshDatabase;

    private function october(): ReportPeriod
    {
        return new ReportPeriod(
            CarbonImmutable::parse('2026-10-01'),
            CarbonImmutable::parse('2026-10-18'),
        );
    }

    public function test_only_received_purchases_are_counted(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        $this->receivedPurchase($business, '2026-10-05', [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '5.000'],
        ]);

        // Draf: belum menambah stok, jadi belum dihitung.
        Purchase::factory()->forBusiness($business)->create([
            'purchased_at' => '2026-10-06',
            'status' => PurchaseStatus::Draft,
            'subtotal' => '900000.00',
            'total' => '900000.00',
        ]);

        // Dibatalkan: stoknya sudah dibalik.
        Purchase::factory()->forBusiness($business)->create([
            'purchased_at' => '2026-10-07',
            'status' => PurchaseStatus::Cancelled,
            'subtotal' => '800000.00',
            'total' => '800000.00',
            'cancelled_at' => now(),
        ]);

        $totals = app(ReportingService::class)->purchaseTotals($business, $this->october());

        $this->assertSame(1, $totals['count']);
        $this->assertSame('50000.00', $totals['total']);

        $this->actingAs($user)
            ->get(route('reports.purchases', ['from' => '2026-10-01', 'to' => '2026-10-18']))
            ->assertOk()
            ->assertSee('Rp50.000')
            ->assertDontSee('Rp900.000')
            ->assertDontSee('Rp800.000');
    }

    public function test_purchases_outside_the_range_are_excluded(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        $this->receivedPurchase($business, '2026-09-30', [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '9.000'],
        ]);

        $totals = app(ReportingService::class)->purchaseTotals($business, $this->october());

        $this->assertSame(0, $totals['count']);
        $this->assertSame('0.00', $totals['total']);
    }

    public function test_the_breakdown_groups_received_purchases_by_supplier(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();
        $besar = Supplier::factory()->forBusiness($business)->create(['name' => 'PT Besar Sentosa']);
        $kecil = Supplier::factory()->forBusiness($business)->create(['name' => 'CV Kecil Jaya']);

        $this->receivedPurchase($business, '2026-10-05', [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '20.000'],
        ], $besar);

        $this->receivedPurchase($business, '2026-10-06', [
            ['product' => $product, 'quantity' => 10, 'unit_cost' => '10.000'],
        ], $besar);

        $this->receivedPurchase($business, '2026-10-07', [
            ['product' => $product, 'quantity' => 5, 'unit_cost' => '10.000'],
        ], $kecil);

        $rows = app(ReportingService::class)->purchasesBySupplier($business, $this->october());

        $this->assertCount(2, $rows);

        // Diurutkan dari total terbesar: PT Besar (300.000) lalu CV Kecil (50.000).
        $this->assertSame('PT Besar Sentosa', $rows->first()->name);
        $this->assertSame(2, (int) $rows->first()->purchase_count);
        $this->assertSame(300000.0, (float) $rows->first()->purchase_total);

        $this->assertSame('CV Kecil Jaya', $rows->last()->name);
        $this->assertSame(50000.0, (float) $rows->last()->purchase_total);

        // Jumlah baris cocok dengan total laporan.
        $totals = app(ReportingService::class)->purchaseTotals($business, $this->october());
        $this->assertSame('350000.00', $totals['total']);
    }

    public function test_the_daily_breakdown_lists_each_day_once(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        $this->receivedPurchase($business, '2026-10-05', [
            ['product' => $product, 'quantity' => 1, 'unit_cost' => '10.000'],
        ]);
        $this->receivedPurchase($business, '2026-10-05', [
            ['product' => $product, 'quantity' => 2, 'unit_cost' => '10.000'],
        ]);
        $this->receivedPurchase($business, '2026-10-07', [
            ['product' => $product, 'quantity' => 1, 'unit_cost' => '10.000'],
        ]);

        $rows = app(ReportingService::class)->purchasesByDay($business, $this->october());

        $this->assertCount(2, $rows);
        $this->assertSame(2, (int) $rows->first()->purchase_count);
        $this->assertSame(30000.0, (float) $rows->first()->purchase_total);
        $this->assertSame(1, (int) $rows->last()->purchase_count);
    }

    public function test_staff_can_view_the_purchase_report(): void
    {
        [$owner, $business] = $this->businessWithUser(BusinessRole::Owner);
        $staff = User::factory()->create();
        $business->users()->attach($staff->getKey(), ['role' => BusinessRole::Staff->value]);

        $this->actingAs($staff)
            ->get(route('reports.purchases'))
            ->assertOk()
            ->assertSee('Total pembelian diterima');
    }

    public function test_another_business_purchases_are_not_counted(): void
    {
        [$user, $business] = $this->businessWithUser();

        [, $otherBusiness] = $this->businessWithUser();
        $otherProduct = Product::factory()->forBusiness($otherBusiness)->create();
        $this->receivedPurchase($otherBusiness, '2026-10-05', [
            ['product' => $otherProduct, 'quantity' => 7, 'unit_cost' => '9.000'],
        ]);

        $totals = app(ReportingService::class)->purchaseTotals($business, $this->october());

        $this->assertSame(0, $totals['count']);
        $this->assertSame('0.00', $totals['total']);
    }
}
