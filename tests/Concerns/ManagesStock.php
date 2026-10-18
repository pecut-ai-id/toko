<?php

namespace Tests\Concerns;

use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Support\Facades\DB;

/**
 * Stok tidak fillable (D-013): satu-satunya cara mengubahnya adalah
 * InventoryService, termasuk di dalam test. Trait ini menyediakan jalan
 * pintas yang tetap memakai jalur resmi tersebut.
 */
trait ManagesStock
{
    /**
     * Beri stok awal lewat InventoryService, lalu kembalikan produk yang
     * sudah disegarkan.
     */
    protected function setStock(
        Product $product,
        string|float|int $quantity,
        ?User $user = null,
        string $reason = 'Stok awal',
    ): Product {
        DB::transaction(function () use ($product, $quantity, $user, $reason): void {
            app(InventoryService::class)->record($product, $quantity, $reason, null, $user);
        });

        return $product->fresh();
    }
}
