<?php

namespace Tests\Feature\Reports;

use App\Models\InventoryMovement;
use App\Models\Product;
use App\Services\ReportingService;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinessUsers;
use Tests\Concerns\ManagesStock;
use Tests\TestCase;

/**
 * Laporan stok: stok saat ini, nilai persediaan, daftar produk menipis,
 * dan riwayat pergerakan stok (requirements.md Bagian 4 "Laporan").
 */
class StockReportTest extends TestCase
{
    use CreatesBusinessUsers, ManagesStock, RefreshDatabase;

    private function october(): ReportPeriod
    {
        return new ReportPeriod(
            CarbonImmutable::parse('2026-10-01'),
            CarbonImmutable::parse('2026-10-31'),
        );
    }

    public function test_the_inventory_value_is_stock_times_cost_price(): void
    {
        [$user, $business] = $this->businessWithUser();

        $this->setStock(Product::factory()->forBusiness($business)->create(['cost_price' => '60000.00']), 10, $user);
        $this->setStock(Product::factory()->forBusiness($business)->create(['cost_price' => '10000.00']), 3, $user);

        $value = app(ReportingService::class)->inventoryValue($business);

        $this->assertSame('630000.00', $value);

        $this->actingAs($user)
            ->get(route('reports.stock'))
            ->assertOk()
            ->assertSee('Nilai persediaan')
            ->assertSee('Rp630.000');
    }

    public function test_inactive_products_still_count_towards_the_inventory_value(): void
    {
        [$user, $business] = $this->businessWithUser();

        $product = Product::factory()->forBusiness($business)->create([
            'cost_price' => '25000.00',
            'is_active' => false,
        ]);

        $this->setStock($product, 4, $user);

        // Barangnya masih ada di gudang walaupun produknya tidak dijual lagi.
        $this->assertSame('100000.00', app(ReportingService::class)->inventoryValue($business));
    }

    public function test_low_stock_only_lists_active_products_at_or_below_the_threshold(): void
    {
        [$user, $business] = $this->businessWithUser();

        $cukup = $this->setStock(
            Product::factory()->forBusiness($business)->create(['name' => 'Produk Cukup']),
            10,
            $user,
        );

        $menipis = $this->setStock(
            Product::factory()->forBusiness($business)->create(['name' => 'Produk Menipis']),
            3,
            $user,
        );

        $kosong = Product::factory()->forBusiness($business)->create(['name' => 'Produk Kosong']);

        $nonaktif = Product::factory()->forBusiness($business)->create([
            'name' => 'Produk Nonaktif',
            'is_active' => false,
        ]);
        $this->setStock($nonaktif, 1, $user);

        $low = app(ReportingService::class)->lowStockProducts($business);

        $this->assertSame(ReportingService::LOW_STOCK_THRESHOLD, 5);
        $this->assertSame(2, $low->count());

        // Hanya produk aktif dengan stok 5 atau kurang.
        $ids = $low->pluck('id')->all();
        $this->assertContains($menipis->getKey(), $ids);
        $this->assertContains($kosong->getKey(), $ids);
        $this->assertNotContains($cukup->getKey(), $ids);
        $this->assertNotContains($nonaktif->getKey(), $ids, 'Produk nonaktif tidak masuk daftar restock.');

        $this->actingAs($user)
            ->get(route('reports.stock'))
            ->assertOk()
            ->assertSee('Produk Menipis')
            ->assertSee('2 produk');
    }

    public function test_the_product_list_shows_stock_and_its_value(): void
    {
        [$user, $business] = $this->businessWithUser();

        $this->setStock(Product::factory()->forBusiness($business)->create([
            'name' => 'Selimut Tebal',
            'sku' => 'SLM-001',
            'unit' => 'pcs',
            'cost_price' => '60000.00',
        ]), 12, $user);

        $this->actingAs($user)
            ->get(route('reports.stock'))
            ->assertOk()
            ->assertSee('Selimut Tebal')
            ->assertSee('SLM-001')
            ->assertSee('12 pcs')
            ->assertSee('Rp720.000');
    }

    public function test_the_movement_history_can_be_filtered_by_product(): void
    {
        [$user, $business] = $this->businessWithUser();

        $selimut = Product::factory()->forBusiness($business)->create(['name' => 'Selimut Tebal']);
        $handuk = Product::factory()->forBusiness($business)->create(['name' => 'Handuk Mandi']);

        InventoryMovement::factory()->forProduct($selimut)->create([
            'occurred_at' => '2026-10-05 09:00:00',
            'quantity' => 10,
            'balance_after' => 10,
            'reason' => 'Stok awal selimut',
        ]);

        InventoryMovement::factory()->forProduct($handuk)->create([
            'occurred_at' => '2026-10-06 09:00:00',
            'quantity' => 7,
            'balance_after' => 7,
            'reason' => 'Stok awal handuk',
        ]);

        $service = app(ReportingService::class);

        $all = $service->movements($business, $this->october());
        $this->assertSame(2, $all->total());

        $filtered = $service->movements($business, $this->october(), $selimut->getKey());
        $this->assertSame(1, $filtered->total());
        $this->assertSame('Stok awal selimut', $filtered->first()->reason);

        $this->actingAs($user)
            ->get(route('reports.stock', ['product_id' => $selimut->getKey()]))
            ->assertOk()
            ->assertSee('Stok awal selimut')
            ->assertDontSee('Stok awal handuk');
    }

    public function test_the_movement_history_respects_the_date_range(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = Product::factory()->forBusiness($business)->create();

        InventoryMovement::factory()->forProduct($product)->create([
            'occurred_at' => '2026-09-30 09:00:00',
            'reason' => 'Mutasi September',
        ]);

        InventoryMovement::factory()->forProduct($product)->create([
            'occurred_at' => '2026-10-31 23:59:00',
            'reason' => 'Mutasi Akhir Oktober',
        ]);

        $rows = app(ReportingService::class)->movements($business, $this->october());

        $this->assertSame(1, $rows->total());
        $this->assertSame('Mutasi Akhir Oktober', $rows->first()->reason);
    }

    public function test_another_business_stock_is_not_shown(): void
    {
        [$user, $business] = $this->businessWithUser();
        $this->setStock(Product::factory()->forBusiness($business)->create(['name' => 'Milik Sendiri']), 5, $user);

        [, $otherBusiness] = $this->businessWithUser();
        Product::factory()->forBusiness($otherBusiness)->create([
            'name' => 'Milik Orang Lain',
            'cost_price' => '999000.00',
        ]);

        $this->actingAs($user)
            ->get(route('reports.stock'))
            ->assertOk()
            ->assertSee('Milik Sendiri')
            ->assertDontSee('Milik Orang Lain')
            ->assertDontSee('Rp999.000');
    }
}
