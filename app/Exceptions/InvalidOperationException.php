<?php

namespace App\Exceptions;

/**
 * Aksi tidak diizinkan pada status dokumen saat ini (mis. menerima
 * pembelian yang sudah dibatalkan).
 */
class InvalidOperationException extends DomainException {}
