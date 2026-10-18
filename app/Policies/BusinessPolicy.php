<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;

class BusinessPolicy
{
    /**
     * Pengguna boleh melihat/mengakses bisnis bila ia anggota aktif dan
     * bisnisnya aktif.
     */
    public function view(User $user, Business $business): bool
    {
        return $business->is_active && $user->belongsToBusiness($business);
    }

    /**
     * Owner dan Admin boleh mengelola anggota bisnis.
     */
    public function manageMembers(User $user, Business $business): bool
    {
        if (! $this->view($user, $business)) {
            return false;
        }

        return $user->roleIn($business)?->canManageMasterData() ?? false;
    }
}
