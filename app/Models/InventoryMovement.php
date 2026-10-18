<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use App\Support\Money;
use Database\Factories\InventoryMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'product_id',
    'quantity',
    'balance_after',
    'reason',
    'user_id',
    'occurred_at',
])]
class InventoryMovement extends Model
{
    /** @use HasFactory<InventoryMovementFactory> */
    use BelongsToBusiness, HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Dokumen sumber mutasi (Purchase untuk penerimaan, Sale untuk
     * pengurangan penjualan dan reversalnya).
     *
     * @return MorphTo<Model, $this>
     */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeForProduct(Builder $query, Product|int $product): void
    {
        $id = $product instanceof Product ? $product->getKey() : $product;

        $query->where('inventory_movements.product_id', $id);
    }

    public function isIncoming(): bool
    {
        return Money::toFloat($this->quantity) > 0;
    }
}
