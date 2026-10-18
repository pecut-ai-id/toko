<?php

namespace App\Http\Middleware;

use App\Models\Business;
use App\Models\User;
use App\Support\CurrentBusiness;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menentukan bisnis aktif untuk request terautentikasi.
 *
 * Urutan: bisnis dari session (bila pengguna masih menjadi anggota aktif),
 * jika tidak ada maka keanggotaan aktif pertama. Pengguna tanpa keanggotaan
 * akan mendapat konteks kosong dan ditolak oleh EnsureCurrentBusiness.
 */
class ResolveCurrentBusiness
{
    public function __construct(protected CurrentBusiness $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            $this->current->set(null);

            return $next($request);
        }

        $business = $this->fromSession($request, $user) ?? $this->default($user);

        $this->current->set($business);

        if ($business !== null && $request->session()->get('current_business_id') !== $business->getKey()) {
            $request->session()->put('current_business_id', $business->getKey());
        }

        return $next($request);
    }

    protected function fromSession(Request $request, User $user): ?Business
    {
        $id = $request->session()->get('current_business_id');

        if (! $id) {
            return null;
        }

        return $user->activeBusinesses()->where('businesses.id', $id)->first();
    }

    protected function default(User $user): ?Business
    {
        return $user->activeBusinesses()->orderBy('business_user.id')->first();
    }
}
