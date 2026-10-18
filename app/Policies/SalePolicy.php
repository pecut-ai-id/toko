<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy extends BusinessScopedPolicy
{
    /**
     * Menjual adalah pekerjaan operasional harian: staff boleh
     * (requirements.md Bagian 3).
     */
    public function create(User $user): bool
    {
        return $this->accessible($user);
    }

    /**
     * Pencatatan pembayaran piutang juga operasional.
     */
    public function recordPayment(User $user, Sale $sale): bool
    {
        return $this->accessible($user) && $this->inCurrentBusiness($sale);
    }

    /**
     * Pembatalan mengembalikan stok yang sudah tercatat, jadi hanya
     * owner/admin (konsisten dengan D-017).
     */
    public function cancel(User $user, Sale $sale): bool
    {
        return $this->managesMasterData($user) && $this->inCurrentBusiness($sale);
    }
}
