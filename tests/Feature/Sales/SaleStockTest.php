<?php

namespace Tests\Feature\Sales;

use App\Models\Customer;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBusinessUsers;
use Tests\Concerns\CreatesSales;
use Tests\Concerns\ManagesStock;
use Tests\TestCase;

class SaleStockTest extends TestCase
{
    use CreatesBusinessUsers, CreatesSales, ManagesStock, RefreshDatabase;

    public function test_creating_a_sale_reduces_stock_and_writes_a_traced_movement(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 20, $user);

        $sale = $this->createSale($user, $business, [
            ['product' => $product, 'quantity' => 3, 'unit_price' => '85.000'],
        ]);

        $this->assertSame('17.00', (string) $product->fresh()->stock);

        $movement = InventoryMovement::query()
            ->where('product_id', $product->getKey())
            ->where('reason', 'like', 'Penjualan %')
            ->sole();

        $this->assertSame('-3.00', (string) $movement->quantity);
        $this->assertSame('17.00', (string) $movement->balance_after);
        $this->assertSame('Penjualan '.$sale->number, $movement->reason);
        $this->assertSame(Sale::class, $movement->source_type);
        $this->assertSame($sale->getKey(), $movement->source_id);
        $this->assertSame($business->getKey(), $movement->business_id);
    }

    public function test_stock_is_reduced_for_every_item_in_one_transaction(): void
    {
        [$user, $business] = $this->businessWithUser();
        $productA = $this->setStock(Product::factory()->forBusiness($business)->create(), 20, $user);
        $productB = $this->setStock(Product::factory()->forBusiness($business)->create(), 20, $user);

        $this->createSale($user, $business, [
            ['product' => $productA, 'quantity' => 5, 'unit_price' => '10.000'],
            ['product' => $productB, 'quantity' => '2,5', 'unit_price' => '4.000'],
        ]);

        $this->assertSame('15.00', (string) $productA->fresh()->stock);
        $this->assertSame('17.50', (string) $productB->fresh()->stock);
    }

    public function test_a_sale_beyond_available_stock_is_rejected_and_changes_nothing(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 5, $user);

        $movementsBefore = InventoryMovement::query()->count();

        $this->actingAs($user)
            ->post(route('sales.store'), [
                'customer_id' => Customer::factory()->forBusiness($business)->create()->getKey(),
                'sold_at' => '2026-10-18',
                'items' => [
                    ['product_id' => $product->getKey(), 'quantity' => '10', 'unit_price' => '10.000'],
                ],
            ])
            ->assertSessionHasErrors('sale');

        // Header penjualan sudah dibuat di dalam transaksi, tapi gagal
        // pada pengurangan stok: seluruh transaksi harus di-rollback.
        $this->assertSame('5.00', (string) $product->fresh()->stock);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertSame($movementsBefore, InventoryMovement::query()->count());
    }

    public function test_a_failure_on_the_second_item_rolls_back_the_first_item_stock_change(): void
    {
        [$user, $business] = $this->businessWithUser();
        $productA = $this->setStock(Product::factory()->forBusiness($business)->create(), 20, $user);
        $productB = $this->setStock(Product::factory()->forBusiness($business)->create(), 2, $user);

        $this->actingAs($user)
            ->post(route('sales.store'), [
                'customer_id' => Customer::factory()->forBusiness($business)->create()->getKey(),
                'sold_at' => '2026-10-18',
                'items' => [
                    ['product_id' => $productA->getKey(), 'quantity' => '5', 'unit_price' => '10.000'],
                    ['product_id' => $productB->getKey(), 'quantity' => '9', 'unit_price' => '10.000'],
                ],
            ])
            ->assertSessionHasErrors('sale');

        $this->assertSame('20.00', (string) $productA->fresh()->stock, 'Stok produk pertama harus kembali seperti semula.');
        $this->assertSame('2.00', (string) $productB->fresh()->stock);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_hpp_and_margin_are_computed_from_the_cost_snapshot(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = $this->setStock(
            Product::factory()->forBusiness($business)->create(['cost_price' => 60000]),
            50,
            $user,
        );

        $sale = $this->createSale($user, $business, [
            ['product' => $product, 'quantity' => 15, 'unit_price' => '85.000'],
        ]);

        $sale->load('items');

        $this->assertSame('1275000.00', $sale->totalAmount());
        $this->assertSame('900000.00', $sale->costTotal());
        $this->assertSame('375000.00', $sale->marginTotal());
        $this->assertSame('15.00', $sale->totalQuantity());
    }

    public function test_stock_can_be_sold_down_to_exactly_zero_but_not_below(): void
    {
        [$user, $business] = $this->businessWithUser();
        $product = $this->setStock(Product::factory()->forBusiness($business)->create(), 4, $user);

        $this->createSale($user, $business, [
            ['product' => $product, 'quantity' => 4, 'unit_price' => '10.000'],
        ]);

        $this->assertSame('0.00', (string) $product->fresh()->stock);

        $this->actingAs($user)
            ->post(route('sales.store'), [
                'customer_id' => Customer::factory()->forBusiness($business)->create()->getKey(),
                'sold_at' => '2026-10-18',
                'items' => [
                    ['product_id' => $product->getKey(), 'quantity' => '1', 'unit_price' => '10.000'],
                ],
            ])
            ->assertSessionHasErrors('sale');

        $this->assertSame('0.00', (string) $product->fresh()->stock);
        $this->assertDatabaseCount('sales', 1);
    }
}
