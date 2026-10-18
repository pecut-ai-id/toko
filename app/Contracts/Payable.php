<?php

namespace App\Contracts;

/**
 * Dokumen yang memiliki total dan saldo yang bisa dibayar.
 *
 * Dipakai bersama oleh penjualan (piutang) dan pembelian (utang).
 * Semua nominal berupa decimal string 2 desimal.
 */
interface Payable
{
    public function documentNumber(): string;

    public function totalAmount(): string;

    public function paidAmount(): string;

    public function dueAmount(): string;

    public function isOpen(): bool;
}
