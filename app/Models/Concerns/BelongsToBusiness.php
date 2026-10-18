<?php

namespace App\Models\Concerns;

use App\Models\Business;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Untuk model yang dimiliki sebuah bisnis (data master maupun transaksi).
 *
 * Setiap query wajib dibatasi dengan `forBusiness()` agar data tidak
 * bocor antar bisnis (lihat docs/DECISIONS.md D-009).
 */
trait BelongsToBusiness
{
    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @param  Builder<$this>  $query
     */
    public function scopeForBusiness(Builder $query, Business|int $business): void
    {
        $id = $business instanceof Business ? $business->getKey() : $business;

        $query->where($this->getTable().'.business_id', $id);
    }
}
