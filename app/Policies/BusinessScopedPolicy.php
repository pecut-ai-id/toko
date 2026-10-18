<?php

namespace App\Policies;

use App\Models\User;
use App\Support\CurrentBusiness;
use Illuminate\Database\Eloquent\Model;

/**
 * Dasar otorisasi untuk data master yang dimiliki sebuah bisnis.
 *
 * Aturan:
 * - Melihat data: setiap anggota aktif bisnis aktif.
 * - Mengelola data (tambah/ubah/nonaktifkan): hanya owner/admin
 *   (requirements.md Bagian 3), dan hanya pada bisnis yang sedang aktif.
 *
 * Otorisasi diperiksa di server; menyembunyikan tombol di UI bukan
 * pengamanan.
 */
abstract class BusinessScopedPolicy
{
    public function __construct(protected CurrentBusiness $current) {}

    /**
     * Pengguna adalah anggota aktif dari bisnis yang sedang aktif.
     */
    protected function accessible(User $user): bool
    {
        return $this->current->check()
            && $user->belongsToBusiness($this->current->id());
    }

    protected function managesMasterData(User $user): bool
    {
        if (! $this->accessible($user)) {
            return false;
        }

        return $user->roleIn($this->current->id())?->canManageMasterData() ?? false;
    }

    /**
     * Record berada di dalam bisnis yang sedang aktif.
     */
    protected function inCurrentBusiness(Model $model): bool
    {
        return (int) $model->getAttribute('business_id') === $this->current->id();
    }

    public function viewAny(User $user): bool
    {
        return $this->accessible($user);
    }

    public function view(User $user, Model $model): bool
    {
        return $this->accessible($user) && $this->inCurrentBusiness($model);
    }

    public function create(User $user): bool
    {
        return $this->managesMasterData($user);
    }

    public function update(User $user, Model $model): bool
    {
        return $this->managesMasterData($user) && $this->inCurrentBusiness($model);
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->managesMasterData($user) && $this->inCurrentBusiness($model);
    }
}
