<?php

namespace App\Policies;

use App\Models\Purchase;
use App\Models\User;

class PurchasePolicy extends BusinessScopedPolicy
{
    /**
     * Pembelian adalah pekerjaan operasional harian: staff boleh membuat
     * draf (requirements.md Bagian 3).
     */
    public function create(User $user): bool
    {
        return $this->accessible($user);
    }

    /**
     * Konfirmasi penerimaan juga operasional, jadi staff boleh.
     */
    public function receive(User $user, Purchase $purchase): bool
    {
        return $this->accessible($user) && $this->inCurrentBusiness($purchase);
    }

    /**
     * Membayar utang ke supplier adalah pekerjaan operasional, jadi
     * staff boleh — sama seperti mencatat pembayaran penjualan (D-024).
     */
    public function recordPayment(User $user, Purchase $purchase): bool
    {
        return $this->accessible($user) && $this->inCurrentBusiness($purchase);
    }

    /**
     * Pembatalan menyentuh stok yang sudah tercatat, jadi hanya owner/admin.
     */
    public function cancel(User $user, Purchase $purchase): bool
    {
        return $this->managesMasterData($user) && $this->inCurrentBusiness($purchase);
    }
}
