<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\User;
use App\Support\CurrentBusiness;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Pencatat jejak audit.
 *
 * Aturan yang dipegang:
 * - Satu pintu: baris `audit_logs` hanya dibuat di sini, supaya bentuk
 *   catatannya seragam dan mudah diperiksa.
 * - Hanya ringkasan yang **penting** yang disimpan (`diff()`), bukan
 *   salinan seluruh baris tabel. Dengan begitu jejak audit tetap terbaca
 *   dan tidak membocorkan data yang tidak perlu.
 * - Pelaku diambil dari pengguna yang sedang login. Operasi yang berjalan
 *   **tanpa pengguna** (seeder, command, sebagian test) tidak menghasilkan
 *   baris audit: baris audit yang tidak menyebut siapa pelakunya tidak
 *   berguna untuk diaudit.
 * - Ditulis di dalam transaction pemanggilnya. Bila pencatatan gagal,
 *   operasinya ikut gagal --- jejak audit tidak boleh bolong demi
 *   menyelamatkan transaksi.
 */
class AuditLogger
{
    public function __construct(protected CurrentBusiness $current) {}

    /**
     * @param  array<string, mixed>  $changes  ringkasan perubahan: nama kolom => [nilai lama, nilai baru]
     */
    public function log(
        AuditAction $action,
        string $summary,
        ?Model $subject = null,
        array $changes = [],
        ?User $user = null,
        ?Business $business = null,
    ): ?AuditLog {
        $user ??= Auth::user();

        if (! $user instanceof User) {
            return null;
        }

        $businessId = $business?->getKey()
            ?? $subject?->getAttribute('business_id')
            ?? $this->current->id();

        if ($businessId === null) {
            throw new RuntimeException(
                'Jejak audit tidak bisa dicatat tanpa bisnis: sertakan bisnis, subjek yang punya business_id, atau konteks bisnis aktif.'
            );
        }

        $log = new AuditLog([
            'action' => $action,
            'summary' => $summary,
            'changes' => $changes === [] ? null : $changes,
        ]);

        $log->business_id = $businessId;
        $log->user_id = $user->getKey();

        if ($subject !== null) {
            $log->subject()->associate($subject);
        }

        $log->save();

        return $log;
    }

    /**
     * Bandingkan nilai lama dengan model yang sudah disimpan, hanya untuk
     * kolom yang memang layak dicatat.
     *
     * @param  array<string, mixed>  $before  atribut mentah sebelum perubahan
     * @param  array<int, string>  $keys
     * @return array<string, array{0: mixed, 1: mixed}>
     */
    public function diff(array $before, Model $after, array $keys): array
    {
        $changes = [];

        foreach ($keys as $key) {
            $old = $this->normalize($after, $key, $before[$key] ?? null);
            $new = $this->normalize($after, $key, $after->getAttribute($key));

            if ($old !== $new) {
                $changes[$key] = [$old, $new];
            }
        }

        return $changes;
    }

    /**
     * Samakan bentuk nilai mentah dari database (`"0.00"`, `1`) dengan
     * nilai yang sudah dicast model (`"0.00"`, `true`) supaya perubahan
     * palsu tidak ikut tercatat.
     *
     * Cast model dipakai sebagai acuan, bukan tebakan dari isi nilainya.
     */
    private function normalize(Model $model, string $key, mixed $value): mixed
    {
        $cast = $model->getCasts()[$key] ?? null;

        if ($cast === 'bool' || $cast === 'boolean') {
            return (bool) $value;
        }

        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value === null) {
            return null;
        }

        if (is_numeric($value)) {
            return number_format((float) $value, 2, '.', '');
        }

        return (string) $value;
    }
}
