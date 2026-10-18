<?php

namespace App\Services;

use App\Contracts\Payable;
use App\Enums\AuditAction;
use App\Exceptions\InvalidOperationException;
use App\Exceptions\OverpaymentException;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Pencatatan dan pembatalan pembayaran atas dokumen bersaldo (piutang
 * penjualan dan utang pembelian).
 *
 * Aturan yang dijaga (requirements.md Bagian 4):
 * - Pembayaran nol atau negatif ditolak.
 * - Pembayaran melebihi sisa saldo ditolak; kelebihan bayar belum
 *   diizinkan di TokoFlow.
 * - Dokumen yang sudah dibatalkan tidak bisa dibayar.
 * - Saldo dihitung ulang di server dengan pembacaan terkunci, sehingga
 *   dua pembayaran bersamaan tidak bisa melampaui total.
 * - Pembatalan pembayaran (void) tidak menghapus baris; baris tetap ada
 *   sebagai jejak dan hanya berhenti dihitung sebagai pelunasan.
 */
class PaymentService
{
    public function __construct(protected AuditLogger $audit) {}

    /**
     * @param  array{amount: string|int|float, paid_at?: string|null, method?: string|null, reference?: string|null, notes?: string|null}  $data
     */
    public function record(Model&Payable $payable, array $data, User $user): Payment
    {
        return DB::transaction(function () use ($payable, $data, $user): Payment {
            // Kunci baris dokumen: semua pembayaran atas dokumen yang sama
            // berbaris di sini, sehingga perhitungan saldo selalu atas data
            // terbaru yang sudah di-commit.
            $locked = $payable->newQuery()
                ->whereKey($payable->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->isOpen()) {
                throw new InvalidOperationException(sprintf(
                    'Dokumen %s belum terbuka atau sudah dibatalkan sehingga tidak dapat dibayar.',
                    $locked->documentNumber(),
                ));
            }

            $amount = round(Money::toFloat(Money::parseToDecimal($data['amount'])), 2);

            if ($amount <= 0) {
                throw new InvalidOperationException('Jumlah pembayaran harus lebih dari nol.');
            }

            // Baca ulang pembayaran berlaku dengan kunci supaya saldo tidak
            // basi dan pembayaran yang sudah di-void tidak ikut dihitung.
            $total = Money::toFloat($locked->totalAmount());
            $paid = Money::toFloat($locked->activePayments()->lockForUpdate()->sum('amount'));
            $due = round(max(0.0, $total - $paid), 2);

            if ($amount > $due) {
                throw OverpaymentException::forDocument($locked->documentNumber(), $due, $amount);
            }

            $payment = new Payment([
                'amount' => number_format($amount, 2, '.', ''),
                'paid_at' => $data['paid_at'] ?? now()->toDateString(),
                'method' => $data['method'] ?? null,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'user_id' => $user->getKey(),
            ]);

            $payment->business_id = $locked->getAttribute('business_id');
            $payment->payable()->associate($locked);
            $payment->save();

            $this->audit->log(
                action: AuditAction::PaymentRecorded,
                summary: sprintf(
                    'Pembayaran %s untuk %s %s dicatat.',
                    Money::rupiah($payment->amount),
                    $this->documentLabel($locked),
                    $locked->documentNumber(),
                ),
                subject: $locked,
                changes: ['amount' => [null, $payment->amount]],
                user: $user,
            );

            return $payment;
        });
    }

    /**
     * Sebutan dokumen untuk keterangan audit ("penjualan"/"pembelian").
     */
    private function documentLabel(Payable $payable): string
    {
        return $payable instanceof Sale ? 'penjualan' : 'pembelian';
    }

    /**
     * Batalkan (void) satu pembayaran.
     *
     * Barisnya tidak dihapus: `voided_at`, `voided_by`, dan `void_reason`
     * diisi sehingga riwayatnya tetap terbaca, tetapi nilainya berhenti
     * dihitung sebagai pelunasan. Dengan ini dokumen yang telanjur
     * dibayar masih bisa dikoreksi: void pembayarannya, lalu batalkan
     * dokumennya bila perlu.
     */
    public function void(Payment $payment, User $user, string $reason): Payment
    {
        return DB::transaction(function () use ($payment, $user, $reason): Payment {
            $locked = Payment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isVoided()) {
                throw new InvalidOperationException('Pembayaran ini sudah dibatalkan sebelumnya.');
            }

            // Kunci dokumennya juga, agar tidak ada pembayaran baru yang
            // menyelinap di antara pemeriksaan dan penyimpanan.
            $payable = $locked->payable()->getResults();

            if (! $payable instanceof Payable) {
                throw new InvalidOperationException('Pembayaran ini tidak menempel pada dokumen bersaldo yang dikenal.');
            }

            $lockedPayable = $payable->newQuery()
                ->whereKey($payable->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedPayable instanceof Payable || ! $lockedPayable->isOpen()) {
                throw new InvalidOperationException(sprintf(
                    'Dokumen %s sudah dibatalkan, jadi pembayarannya tidak perlu dibatalkan lagi.',
                    $payable->documentNumber(),
                ));
            }

            $locked->voided_at = now();
            $locked->voided_by = $user->getKey();
            $locked->void_reason = $reason;
            $locked->save();

            $this->audit->log(
                action: AuditAction::PaymentVoided,
                summary: sprintf(
                    'Pembayaran %s untuk %s %s dibatalkan: %s',
                    Money::rupiah($locked->amount),
                    $this->documentLabel($lockedPayable),
                    $lockedPayable->documentNumber(),
                    $reason,
                ),
                subject: $lockedPayable,
                changes: ['voided' => [false, true], 'reason' => [null, $reason]],
                user: $user,
            );

            return $locked;
        });
    }
}
