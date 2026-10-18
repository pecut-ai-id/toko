<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\PurchaseStatus;
use App\Exceptions\InvalidOperationException;
use App\Models\Business;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * Alur pembelian: draf -> penerimaan dikonfirmasi -> stok bertambah.
 *
 * Semua langkah multi-tabel dibungkus satu database transaction sehingga
 * header, item, dan pergerakan stok tersimpan bersama atau tidak sama
 * sekali (requirements.md Bagian 6 poin 1).
 */
class PurchaseService
{
    public function __construct(
        protected InventoryService $inventory,
        protected AuditLogger $audit,
    ) {}

    /**
     * @param  array{supplier_id: int, purchased_at: string, notes?: string|null, items: array<int, array{product_id: int, quantity: string, unit_cost: string}>}  $data
     */
    public function create(array $data, Business $business): Purchase
    {
        return DB::transaction(function () use ($data, $business): Purchase {
            // Kunci baris bisnis agar penomoran dokumen tidak bentrok
            // saat dua permintaan masuk bersamaan.
            $lockedBusiness = Business::query()
                ->whereKey($business->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $purchase = new Purchase([
                'supplier_id' => $data['supplier_id'],
                'purchased_at' => $data['purchased_at'],
                'notes' => $data['notes'] ?? null,
                'status' => PurchaseStatus::Draft,
            ]);

            $purchase->business_id = $lockedBusiness->getKey();
            $purchase->number = $this->nextNumber($lockedBusiness, $data['purchased_at']);
            $purchase->subtotal = '0.00';
            $purchase->total = '0.00';
            $purchase->save();

            $subtotal = '0.00';

            foreach ($data['items'] as $item) {
                // Normalkan dulu supaya subtotal dan nilai tersimpan
                // dihitung dari angka yang sama, apa pun format inputnya.
                $quantity = Money::parseToDecimal($item['quantity']);
                $unitCost = Money::parseToDecimal($item['unit_cost']);
                $lineTotal = Money::multiply($unitCost, $quantity);

                $purchase->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'subtotal' => $lineTotal,
                ]);

                $subtotal = Money::sum([$subtotal, $lineTotal]);
            }

            $purchase->subtotal = $subtotal;
            $purchase->total = $subtotal;
            $purchase->save();

            $this->audit->log(
                action: AuditAction::PurchaseCreated,
                summary: 'Pembelian '.$purchase->number.' dibuat sebagai draf.',
                subject: $purchase,
                changes: ['total' => [null, $purchase->total]],
            );

            return $purchase;
        });
    }

    /**
     * Konfirmasi penerimaan: stok bertambah dan pergerakan stok tercatat.
     */
    public function receive(Purchase $purchase, User $user): Purchase
    {
        return DB::transaction(function () use ($purchase, $user): Purchase {
            $locked = Purchase::query()
                ->whereKey($purchase->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $beforeStatus = $locked->status;

            if (! $locked->canBeReceived()) {
                throw new InvalidOperationException(sprintf(
                    'Pembelian %s berstatus %s dan tidak dapat diterima.',
                    $locked->number,
                    $locked->status->label(),
                ));
            }

            $locked->load('items.product');

            foreach ($locked->items as $item) {
                $this->inventory->record(
                    product: $item->product,
                    quantity: $item->quantity,
                    reason: 'Penerimaan pembelian '.$locked->number,
                    source: $locked,
                    user: $user,
                );
            }

            $locked->status = PurchaseStatus::Received;
            $locked->received_at = now();
            $locked->received_by = $user->getKey();
            $locked->save();

            // Status sengaja disimpan sebelum harga pokok diselaraskan,
            // supaya pembelian ini sudah terhitung sebagai pembelian
            // terakhir yang berlaku.
            foreach ($locked->items as $item) {
                $this->syncDefaultCostPrice($item->product_id);
            }

            $this->audit->log(
                action: AuditAction::PurchaseReceived,
                summary: 'Pembelian '.$locked->number.' diterima; stok bertambah.',
                subject: $locked,
                changes: ['status' => [$beforeStatus->label(), $locked->status->label()]],
                user: $user,
            );

            return $locked;
        });
    }

    /**
     * Batalkan pembelian. Bila sudah diterima, stok dikembalikan lewat
     * pergerakan reversal yang tercatat (requirements.md Bagian 4).
     */
    public function cancel(Purchase $purchase, User $user): Purchase
    {
        return DB::transaction(function () use ($purchase, $user): Purchase {
            $locked = Purchase::query()
                ->whereKey($purchase->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status->isCancelled()) {
                throw new InvalidOperationException(
                    'Pembelian '.$locked->number.' sudah dibatalkan.'
                );
            }

            // Utang yang sudah dibayar tidak bisa dibatalkan begitu saja:
            // dananya ada di supplier. Pembayaran di-void lebih dulu (D-026),
            // lalu pembeliannya dibatalkan, sehingga tidak ada uang yang
            // menggantung tanpa jejak.
            if ($locked->hasActivePayments()) {
                throw new InvalidOperationException(
                    'Pembelian '.$locked->number.' sudah menerima pembayaran. '
                    .'Batalkan pembayarannya lebih dulu sebelum membatalkan pembelian ini.'
                );
            }

            $wasReceived = $locked->status->isReceived();
            $beforeStatus = $locked->status;

            $locked->status = PurchaseStatus::Cancelled;
            $locked->cancelled_at = now();
            $locked->cancelled_by = $user->getKey();
            $locked->save();

            if ($wasReceived) {
                $locked->load('items.product');

                foreach ($locked->items as $item) {
                    $reversal = number_format(-1 * Money::toFloat($item->quantity), 2, '.', '');

                    $this->inventory->record(
                        product: $item->product,
                        quantity: $reversal,
                        reason: 'Pembatalan pembelian '.$locked->number,
                        source: $locked,
                        user: $user,
                    );
                }

                // Pembelian ini bukan lagi pembelian terakhir yang berlaku,
                // jadi harga pokok default bisa kembali ke harga sebelumnya.
                foreach ($locked->items as $item) {
                    $this->syncDefaultCostPrice($item->product_id);
                }
            }

            $this->audit->log(
                action: AuditAction::PurchaseCancelled,
                summary: 'Pembelian '.$locked->number.' dibatalkan'
                    .($wasReceived ? '; stok dikembalikan lewat pergerakan reversal.' : '.'),
                subject: $locked,
                changes: ['status' => [$beforeStatus->label(), $locked->status->label()]],
                user: $user,
            );

            return $locked;
        });
    }

    /**
     * Selaraskan harga pokok default produk dengan pembelian terakhir yang
     * masih berlaku (D-027).
     *
     * Dipakai saat penerimaan dan saat pembatalan, supaya harga pokok tidak
     * tertinggal di harga pembelian yang sudah dibatalkan. Hanya kolom
     * `cost_price` yang disentuh lewat query builder: nilai `stock` tidak
     * boleh tertimpa model yang sudah basi.
     *
     * Bila tidak ada lagi pembelian yang berlaku, nilai terakhir dibiarkan
     * apa adanya — lebih baik daripada menebak angka baru.
     */
    protected function syncDefaultCostPrice(int $productId): void
    {
        $latest = PurchaseItem::query()
            ->where('product_id', $productId)
            ->whereHas('purchase', fn ($query) => $query->where('status', PurchaseStatus::Received))
            ->orderByDesc('id')
            ->first();

        if ($latest === null) {
            return;
        }

        Product::query()
            ->whereKey($productId)
            ->update(['cost_price' => $latest->unit_cost]);
    }

    /**
     * Nomor dokumen unik per bisnis: PB-YYYYMMDD-0001.
     */
    protected function nextNumber(Business $business, string $purchasedAt): string
    {
        $prefix = 'PB-'.date('Ymd', strtotime($purchasedAt)).'-';

        $used = Purchase::query()
            ->forBusiness($business)
            ->where('number', 'like', $prefix.'%')
            ->count();

        return $prefix.str_pad((string) ($used + 1), 4, '0', STR_PAD_LEFT);
    }
}
