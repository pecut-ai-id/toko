<?php

namespace App\Models;

use App\Contracts\Payable;
use App\Enums\PurchaseStatus;
use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\HasPayments;
use App\Support\Money;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[Fillable([
    'supplier_id',
    'number',
    'purchased_at',
    'status',
    'subtotal',
    'total',
    'notes',
    'received_at',
    'received_by',
    'cancelled_at',
    'cancelled_by',
])]
class Purchase extends Model implements Payable
{
    /** @use HasFactory<PurchaseFactory> */
    use BelongsToBusiness, HasFactory, HasPayments;

    /**
     * `business_id` diisi dari konteks bisnis aktif, bukan dari input.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PurchaseStatus::class,
            'purchased_at' => 'date',
            'received_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return HasMany<PurchaseItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * Pergerakan stok yang dihasilkan dokumen ini (penerimaan + reversal).
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
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function canBeReceived(): bool
    {
        return $this->status === PurchaseStatus::Draft;
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

    /**
     * Utang supplier baru ada setelah penerimaan dikonfirmasi: draf belum
     * mengubah stok maupun menimbulkan kewajiban.
     */
    public function isOpen(): bool
    {
        return $this->status === $this->openStatus();
    }

    /**
     * Status yang berarti utang supplier ini sudah nyata.
     */
    public function openStatus(): PurchaseStatus
    {
        return PurchaseStatus::Received;
    }

    // --- Aturan aksi ---------------------------------------------------

    public function canBeCancelled(): bool
    {
        return $this->status !== PurchaseStatus::Cancelled && ! $this->hasActivePayments();
    }
}
