<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy extends BusinessScopedPolicy
{
    /**
     * Penyesuaian stok (stok awal, koreksi, barang rusak) menyentuh angka
     * yang dipakai laporan, jadi hanya owner/admin (D-010, D-028).
     *
     * Staff tetap boleh mengubah stok lewat jalur operasional yang sudah
     * tercatat dokumennya (penerimaan pembelian dan penjualan).
     */
    public function adjustStock(User $user, Product $product): bool
    {
        return $this->managesMasterData($user) && $this->inCurrentBusiness($product);
    }
}
