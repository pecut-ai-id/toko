<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidOperationException;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Satu-satunya pintu masuk perubahan stok.
 *
 * Aturan yang dijaga:
 * - Setiap perubahan stok menulis satu baris `inventory_movements`
 *   (tidak ada angka stok yang berubah tanpa jejak).
 * - Baris produk dikunci dengan `lockForUpdate()` sehingga operasi
 *   bersamaan tidak menghasilkan stok negatif atau saldo yang salah.
 * - Stok tidak boleh menjadi negatif.
 */
class InventoryService
{
    /**
     * Catat satu mutasi stok.
     *
     * HARUS dipanggil di dalam database transaction milik pemanggil agar
     * perubahan stok dan dokumen sumbernya commit bersama-sama.
     *
     * @param  string|float|int  $quantity  bertanda: positif masuk, negatif keluar
     * @param  Model|null  $source  dokumen sumber (mis. Purchase, Sale)
     */
    public function record(
        Product $product,
        string|float|int $quantity,
        string $reason,
        ?Model $source = null,
        ?User $user = null,
        ?CarbonInterface $occurredAt = null,
    ): InventoryMovement {
        $delta = round(Money::toFloat($quantity), 2);

        if ($delta === 0.0) {
            throw new InvalidOperationException('Mutasi stok tidak boleh bernilai nol.');
        }

        // Kunci baris produk sampai transaksi selesai.
        $locked = Product::query()
            ->whereKey($product->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        $current = Money::toFloat($locked->stock);
        $balance = round($current + $delta, 2);

        if ($balance < 0) {
            throw InsufficientStockException::forProduct($locked->name, $current, abs($delta));
        }

        $locked->stock = number_format($balance, 2, '.', '');
        $locked->save();

        $movement = new InventoryMovement([
            'product_id' => $locked->getKey(),
            'quantity' => number_format($delta, 2, '.', ''),
            'balance_after' => number_format($balance, 2, '.', ''),
            'reason' => $reason,
            'user_id' => $user?->getKey(),
            'occurred_at' => $occurredAt ?? now(),
        ]);

        $movement->business_id = $locked->business_id;

        if ($source !== null) {
            $movement->source()->associate($source);
        }

        $movement->save();

        return $movement;
    }
}
