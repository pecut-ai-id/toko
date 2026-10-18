<?php

namespace App\Models;

use App\Contracts\Payable;
use App\Enums\SaleStatus;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\HasPayments;
use App\Support\Money;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'customer_id',
    'number',
    'sold_at',
    'status',
    'subtotal',
    'total',
    'notes',
    'cancelled_at',
    'cancelled_by',
])]
class Sale extends Model implements Payable
{
    /** @use HasFactory<SaleFactory> */
    use BelongsToBusiness, HasFactory, HasPayments;

    /**
     * `business_id` diisi dari konteks bisnis aktif, bukan dari input.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'completed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SaleStatus::class,
            'sold_at' => 'date',
            'cancelled_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Pergerakan stok yang dihasilkan dokumen ini (pengurangan + reversal).
     *
     * @return MorphMany<InventoryMovement, $this>
     */
    public function movements(): MorphMany
    {
        return $this->morphMany(InventoryMovement::class, 'source');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    // --- Payable -------------------------------------------------------

    public function documentNumber(): string
    {
        return $this->number;
    }

    public function totalAmount(): string
    {
        return number_format(Money::toFloat($this->total), 2, '.', '');
    }

    public function isOpen(): bool
    {
        return $this->status === $this->openStatus();
    }

    /**
     * Penjualan yang belum dibatalkan masih berjalan; di sinilah piutang
     * pelanggan bisa ada.
     */
    public function openStatus(): SaleStatus
    {
        return SaleStatus::Completed;
    }

    // --- Perhitungan turunan -------------------------------------------

    /**
     * Harga pokok penjualan: dari snapshot `unit_cost` item, bukan dari
     * harga pokok produk saat ini.
     */
    public function costTotal(): string
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        $total = 0.0;

        foreach ($items as $item) {
            $total += Money::toFloat($item->quantity) * Money::toFloat($item->unit_cost);
        }

        return number_format(round($total, 2), 2, '.', '');
    }

    public function marginTotal(): string
    {
        return number_format(
            round(Money::toFloat($this->totalAmount()) - Money::toFloat($this->costTotal()), 2),
            2,
            '.',
            ''
        );
    }

    public function totalQuantity(): string
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();

        $total = 0.0;

        foreach ($items as $item) {
            $total += Money::toFloat($item->quantity);
        }

        return number_format(round($total, 2), 2, '.', '');
    }

    // --- Aturan aksi ---------------------------------------------------

    public function canBeCancelled(): bool
    {
        // Pembatalan menolak dokumen yang masih punya pembayaran berlaku.
        // Pembayaran bisa di-void lebih dulu (D-026), jadi mengembalikan
        // dana tetap mungkin tanpa menghapus jejak apa pun.
        return $this->status === SaleStatus::Completed && ! $this->hasActivePayments();
    }
}
