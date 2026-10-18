<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'amount',
    'paid_at',
    'method',
    'reference',
    'notes',
    'user_id',
])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use BelongsToBusiness, HasFactory;

    /**
     * `business_id`, `payable`, dan kolom void diisi dari dokumen sumber
     * atau oleh PaymentService, bukan dari input pengguna.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'date',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function voider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /**
     * Pembayaran yang dibatalkan tidak dihapus, hanya tidak lagi dihitung
     * sebagai pelunasan (D-015: reversal tercatat, bukan penghapusan).
     */
    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }
}
