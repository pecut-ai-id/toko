<?php

namespace App\Http\Controllers;

use App\Exceptions\DomainException;
use App\Http\Requests\SaleRequest;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use App\Services\SaleService;
use App\Support\CurrentBusiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function __construct(protected SaleService $sales) {}

    public function index(Request $request, CurrentBusiness $current): View
    {
        $this->authorize('viewAny', Sale::class);

        $status = (string) $request->query('status');
        $pembayaran = (string) $request->query('pembayaran');

        $sales = Sale::query()
            ->forBusiness($current->require())
            // `payments` dimuat agar kolom dibayar/sisa tidak memicu N+1.
            ->with(['customer', 'payments'])
            ->when(
                in_array($status, ['completed', 'cancelled'], true),
                fn ($query) => $query->where('status', $status)
            )
            // Nilai lain diabaikan (dianggap "semua"), supaya URL yang
            // ditulis tangan tidak membuat halaman kosong tanpa penjelasan.
            ->when($pembayaran === 'belum', fn ($query) => $query->unpaid())
            ->when($pembayaran === 'sudah', fn ($query) => $query->paidOff())
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('sales.index', [
            'sales' => $sales,
            'status' => $status,
            'pembayaran' => $pembayaran,
        ]);
    }

    public function create(CurrentBusiness $current): View
    {
        $this->authorize('create', Sale::class);

        $business = $current->require();

        return view('sales.create', [
            'customers' => Customer::query()->forBusiness($business)->active()->orderBy('name')->get(),
            'products' => Product::query()->forBusiness($business)->active()->orderBy('name')->get(),
        ]);
    }

    public function store(SaleRequest $request, CurrentBusiness $current): RedirectResponse
    {
        $this->authorize('create', Sale::class);

        /** @var User $user */
        $user = $request->user();

        try {
            $sale = $this->sales->create($request->validated(), $current->require(), $user);
        } catch (DomainException $exception) {
            // Stok kurang atau pembayaran melebihi total: tidak ada
            // perubahan yang tersimpan karena transaksinya di-rollback.
            return back()->withInput()->withErrors(['sale' => $exception->getMessage()]);
        }

        return redirect()
            ->route('sales.show', $sale)
            ->with('status', 'Penjualan '.$sale->number.' disimpan.');
    }

    public function show(Sale $sale): View
    {
        $this->authorize('view', $sale);

        $sale->load([
            'customer',
            'items.product',
            'payments.user',
            'payments.voider',
            'movements.product',
            'canceller',
        ]);

        return view('sales.show', compact('sale'));
    }

    public function cancel(Request $request, Sale $sale): RedirectResponse
    {
        $this->authorize('cancel', $sale);

        /** @var User $user */
        $user = $request->user();

        try {
            $cancelled = $this->sales->cancel($sale, $user);
        } catch (DomainException $exception) {
            return back()->withErrors(['sale' => $exception->getMessage()]);
        }

        return redirect()
            ->route('sales.show', $cancelled)
            ->with('status', 'Penjualan '.$cancelled->number.' dibatalkan; stok dikembalikan.');
    }
}
