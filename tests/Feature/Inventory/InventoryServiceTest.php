<?php

namespace Tests\Feature\Inventory;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidOperationException;
use App\Models\Business;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): InventoryService
    {
        return app(InventoryService::class);
    }

    public function test_stock_increases_and_every_change_is_recorded(): void
    {
        $business = Business::factory()->create();
        $product = Product::factory()->forBusiness($business)->create();

        $movement = DB::transaction(
            fn () => $this->service()->record($product, '10', 'Stok awal')
        );

        $this->assertSame('10.00', (string) $product->fresh()->stock);
        $this->assertSame('10.00', (string) $movement->quantity);
        $this->assertSame('10.00', (string) $movement->balance_after);
        $this->assertSame($business->getKey(), $movement->business_id);
        $this->assertSame('Stok awal', $movement->reason);
    }

    public function test_balance_accumulates_across_movements(): void
    {
        $business = Business::factory()->create();
        $product = Product::factory()->forBusiness($business)->create();
        $service = $this->service();

        DB::transaction(function () use ($service, $product): void {
            $service->record($product, '10', 'Masuk');
            $service->record($product, '-4', 'Keluar');
            $service->record($product, '1.5', 'Masuk lagi');
        });

        $this->assertSame('7.50', (string) $product->fresh()->stock);

        // Saldo pada baris terakhir harus sama dengan stok produk.
        $last = $product->movements()->orderByDesc('id')->first();
        $this->assertSame('7.50', (string) $last->balance_after);
    }

    public function test_stock_never_becomes_negative(): void
    {
        $business = Business::factory()->create();
        $product = Product::factory()->forBusiness($business)->create();
        $service = $this->service();

        DB::transaction(fn () => $service->record($product, '5', 'Masuk'));

        try {
            DB::transaction(fn () => $service->record($product, '-6', 'Keluar berlebihan'));
            $this->fail('Mutasi yang membuat stok negatif seharusnya ditolak.');
        } catch (InsufficientStockException $exception) {
            $this->assertStringContainsString('tidak cukup', $exception->getMessage());
        }

        // Transaksi dibatalkan: stok dan buku besar tetap utuh.
        $this->assertSame('5.00', (string) $product->fresh()->stock);
        $this->assertSame(1, $product->movements()->count());
    }

    public function test_a_zero_movement_is_rejected(): void
    {
        $business = Business::factory()->create();
        $product = Product::factory()->forBusiness($business)->create();

        $this->expectException(InvalidOperationException::class);

        DB::transaction(fn () => $this->service()->record($product, '0', 'Tidak ada perubahan'));
    }

    public function test_product_stock_always_matches_the_ledger_total(): void
    {
        $business = Business::factory()->create();
        $productA = Product::factory()->forBusiness($business)->create();
        $productB = Product::factory()->forBusiness($business)->create();
        $service = $this->service();

        DB::transaction(function () use ($service, $productA, $productB): void {
            $service->record($productA, '10', 'Masuk');
            $service->record($productA, '-3.25', 'Keluar');
            $service->record($productB, '7', 'Masuk');
        });

        foreach ([$productA, $productB] as $product) {
            $ledger = (float) $product->movements()->sum('quantity');

            $this->assertEqualsWithDelta(
                $ledger,
                (float) $product->fresh()->stock,
                0.0001,
                'Stok produk harus sama dengan total buku besar pergerakan.',
            );
        }
    }
}
