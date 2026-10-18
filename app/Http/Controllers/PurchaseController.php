<?php

namespace App\Http\Controllers;

use App\Exceptions\DomainException;
use App\Http\Requests\PurchaseRequest;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseService;
use App\Support\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    public function __construct(protected PurchaseService $purchases) {}

    public function index(Request $request, CurrentBusiness $current): View
    {
        $this->authorize('viewAny', Purchase::class);

        $status = (string) $request->query('status');
        $pembayaran = (string) $request->query('pembayaran');

        $purchases = Purchase::query()
            ->forBusiness($current->require())
            // `payments` dimuat agar kolom dibayar/sisa tidak memicu N+1.
            ->with(['supplier', 'payments'])
            ->when(
                in_array($status, ['draft', 'received', 'cancelled'], true),
                fn ($query) => $query->where('status', $status)
            )
            // Nilai lain diabaikan (dianggap "semua"); draf tidak muncul di
            // "belum lunas" karena utang baru ada setelah diterima (D-025).
            ->when($pembayaran === 'belum', fn ($query) => $query->unpaid())
            ->when($pembayaran === 'sudah', fn ($query) => $query->paidOff())
            ->orderByDesc('purchased_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('purchases.index', [
            'purchases' => $purchases,
            'status' => $status,
            'pembayaran' => $pembayaran,
        ]);
    }

    public function create(CurrentBusiness $current): View
    {
        $this->authorize('create', Purchase::class);

        $business = $current->require();

        return view('purchases.create', [
            'suppliers' => Supplier::query()->forBusiness($business)->active()->orderBy('name')->get(),
            'products' => Product::query()->forBusiness($business)->active()->orderBy('name')->get(),
        ]);
    }

    public function store(PurchaseRequest $request, CurrentBusiness $current): RedirectResponse
    {
        $this->authorize('create', Purchase::class);

        $purchase = $this->purchases->create($request->validated(), $current->require());

        return redirect()
            ->route('purchases.show', $purchase)
            ->with('status', 'Pembelian '.$purchase->number.' disimpan sebagai draf.');
    }

    public function show(Purchase $purchase): View
    {
        $this->authorize('view', $purchase);

        $purchase->load([
            'supplier',
            'items.product',
            'payments.user',
            'payments.voider',
            'movements.product',
            'movements.user',
            'receiver',
            'canceller',
        ]);

        return view('purchases.show', compact('purchase'));
    }

    public function receive(Request $request, Purchase $purchase): RedirectResponse
    {
        $this->authorize('receive', $purchase);

        /** @var User $user */
        $user = $request->user();

        try {
            $received = $this->purchases->receive($purchase, $user);
        } catch (DomainException $exception) {
            return back()->withErrors(['purchase' => $exception->getMessage()]);
        }

        return redirect()
            ->route('purchases.show', $received)
            ->with('status', 'Penerimaan '.$received->number.' dikonfirmasi; stok telah diperbarui.');
    }

    public function cancel(Request $request, Purchase $purchase): RedirectResponse
    {
        $this->authorize('cancel', $purchase);

        /** @var User $user */
        $user = $request->user();

        try {
            $cancelled = $this->purchases->cancel($purchase, $user);
        } catch (DomainException $exception) {
            return back()->withErrors(['purchase' => $exception->getMessage()]);
        }

        return redirect()
            ->route('purchases.show', $cancelled)
            ->with('status', 'Pembelian '.$cancelled->number.' dibatalkan.');
    }
}
