<?php

namespace App\Support;

use App\Models\Business;
use RuntimeException;

/**
 * Konteks bisnis aktif untuk request yang sedang berjalan.
 *
 * Didaftarkan sebagai singleton (lihat AppServiceProvider) sehingga
 * middleware, policy, dan controller memakai instance yang sama.
 * Query domain pada tahap berikutnya harus dibatasi memakai
 * `$currentBusiness->id()`.
 */
class CurrentBusiness
{
    protected ?Business $business = null;

    public function set(?Business $business): void
    {
        $this->business = $business;
    }

    public function get(): ?Business
    {
        return $this->business;
    }

    public function id(): ?int
    {
        return $this->business?->getKey();
    }

    public function check(): bool
    {
        return $this->business !== null;
    }

    public function require(): Business
    {
        if ($this->business === null) {
            throw new RuntimeException('Tidak ada bisnis aktif pada konteks request ini.');
        }

        return $this->business;
    }
}
