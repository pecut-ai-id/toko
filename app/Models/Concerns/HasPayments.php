<?php

namespace App\Models\Concerns;

use App\Enums\PurchaseStatus;
use App\Enums\SaleStatus;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Dokumen yang punya saldo bisa dibayar (piutang penjualan, utang
 * pembelian).
 *
 * Saldo **tidak pernah disimpan**: selalu dihitung dari total dikurangi
 * pembayaran yang belum di-void. Ini menghilangkan risiko nilai basi
 * (D-021).
 *
 * Kelas pemakai wajib menyediakan `documentNumber()`, `totalAmount()`,
 * `isOpen()`, dan `openStatus()` (lihat `App\Contracts\Payable`).
 */
trait HasPayments
{
    /**
     * Status yang berarti dokumen ini masih berjalan, sebagai nilai enum
     * agar bisa dibandingkan di dalam SQL oleh penyaring daftar
     * (`scopeUnpaid`/`scopePaidOff`).
     */
    abstract public function openStatus(): SaleStatus|PurchaseStatus;

    /**
     * Belum lunas: dokumen yang masih terbuka dan sisanya belum nol.
     *
     * Saldo tidak pernah disimpan sebagai kolom (D-021), jadi penyaringnya
     * membandingkan `total` dengan jumlah pembayaran yang belum di-void
     * lewat subquery. Dokumen yang dibatalkan tidak ikut, karena
     * tagihannya sudah gugur (D-025) --- sejalan dengan `dueAmount()`.
     */
    public function scopeUnpaid(Builder $query): Builder
    {
        return $query
            ->where($query->getModel()->getTable().'.status', $this->openStatus()->value)
            ->whereRaw(
                $query->getModel()->getTable().'.total > '.$this->activePaymentsSumSql(),
                [$this->getMorphClass()],
            );
    }

    /**
     * Sudah lunas: dokumen terbuka yang totalnya sudah tertutup pembayaran
     * yang masih berlaku.
     */
    public function scopePaidOff(Builder $query): Builder
    {
        return $query
            ->where($query->getModel()->getTable().'.status', $this->openStatus()->value)
            ->whereRaw(
                $query->getModel()->getTable().'.total <= '.$this->activePaymentsSumSql(),
                [$this->getMorphClass()],
            );
    }

    /**
     * Subquery jumlah pembayaran yang belum di-void untuk satu dokumen.
     * Satu-satunya tempat rumus "dibayar" ditulis untuk keperluan query,
     * supaya daftar dan halaman rincian tidak bisa berbeda.
     */
    private function activePaymentsSumSql(): string
    {
        return '(select coalesce(sum(p.amount), 0) from payments as p'
            .' where p.payable_type = ? and p.payable_id = '.$this->getTable().'.id'
            .' and p.voided_at is null)';
    }

    /**
     * @return MorphMany<Payment, $this>
     */
    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    /**
     * Pembayaran yang masih berlaku (belum di-void).
     *
     * @return MorphMany<Payment, $this>
     */
    public function activePayments(): MorphMany
    {
        return $this->payments()->whereNull('voided_at');
    }

    /**
     * Total pembayaran yang masih berlaku. Memakai relasi yang sudah
     * dimuat bila ada, agar daftar transaksi tidak memicu N+1 query.
     */
    public function paidAmount(): string
    {
        $sum = $this->relationLoaded('payments')
            ? $this->payments->whereNull('voided_at')->sum('amount')
            : $this->activePayments()->sum('amount');

        return number_format(round(Money::toFloat($sum), 2), 2, '.', '');
    }

    /**
     * Sisa yang masih harus dibayar. Dokumen yang sudah dibatalkan atau
     * belum berstatus terbuka dianggap tidak punya sisa, supaya daftar
     * transaksi tidak menampilkan tagihan yang sebenarnya sudah gugur
     * (D-025).
     */
    public function dueAmount(): string
    {
        if (! $this->isOpen()) {
            return '0.00';
        }

        $due = round(Money::toFloat($this->totalAmount()) - Money::toFloat($this->paidAmount()), 2);

        return number_format(max(0.0, $due), 2, '.', '');
    }

    public function hasPayments(): bool
    {
        return $this->relationLoaded('payments')
            ? $this->payments->isNotEmpty()
            : $this->payments()->exists();
    }

    public function hasActivePayments(): bool
    {
        return $this->relationLoaded('payments')
            ? $this->payments->whereNull('voided_at')->isNotEmpty()
            : $this->activePayments()->exists();
    }

    public function canRecordPayment(): bool
    {
        return $this->isOpen() && Money::toFloat($this->dueAmount()) > 0;
    }
}
