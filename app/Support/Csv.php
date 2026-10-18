<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ekspor CSV sederhana (requirements.md Bagian 4 "Laporan": ekspor opsional
 * setelah laporan dasarnya benar).
 *
 * Dua pilihan yang disengaja:
 * - Pemisah kolom `;` dan BOM UTF-8, supaya angka ribuan berformat
 *   Indonesia tetap terbaca rapi di Excel dengan locale Indonesia.
 * - Angka ditulis sebagai decimal polos (`1275000.00`), bukan
 *   `Rp1.275.000`, supaya hasil ekspor bisa dihitung ulang di
 *   spreadsheet.
 */
final class Csv
{
    /**
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, string|int|float|null>>  $rows
     */
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            // BOM UTF-8: tanpa ini Excel di Windows menampilkan karakter aneh.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $headers, ';');

            foreach ($rows as $row) {
                fputcsv($handle, $row, ';');
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Rapikan angka menjadi decimal polos untuk isi CSV.
     */
    public static function number(mixed $value): string
    {
        return number_format(round(Money::toFloat($value), 2), 2, '.', '');
    }
}
