<?php

namespace App\Enums;

enum PurchaseStatus: string
{
    case Draft = 'draft';
    case Received = 'received';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Received => 'Diterima',
            self::Cancelled => 'Dibatalkan',
        };
    }

    public function isDraft(): bool
    {
        return $this === self::Draft;
    }

    public function isReceived(): bool
    {
        return $this === self::Received;
    }

    public function isCancelled(): bool
    {
        return $this === self::Cancelled;
    }
}
