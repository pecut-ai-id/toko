<?php

namespace Database\Factories;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Business;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * Baris jejak audit untuk keperluan uji.
 *
 * Dalam aplikasi, baris ini hanya dibuat lewat `AuditLogger`; factory ini
 * dipakai untuk menyiapkan data (mis. mengisi riwayat sebelum halaman
 * dibuka).
 *
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'business_id' => Business::factory(),
            'user_id' => User::factory(),
            'action' => AuditAction::SaleCreated,
            'summary' => 'Catatan uji',
            'changes' => null,
        ];
    }

    public function forBusiness(Business $business): static
    {
        return $this->state(fn (): array => [
            'business_id' => $business->getKey(),
        ]);
    }

    public function by(User $user): static
    {
        return $this->state(fn (): array => [
            'user_id' => $user->getKey(),
        ]);
    }

    public function action(AuditAction $action): static
    {
        return $this->state(fn (): array => [
            'action' => $action,
        ]);
    }

    /**
     * `business_id` dan subjek diisi langsung (bukan mass assignment)
     * karena memang tidak fillable.
     */
    public function forSubject(Model $subject): static
    {
        return $this->afterMaking(function (AuditLog $log) use ($subject): void {
            $log->subject()->associate($subject);

            if ($subject->getAttribute('business_id') !== null) {
                $log->business_id = $subject->getAttribute('business_id');
            }
        });
    }
}
