<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Jejak audit boleh dibaca seluruh anggota aktif bisnis (D-031).
 *
 * Alasannya: isinya adalah tindakan atas data yang **sudah** boleh dilihat
 * anggota itu sendiri, dan menutupnya untuk staff justru membuat staff
 * tidak bisa memeriksa kenapa stok atau saldo berubah.
 *
 * Baris audit tidak pernah dibuat, diubah, atau dihapus lewat UI, jadi
 * aksi tulis selalu ditolak. `viewAny`/`view` sengaja tidak ditulis ulang:
 * aturan dasarnya (anggota aktif bisnis aktif, dan hanya untuk baris di
 * bisnis itu) sudah persis seperti yang diinginkan.
 */
class AuditLogPolicy extends BusinessScopedPolicy
{
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Model $model): bool
    {
        return false;
    }

    public function delete(User $user, Model $model): bool
    {
        return false;
    }
}
