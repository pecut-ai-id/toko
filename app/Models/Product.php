<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use App\Models\Concerns\HasActiveFlag;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['sku', 'name', 'unit', 'sell_price', 'cost_price', 'is_active'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use BelongsToBusiness, HasActiveFlag, HasFactory;

    /**
     * `business_id` dan `stock` sengaja tidak fillable:
     * - `business_id` selalu diisi dari konteks bisnis aktif di server.
     * - `stock` hanya boleh berubah lewat InventoryService.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stock' => 'decimal:2',
            'sell_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * @return HasMany<PurchaseItem, $this>
     */
    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * Produk yang sudah dipakai transaksi tidak boleh dihapus destruktif.
     */
    public function isUsedInTransactions(): bool
    {
        return $this->purchaseItems()->exists() || $this->saleItems()->exists();
    }
}
