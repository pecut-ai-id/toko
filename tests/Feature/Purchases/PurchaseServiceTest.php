<?php

namespace Tests\Feature\Purchases;

use App\Models\Business;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\PurchaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_normalises_indonesian_number_format_before_storing(): void
    {
        $business = Business::factory()->create();
        $supplier = Supplier::factory()->forBusiness($business)->create();
        $product = Product::factory()->forBusiness($business)->create();

        $purchase = app(PurchaseService::class)->create([
            'supplier_id' => $supplier->getKey(),
            'purchased_at' => '2026-10-18',
            'items' => [
                ['product_id' => $product->getKey(), 'quantity' => '10', 'unit_cost' => '50.000'],
                ['product_id' => $product->getKey(), 'quantity' => '2', 'unit_cost' => '1.275.000,50'],
            ],
        ], $business);

        $items = $purchase->items()->orderBy('id')->get();

        $this->assertSame('50000.00', (string) $items[0]->unit_cost);
        $this->assertSame('500000.00', (string) $items[0]->subtotal);
        $this->assertSame('1275000.50', (string) $items[1]->unit_cost);
        $this->assertSame('2550001.00', (string) $items[1]->subtotal);
        $this->assertSame('3050001.00', (string) $purchase->fresh()->total);
    }

    public function test_it_derives_the_document_number_from_the_business_and_date(): void
    {
        $business = Business::factory()->create();
        $supplier = Supplier::factory()->forBusiness($business)->create();
        $product = Product::factory()->forBusiness($business)->create();

        $service = app(PurchaseService::class);

        $make = fn (string $date) => $service->create([
            'supplier_id' => $supplier->getKey(),
            'purchased_at' => $date,
            'items' => [
                ['product_id' => $product->getKey(), 'quantity' => '1', 'unit_cost' => '1000'],
            ],
        ], $business);

        $first = $make('2026-10-18');
        $second = $make('2026-10-18');

        $this->assertSame('PB-20261018-0001', $first->number);
        $this->assertSame('PB-20261018-0002', $second->number);
    }
}
