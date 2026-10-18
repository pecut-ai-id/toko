<?php

namespace Database\Factories;

use App\Enums\SaleStatus;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    /**
     * Penghitung sederhana agar nomor dokumen tidak bentrok antar test
     * tanpa bergantung pada data acak.
     */
    protected static int $sequence = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static::$sequence++;

        return [
            'business_id' => Business::factory(),
            'customer_id' => Customer::factory(),
            'number' => 'PJ-'.now()->format('Ymd').'-'.str_pad((string) static::$sequence, 4, '0', STR_PAD_LEFT),
            'sold_at' => now()->toDateString(),
            'status' => SaleStatus::Completed,
            'subtotal' => '0.00',
            'total' => '0.00',
            'notes' => null,
        ];
    }

    /**
     * Penjualan milik bisnis tertentu, dengan pelanggan pada bisnis yang
     * sama.
     */
    public function forBusiness(Business $business): static
    {
        return $this->state(fn (): array => [
            'business_id' => $business->getKey(),
            'customer_id' => Customer::factory()->forBusiness($business),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => SaleStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
