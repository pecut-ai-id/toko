<?php

namespace Database\Factories;

use App\Contracts\Payable;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * Pembayaran selalu menempel pada sebuah dokumen, jadi factory ini harus
 * dipakai bersama `forSale()` / `forPurchase()`. Dalam kode aplikasi,
 * pembayaran dibuat lewat PaymentService, bukan lewat factory.
 *
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'amount' => 100000,
            'paid_at' => now()->toDateString(),
            'method' => 'Tunai',
            'reference' => null,
            'notes' => null,
        ];
    }

    /**
     * `business_id` dan relasi `payable` diisi langsung (bukan mass
     * assignment) karena sengaja tidak fillable.
     */
    public function forPayable(Model&Payable $payable): static
    {
        return $this->afterMaking(function (Payment $payment) use ($payable): void {
            $payment->business_id = $payable->getAttribute('business_id');
            $payment->payable()->associate($payable);
        });
    }

    public function forSale(Sale $sale): static
    {
        return $this->forPayable($sale);
    }

    public function forPurchase(Purchase $purchase): static
    {
        return $this->forPayable($purchase);
    }

    /**
     * Pembayaran yang sudah dibatalkan: barisnya tetap ada, hanya tidak
     * lagi dihitung sebagai pelunasan (D-026).
     */
    public function voided(?User $user = null, string $reason = 'Salah input'): static
    {
        return $this->state(fn (): array => [
            'voided_at' => now(),
            'voided_by' => $user?->getKey(),
            'void_reason' => $reason,
        ]);
    }
}
