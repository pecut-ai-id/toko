<?php

namespace Database\Factories;

use App\Enums\PurchaseStatus;
use App\Models\Business;
use App\Models\Purchase;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    protected $model = Purchase::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'supplier_id' => Supplier::factory(),
            'number' => 'PB-'.now()->format('Ymd').'-'.fake()->unique()->numerify('####'),
            'purchased_at' => now()->toDateString(),
            'status' => PurchaseStatus::Draft,
            'subtotal' => '0.00',
            'total' => '0.00',
            'notes' => null,
        ];
    }

    /**
     * Draf milik bisnis tertentu, dengan supplier pada bisnis yang sama.
     */
    public function forBusiness(Business $business): static
    {
        return $this->state(fn (): array => [
            'business_id' => $business->getKey(),
            'supplier_id' => Supplier::factory()->forBusiness($business),
        ]);
    }
}
