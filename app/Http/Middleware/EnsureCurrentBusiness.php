<?php

namespace App\Http\Middleware;

use App\Support\CurrentBusiness;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menolak request yang tidak memiliki konteks bisnis aktif.
 *
 * Dipakai pada route operasional agar pengguna tanpa keanggotaan bisnis
 * tidak dapat mengakses data transaksi.
 */
class EnsureCurrentBusiness
{
    public function __construct(protected CurrentBusiness $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->current->check()) {
            abort(403, 'Akun Anda belum terdaftar pada bisnis mana pun.');
        }

        return $next($request);
    }
}
