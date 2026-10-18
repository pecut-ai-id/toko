<?php

namespace App\Support;

/**
 * Penanganan nilai uang TokoFlow.
 *
 * Dua aturan penting:
 * 1. Uang **wajib** masuk ke database sebagai decimal string (bukan float).
 * 2. Input pengguna memakai konvensi Indonesia: titik sebagai pemisah
 *    ribuan, koma sebagai pemisah desimal ("1.275.000,50").
 *
 * `parseToDecimal()` mengubah input bebas menjadi string decimal siap
 * disimpan. `format()` mengembalikannya ke tampilan Indonesia.
 */
final class Money
{
    /**
     * Ubah input pengguna menjadi string decimal (2 angka di belakang koma).
     *
     * Mengembalikan null bila input tidak bisa dibaca sebagai angka,
     * sehingga aturan validasi `required`/`numeric` yang menanganinya.
     *
     * Contoh:
     * - "1.275.000"      -> "1275000.00"
     * - "Rp 85.000,50"   -> "85000.50"
     * - "85,5"           -> "85.50"
     * - "85.5"           -> "85.50"   (satu titik + bukan 3 digit = desimal)
     * - "85.000"         -> "85000.00" (satu titik + 3 digit = ribuan)
     * - "-1.000"         -> "-1000.00"
     */
    public static function parseToDecimal(string|int|float|null $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return number_format((float) $value, 2, '.', '');
        }

        $clean = preg_replace('/[^0-9.,-]/', '', trim($value));

        if ($clean === null || $clean === '') {
            return null;
        }

        $negative = str_contains($clean, '-');
        $clean = str_replace('-', '', $clean);

        $hasDot = str_contains($clean, '.');
        $hasComma = str_contains($clean, ',');

        if ($hasDot && $hasComma) {
            // Pemisah desimal adalah yang muncul paling akhir.
            if (strrpos($clean, ',') > strrpos($clean, '.')) {
                $clean = str_replace('.', '', $clean);
                $clean = str_replace(',', '.', $clean);
            } else {
                $clean = str_replace(',', '', $clean);
            }
        } elseif ($hasComma) {
            $clean = str_replace(',', '.', $clean);
        } elseif ($hasDot) {
            $parts = explode('.', $clean);
            $last = end($parts);

            // Lebih dari satu titik, atau tepat 3 digit setelah titik terakhir
            // => pemisah ribuan.
            if (count($parts) === 2 && strlen((string) $last) !== 3) {
                // biarkan sebagai pemisah desimal
            } else {
                $clean = str_replace('.', '', $clean);
            }
        }

        if (! preg_match('/^\d+(\.\d+)?$/', $clean)) {
            return null;
        }

        $normalized = number_format((float) $clean, 2, '.', '');

        return $negative && (float) $normalized !== 0.0 ? '-'.$normalized : $normalized;
    }

    /**
     * Nilai aman untuk operasi aritmetika.
     */
    public static function toFloat(string|int|float|null $value): float
    {
        return (float) ($value ?? 0);
    }

    /**
     * Format tampilan Indonesia: pemisah ribuan titik, desimal koma.
     * Angka bulat tidak menampilkan koma (Rp85.000, bukan Rp85.000,00).
     */
    public static function format(string|int|float|null $value): string
    {
        $number = (float) ($value ?? 0);
        $decimals = abs($number - round($number)) < 0.005 ? 0 : 2;

        return number_format($number, $decimals, ',', '.');
    }

    /**
     * Format dengan awalan "Rp". Tanda minus diletakkan sebelum "Rp"
     * (mis. "-Rp1.000").
     */
    public static function rupiah(string|int|float|null $value): string
    {
        $number = (float) ($value ?? 0);

        return ($number < 0 ? '-Rp' : 'Rp').self::format(abs($number));
    }

    /**
     * Perkalian uang x kuantitas dengan pembulatan 2 desimal eksplisit
     * (requirements.md Bagian 6 poin 3).
     *
     * Kedua argumen diharapkan sudah berupa decimal string hasil
     * `parseToDecimal()` atau cast decimal dari database; nilai seperti
     * "50.000" di sini dibaca sebagai 50, bukan 50.000.
     */
    public static function multiply(string|int|float|null $amount, string|int|float|null $quantity): string
    {
        return number_format(
            round(self::toFloat($amount) * self::toFloat($quantity), 2),
            2,
            '.',
            ''
        );
    }

    /**
     * Penjumlahan uang dengan pembulatan 2 desimal eksplisit.
     *
     * @param  iterable<string|int|float|null>  $amounts
     */
    public static function sum(iterable $amounts): string
    {
        $total = 0.0;

        foreach ($amounts as $amount) {
            $total += self::toFloat($amount);
        }

        return number_format(round($total, 2), 2, '.', '');
    }
}
