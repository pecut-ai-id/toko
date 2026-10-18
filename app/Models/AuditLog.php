<?php

namespace App\Models;

use App\Enums\AuditAction;
use App\Models\Concerns\BelongsToBusiness;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Satu baris jejak audit.
 *
 * Barisnya tidak pernah diperbarui: tidak ada `updated_at`, dan nilainya
 * hanya dibuat lewat `App\Services\AuditLogger`.
 */
#[Fillable(['action', 'summary', 'changes'])]
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use BelongsToBusiness, HasFactory;

    /**
     * Jejak audit tidak punya waktu perubahan.
     */
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'changes' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
