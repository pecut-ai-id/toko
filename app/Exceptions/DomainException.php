<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Kesalahan aturan bisnis yang aman ditampilkan ke pengguna.
 *
 * Pesan pada exception turunannya boleh muncul di UI; ini berbeda dari
 * error teknis yang tidak boleh dibocorkan.
 */
abstract class DomainException extends RuntimeException {}
