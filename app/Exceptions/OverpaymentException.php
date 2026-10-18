<?php

namespace App\Exceptions;

/**
 * Pembayaran melebihi saldo dokumen.
 *
 * requirements.md Bagian 4 melarang kelebihan bayar kecuali aturan kelebihan
 * bayar disetujui secara eksplisit. TokoFlow belum menyetujuinya.
 */
class OverpaymentException extends DomainException
{
    public static function forDocument(string $number, float $due, float $requested): self
    {
        return new self(sprintf(
            'Pembayaran %s melebihi sisa %s untuk %s.',
            number_format($requested, 2, ',', '.'),
            number_format($due, 2, ',', '.'),
            $number,
        ));
    }
}
