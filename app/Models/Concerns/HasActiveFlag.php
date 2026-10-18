<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Untuk data master yang punya kolom `is_active` dan tidak dihapus
 * secara destruktif (docs/DECISIONS.md D-011).
 */
trait HasActiveFlag
{
    /**
     * @param  Builder<$this>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where($this->getTable().'.is_active', true);
    }
}
