<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\BusinessRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Semua keanggotaan bisnis, termasuk yang nonaktif.
     *
     * @return BelongsToMany<Business, $this>
     */
    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class)
            ->withPivot(['role', 'is_active'])
            ->withTimestamps();
    }

    /**
     * Keanggotaan bisnis yang masih aktif.
     *
     * @return BelongsToMany<Business, $this>
     */
    public function activeBusinesses(): BelongsToMany
    {
        return $this->businesses()->wherePivot('is_active', true);
    }

    /**
     * Apakah pengguna adalah anggota aktif dari bisnis tertentu.
     */
    public function belongsToBusiness(Business|int $business): bool
    {
        $id = $business instanceof Business ? $business->getKey() : $business;

        return $this->activeBusinesses()->where('businesses.id', $id)->exists();
    }

    /**
     * Peran pengguna pada bisnis tertentu, atau null bila bukan anggota aktif.
     */
    public function roleIn(Business|int $business): ?BusinessRole
    {
        $id = $business instanceof Business ? $business->getKey() : $business;

        $membership = $this->activeBusinesses()->where('businesses.id', $id)->first();

        if ($membership === null) {
            return null;
        }

        return BusinessRole::tryFrom((string) $membership->pivot->role);
    }
}
