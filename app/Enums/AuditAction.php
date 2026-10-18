<?php

namespace App\Enums;

/**
 * Jenis kejadian yang dicatat di jejak audit.
 *
 * Nilai string-nya ikut tersimpan di `audit_logs.action`, jadi jangan
 * diubah sembarangan: baris lama akan kehilangan artinya. Labelnya
 * dipakai untuk tampilan.
 */
enum AuditAction: string
{
    case PurchaseCreated = 'purchase.created';
    case PurchaseReceived = 'purchase.received';
    case PurchaseCancelled = 'purchase.cancelled';

    case SaleCreated = 'sale.created';
    case SaleCancelled = 'sale.cancelled';

    case PaymentRecorded = 'payment.recorded';
    case PaymentVoided = 'payment.voided';

    case StockAdjusted = 'stock.adjusted';

    case ProductCreated = 'product.created';
    case ProductUpdated = 'product.updated';
    case ProductDeactivated = 'product.deactivated';

    case SupplierCreated = 'supplier.created';
    case SupplierUpdated = 'supplier.updated';
    case SupplierDeactivated = 'supplier.deactivated';

    case CustomerCreated = 'customer.created';
    case CustomerUpdated = 'customer.updated';
    case CustomerDeactivated = 'customer.deactivated';

    public function label(): string
    {
        return match ($this) {
            self::PurchaseCreated => 'Pembelian dibuat',
            self::PurchaseReceived => 'Pembelian diterima',
            self::PurchaseCancelled => 'Pembelian dibatalkan',
            self::SaleCreated => 'Penjualan dibuat',
            self::SaleCancelled => 'Penjualan dibatalkan',
            self::PaymentRecorded => 'Pembayaran dicatat',
            self::PaymentVoided => 'Pembayaran dibatalkan',
            self::StockAdjusted => 'Stok disesuaikan',
            self::ProductCreated => 'Produk ditambahkan',
            self::ProductUpdated => 'Produk diubah',
            self::ProductDeactivated => 'Produk dinonaktifkan',
            self::SupplierCreated => 'Supplier ditambahkan',
            self::SupplierUpdated => 'Supplier diubah',
            self::SupplierDeactivated => 'Supplier dinonaktifkan',
            self::CustomerCreated => 'Pelanggan ditambahkan',
            self::CustomerUpdated => 'Pelanggan diubah',
            self::CustomerDeactivated => 'Pelanggan dinonaktifkan',
        };
    }

    /**
     * Pengelompokan untuk penyaring di halaman riwayat.
     */
    public function group(): string
    {
        return match ($this) {
            self::PurchaseCreated, self::PurchaseReceived, self::PurchaseCancelled => 'Pembelian',
            self::SaleCreated, self::SaleCancelled => 'Penjualan',
            self::PaymentRecorded, self::PaymentVoided => 'Pembayaran',
            self::StockAdjusted => 'Persediaan',
            default => 'Data master',
        };
    }

    /**
     * @return array<string, array<int, self>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::cases() as $case) {
            $groups[$case->group()][] = $case;
        }

        return $groups;
    }
}
