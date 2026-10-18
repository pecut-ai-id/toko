<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\SaleStatus;
use App\Exceptions\InvalidOperationException;
use App\Models\Business;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Alur penjualan.
 *
 * Berbeda dari pembelian, penjualan langsung memengaruhi stok: tidak ada
 * fase draf. Header, item, pengurangan stok, dan pembayaran awal semuanya
 * disimpan dalam satu database transaction, sehingga penjualan yang gagal
 * (mis. stok kurang atau pembayaran melebihi total) tidak meninggalkan
 * perubahan sebagian (requirements.md Bagian 4 dan 6).
 */
class SaleService
{
    public function __construct(
        protected InventoryService $inventory,
        protected PaymentService $payments,
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  array{customer_id: int, sold_at: string, notes?: string|null, paid_amount?: string|null, paid_at?: string|null, payment_method?: string|null, payment_reference?: string|null, items: array<int, array{product_id: int, quantity: string, unit_price: string}>}  $data
     */
    public function create(array $data, Business $business, User $user): Sale
    {
        return DB::transaction(function () use ($data, $business, $user): Sale {
            // Kunci baris bisnis agar penomoran dokumen tidak bentrok
            // saat dua permintaan masuk bersamaan.
            $lockedBusiness = Business::query()
                ->whereKey($business->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $sale = new Sale([
                'customer_id' => $data['customer_id'],
                'sold_at' => $data['sold_at'],
                'notes' => $data['notes'] ?? null,
                'status' => SaleStatus::Completed,
            ]);

            $sale->business_id = $lockedBusiness->getKey();
            $sale->number = $this->nextNumber($lockedBusiness, $data['sold_at']);
            $sale->subtotal = '0.00';
            $sale->total = '0.00';
            $sale->save();

            $subtotal = '0.00';

            foreach ($data['items'] as $item) {
                $product = Product::query()
                    ->forBusiness($lockedBusiness)
                    ->whereKey($item['product_id'])
                    ->firstOrFail();

                // Server menghitung ulang semua angka; total dari browser
                // tidak pernah dipercaya (requirements.md Bagian 4).
                $quantity = Money::parseToDecimal($item['quantity']);
                $unitPrice = Money::parseToDecimal($item['unit_price']);
                $lineTotal = Money::multiply($unitPrice, $quantity);

                $sale->items()->create([
                    'product_id' => $product->getKey(),
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    // Snapshot harga pokok saat transaksi; tidak boleh
                    // berasal dari input pengguna.
                    'unit_cost' => $product->cost_price,
                    'subtotal' => $lineTotal,
                ]);

                $subtotal = Money::sum([$subtotal, $lineTotal]);
            }

            $sale->subtotal = $subtotal;
            $sale->total = $subtotal;
            $sale->save();

            // Pengurangan stok: ditolak bila stok tidak cukup, dan
            // kegagalannya membatalkan seluruh penjualan.
            $sale->load('items.product');

            foreach ($sale->items as $item) {
                $this->inventory->record(
                    product: $item->product,
                    quantity: number_format(-1 * Money::toFloat($item->quantity), 2, '.', ''),
                    reason: 'Penjualan '.$sale->number,
                    source: $sale,
                    user: $user,
                );
            }

            $this->audit->log(
                action: AuditAction::SaleCreated,
                summary: 'Penjualan '.$sale->number.' dibuat; stok berkurang.',
                subject: $sale,
                changes: ['total' => [null, $sale->total]],
                user: $user,
            );

            $this->recordInitialPayment($sale, $data, $user);

            return $sale;
        });
    }

    /**
     * Batalkan penjualan. Stok dikembalikan lewat pergerakan reversal
     * yang tercatat (requirements.md Bagian 6 poin 4).
     *
     * Penjualan yang masih punya pembayaran berlaku belum bisa dibatalkan
     * karena pengembalian dana belum ada di cakupan TokoFlow. Pembayaran
     * bisa di-void lebih dulu (D-026), sehingga koreksi tetap mungkin tanpa
     * menghapus jejak apa pun.
     */
    public function cancel(Sale $sale, User $user): Sale
    {
        return DB::transaction(function () use ($sale, $user): Sale {
            $locked = Sale::query()
                ->whereKey($sale->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status->isCancelled()) {
                throw new InvalidOperationException(
                    'Penjualan '.$locked->number.' sudah dibatalkan.'
                );
            }

            $beforeStatus = $locked->status;

            if ($locked->hasActivePayments()) {
                throw new InvalidOperationException(sprintf(
                    'Penjualan %s sudah memiliki pembayaran sehingga tidak dapat dibatalkan. '
                    .'Batalkan pembayarannya lebih dulu.',
                    $locked->number,
                ));
            }

            $locked->load('items.product');

            foreach ($locked->items as $item) {
                $this->inventory->record(
                    product: $item->product,
                    quantity: number_format(Money::toFloat($item->quantity), 2, '.', ''),
                    reason: 'Pembatalan penjualan '.$locked->number,
                    source: $locked,
                    user: $user,
                );
            }

            $locked->status = SaleStatus::Cancelled;
            $locked->cancelled_at = now();
            $locked->cancelled_by = $user->getKey();
            $locked->save();

            $this->audit->log(
                action: AuditAction::SaleCancelled,
                summary: 'Penjualan '.$locked->number.' dibatalkan; stok dikembalikan.',
                subject: $locked,
                changes: ['status' => [$beforeStatus->label(), $locked->status->label()]],
                user: $user,
            );

            return $locked;
        });
    }

    /**
     * Pembayaran awal pada saat penjualan dibuat. Kosong atau nol berarti
     * penjualan kredit penuh.
     *
     * @param  array<string, mixed>  $data
     */
    protected function recordInitialPayment(Sale $sale, array $data, User $user): void
    {
        $amount = Money::parseToDecimal($data['paid_amount'] ?? null);

        if ($amount === null || Money::toFloat($amount) <= 0) {
            return;
        }

        $this->payments->record($sale, [
            'amount' => $amount,
            'paid_at' => $data['paid_at'] ?? $sale->sold_at->toDateString(),
            'method' => $data['payment_method'] ?? null,
            'reference' => $data['payment_reference'] ?? null,
        ], $user);
    }

    /**
     * Nomor dokumen unik per bisnis: PJ-YYYYMMDD-0001.
     */
    protected function nextNumber(Business $business, string $soldAt): string
    {
        $prefix = 'PJ-'.date('Ymd', strtotime($soldAt)).'-';

        $used = Sale::query()
            ->forBusiness($business)
            ->where('number', 'like', $prefix.'%')
            ->count();

        return $prefix.str_pad((string) ($used + 1), 4, '0', STR_PAD_LEFT);
    }
}
