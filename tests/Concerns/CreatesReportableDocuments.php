<?php

namespace Tests\Concerns;

use App\Enums\PurchaseStatus;
use App\Enums\SaleStatus;
use App\Models\Business;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Supplier;
use App\Support\Money;
use InvalidArgumentException;

/**
 * Dokumen transaksi yang sudah "jadi" (penjualan selesai, pembelian
 * diterima) untuk menguji laporan.
 *
 * Dipakai sengaja alih-alih endpoint HTTP: laporan hanya membaca tabel,
 * jadi test laporan sebaiknya mengunci **angka** yang diuji, bukan
 * bergantung pada jalur input penjualan/pembelian.
 *
 * Nominal ditulis dengan format Indonesia seperti yang diketik pengguna
 * ("85.000") dan dibaca lewat `Money::parseToDecimal()` — jalur yang sama
 * dengan form asli — supaya tidak ada dua tafsir angka di dalam test.
 */
trait CreatesReportableDocuments
{
    /**
     * @param  array<int, array{product: Product, quantity: int|string, unit_price: int|string, unit_cost: int|string}>  $lines
     */
    protected function completedSale(
        Business $business,
        string $soldAt,
        array $lines,
        ?Customer $customer = null,
    ): Sale {
        $customer ??= Customer::factory()->forBusiness($business)->create();

        $total = 0.0;

        foreach ($lines as $line) {
            $total += Money::toFloat(Money::multiply(
                $this->decimal($line['unit_price']),
                $this->decimal($line['quantity']),
            ));
        }

        $sale = Sale::factory()->forBusiness($business)->create([
            'customer_id' => $customer->getKey(),
            'sold_at' => $soldAt,
            'status' => SaleStatus::Completed,
            'subtotal' => number_format(round($total, 2), 2, '.', ''),
            'total' => number_format(round($total, 2), 2, '.', ''),
        ]);

        foreach ($lines as $line) {
            SaleItem::factory()->create([
                'sale_id' => $sale->getKey(),
                'product_id' => $line['product']->getKey(),
                'quantity' => $this->decimal($line['quantity']),
                'unit_price' => $this->decimal($line['unit_price']),
                'unit_cost' => $this->decimal($line['unit_cost']),
                'subtotal' => Money::multiply(
                    $this->decimal($line['unit_price']),
                    $this->decimal($line['quantity']),
                ),
            ]);
        }

        return $sale;
    }

    /**
     * @param  array<int, array{product: Product, quantity: int|string, unit_cost: int|string}>  $lines
     */
    protected function receivedPurchase(
        Business $business,
        string $purchasedAt,
        array $lines,
        ?Supplier $supplier = null,
    ): Purchase {
        $supplier ??= Supplier::factory()->forBusiness($business)->create();

        $total = 0.0;

        foreach ($lines as $line) {
            $total += Money::toFloat(Money::multiply(
                $this->decimal($line['unit_cost']),
                $this->decimal($line['quantity']),
            ));
        }

        $total = number_format(round($total, 2), 2, '.', '');

        $purchase = Purchase::factory()->forBusiness($business)->create([
            'supplier_id' => $supplier->getKey(),
            'purchased_at' => $purchasedAt,
            'status' => PurchaseStatus::Received,
            'subtotal' => $total,
            'total' => $total,
            'received_at' => $purchasedAt.' 08:00:00',
        ]);

        foreach ($lines as $line) {
            PurchaseItem::factory()->create([
                'purchase_id' => $purchase->getKey(),
                'product_id' => $line['product']->getKey(),
                'quantity' => $this->decimal($line['quantity']),
                'unit_cost' => $this->decimal($line['unit_cost']),
                'subtotal' => Money::multiply(
                    $this->decimal($line['unit_cost']),
                    $this->decimal($line['quantity']),
                ),
            ]);
        }

        return $purchase;
    }

    /**
     * Nilai uji bertipe decimal polos; format Indonesia diterjemahkan
     * lewat parser yang sama dengan form.
     */
    private function decimal(string|int|float|null $value): string
    {
        $parsed = Money::parseToDecimal($value);

        if ($parsed === null) {
            throw new InvalidArgumentException('Nominal uji tidak bisa dibaca: '.(string) $value);
        }

        return $parsed;
    }
}
