<?php

namespace App\Http\Controllers;

use App\Services\ReportingService;
use App\Support\CurrentBusiness;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(protected ReportingService $reports) {}

    /**
     * Ringkasan operasional bisnis aktif.
     *
     * Angkanya berasal dari transaksi nyata; definisi
     * metriknya ada di `ReportingService` dan docs/DECISIONS.md D-029.
     * Otorisasi cukup dari middleware konteks bisnis: dashboard hanya
     * menampilkan angka bisnis yang memang boleh dilihat anggotanya.
     */
    public function __invoke(CurrentBusiness $current): View
    {
        $business = $current->require();

        return view('dashboard', [
            'business' => $business,
            'summary' => $this->reports->dashboard($business),
        ]);
    }
}
