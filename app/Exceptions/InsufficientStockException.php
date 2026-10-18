<?php

namespace App\Exceptions;

/**
 * Operasi akan membuat stok produk menjadi negatif.
 */
class InsufficientStockException extends DomainException
{
    public static function forProduct(string $productName, float $available, float $requested): self
    {
        return new self(sprintf(
            'Stok %s tidak cukup: tersedia %s, diminta %s.',
            $productName,
            number_format($available, 2, ',', '.'),
            number_format($requested, 2, ',', '.'),
        ));
    }
}
