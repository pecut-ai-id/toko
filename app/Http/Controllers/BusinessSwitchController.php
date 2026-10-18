<?php

namespace App\Http\Controllers;

use App\Models\Business;
use App\Support\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BusinessSwitchController extends Controller
{
    /**
     * Pindahkan konteks bisnis aktif ke bisnis yang dipilih.
     *
     * Otorisasi diperiksa di server lewat BusinessPolicy: pengguna hanya
     * dapat berpindah ke bisnis yang menjadi keanggotaannya.
     */
    public function __invoke(Request $request, Business $business, CurrentBusiness $current): RedirectResponse
    {
        $this->authorize('view', $business);

        $request->session()->put('current_business_id', $business->getKey());
        $current->set($business);

        return redirect()
            ->route('dashboard')
            ->with('status', 'Bisnis aktif diubah ke '.$business->name.'.');
    }
}
