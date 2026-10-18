<?php

namespace App\Enums;

/**
 * Peran pengguna di dalam sebuah bisnis (lihat requirements.md Bagian 3).
 */
enum BusinessRole: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Admin => 'Admin',
            self::Staff => 'Staff',
        };
    }

    /**
     * Owner dan Admin boleh mengelola data master serta pengguna.
     */
    public function canManageMasterData(): bool
    {
        return $this !== self::Staff;
    }
}
