<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

/**
 * Otorisasi atas satu baris pembayaran.
 *
 * Tidak ada policy terpisah untuk mencatat pembayaran: itu wewenang policy
 * dokumennya (SalePolicy/PurchasePolicy), karena yang diperiksa adalah
 * dokumen sumbernya.
 */
class PaymentPolicy extends BusinessScopedPolicy
{
    /**
     * Membatalkan (void) pembayaran adalah koreksi yang mengubah saldo
     * piutang/utang, jadi hanya owner/admin — sejalan dengan pembatalan
     * dokumen (D-024).
     */
    public function void(User $user, Payment $payment): bool
    {
        return $this->managesMasterData($user) && $this->inCurrentBusiness($payment);
    }
}
