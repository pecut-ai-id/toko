<?php

namespace App\Http\Controllers;

use App\Exceptions\DomainException;
use App\Http\Requests\PaymentRequest;
use App\Http\Requests\PaymentVoidRequest;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\User;
use App\Services\PaymentService;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;

class PaymentController extends Controller
{
    public function __construct(protected PaymentService $payments) {}

    /**
     * Catat pembayaran (penuh atau parsial) atas piutang penjualan.
     */
    public function storeForSale(PaymentRequest $request, Sale $sale): RedirectResponse
    {
        $this->authorize('recordPayment', $sale);

        return $this->record($request, $sale, 'sales.show');
    }

    /**
     * Catat pembayaran (penuh atau parsial) atas utang pembelian.
     */
    public function storeForPurchase(PaymentRequest $request, Purchase $purchase): RedirectResponse
    {
        $this->authorize('recordPayment', $purchase);

        return $this->record($request, $purchase, 'purchases.show');
    }

    /**
     * Batalkan (void) satu pembayaran tanpa menghapus barisnya.
     *
     * Dipakai untuk mengoreksi pembayaran yang salah input, dan sebagai
     * langkah pembuka bila dokumen yang telanjur dibayar harus dibatalkan
     * (D-026).
     */
    public function void(PaymentVoidRequest $request, Payment $payment): RedirectResponse
    {
        $this->authorize('void', $payment);

        /** @var User $user */
        $user = $request->user();

        // Alamat kembali ditentukan dokumen sumbernya, jadi diambil
        // sebelum pembayarannya diubah.
        $payable = $payment->payable;
        $route = $payable instanceof Purchase ? 'purchases.show' : 'sales.show';

        try {
            $this->payments->void($payment, $user, $request->validated()['reason']);
        } catch (DomainException $exception) {
            return back()->withErrors(['payment' => $exception->getMessage()]);
        }

        return redirect()
            ->route($route, $payable)
            ->with('status', 'Pembayaran '.Money::rupiah($payment->amount).' dibatalkan.');
    }

    /**
     * @param  Sale|Purchase  $payable
     */
    protected function record(PaymentRequest $request, $payable, string $route): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $payment = $this->payments->record($payable, $request->validated(), $user);
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['payment' => $exception->getMessage()]);
        }

        return redirect()
            ->route($route, $payable)
            ->with('status', 'Pembayaran '.Money::rupiah($payment->amount).' tercatat.');
    }
}
