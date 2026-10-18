<?php

namespace Tests\Concerns;

use App\Models\Business;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;

trait CreatesPurchases
{
    /**
     * Buat draf pembelian lewat endpoint HTTP yang sebenarnya.
     *
     * @param  array<int, array{product: Product, quantity: int|string, unit_cost: int|string}>  $lines
     */
    protected function draftPurchase(
        User $user,
        Business $business,
        array $lines,
        string $purchasedAt = '2026-10-18',
    ): Purchase {
        $supplier = Supplier::factory()->forBusiness($business)->create();

        $items = array_map(fn (array $line): array => [
            'product_id' => $line['product']->getKey(),
            'quantity' => (string) $line['quantity'],
            'unit_cost' => (string) $line['unit_cost'],
        ], $lines);

        $this->actingAs($user)
            ->post(route('purchases.store'), [
                'supplier_id' => $supplier->getKey(),
                'purchased_at' => $purchasedAt,
                'items' => $items,
            ])
            ->assertRedirect();

        return Purchase::latest('id')->firstOrFail();
    }
}
