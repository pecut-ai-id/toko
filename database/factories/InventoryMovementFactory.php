<?php

namespace Database\Factories;

use App\Models\Business;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Hati-hati: mutasi stok produksi selalu dibuat lewat InventoryService.
 * Factory ini hanya untuk menyiapkan data uji.
 *
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    protected $model = InventoryMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'product_id' => Product::factory(),
            'quantity' => 10,
            'balance_after' => 10,
            'reason' => 'Data uji',
            'occurred_at' => now(),
        ];
    }

    public function forProduct(Product $product): static
    {
        return $this->state(fn (): array => [
            'business_id' => $product->business_id,
            'product_id' => $product->getKey(),
        ]);
    }
}
