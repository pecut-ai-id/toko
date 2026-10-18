<?php

namespace Tests\Concerns;

use App\Models\Business;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;

trait CreatesSales
{
    /**
     * Buat penjualan lewat endpoint HTTP yang sebenarnya.
     *
     * @param  array<int, array{product: Product, quantity: int|string, unit_price: int|string}>  $lines
     * @param  array<string, mixed>  $extra
     */
    protected function createSale(
        User $user,
        Business $business,
        array $lines,
        string $soldAt = '2026-10-18',
        array $extra = [],
    ): Sale {
        $customer = Customer::factory()->forBusiness($business)->create();

        $items = array_map(fn (array $line): array => [
            'product_id' => $line['product']->getKey(),
            'quantity' => (string) $line['quantity'],
            'unit_price' => (string) $line['unit_price'],
        ], $lines);

        $this->actingAs($user)
            ->post(route('sales.store'), array_merge([
                'customer_id' => $customer->getKey(),
                'sold_at' => $soldAt,
                'items' => $items,
            ], $extra))
            ->assertRedirect();

        return Sale::latest('id')->firstOrFail();
    }
}
