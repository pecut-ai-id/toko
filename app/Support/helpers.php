<?php

use App\Support\Money;

if (! function_exists('rupiah')) {
    /**
     * Format nilai uang untuk tampilan: rupiah(1275000) => "Rp1.275.000".
     */
    function rupiah(string|int|float|null $value): string
    {
        return Money::rupiah($value);
    }
}

if (! function_exists('angka')) {
    /**
     * Format angka (misal kuantitas) dengan konvensi Indonesia tanpa "Rp".
     */
    function angka(string|int|float|null $value): string
    {
        return Money::format($value);
    }
}
