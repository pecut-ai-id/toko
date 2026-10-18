<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\PurchaseStatus;
use App\Http\Requests\SupplierRequest;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\AuditLogger;
use App\Support\CurrentBusiness;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function __construct(protected AuditLogger $audit) {}

    public function index(Request $request, CurrentBusiness $current): View
    {
        $this->authorize('viewAny', Supplier::class);

        $term = trim((string) $request->query('q'));

        $suppliers = Supplier::query()
            ->forBusiness($current->require())
            ->when($term !== '', function ($query) use ($term): void {
                $query->where('name', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('suppliers.index', [
            'suppliers' => $suppliers,
            'term' => $term,
        ]);
    }

    public function show(Supplier $supplier, CurrentBusiness $current): View
    {
        $this->authorize('view', $supplier);

        $business = $current->require();

        $purchases = Purchase::query()
            ->forBusiness($business)
            ->where('supplier_id', $supplier->getKey())
            ->with('payments')
            ->orderByDesc('purchased_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        // Utang dihitung dari seluruh pembelian yang sudah diterima, bukan
        // hanya 20 baris yang ditampilkan. Pembayaran yang sudah di-void
        // dikecualikan, sama seperti perhitungan di model.
        $openPurchases = Purchase::query()
            ->forBusiness($business)
            ->where('supplier_id', $supplier->getKey())
            ->where('status', PurchaseStatus::Received)
            ->withSum(['payments' => fn ($query) => $query->whereNull('voided_at')], 'amount')
            ->get(['id', 'total']);

        $debt = 0.0;

        foreach ($openPurchases as $openPurchase) {
            $debt += Money::toFloat($openPurchase->total) - Money::toFloat($openPurchase->payments_sum_amount);
        }

        return view('suppliers.show', [
            'supplier' => $supplier,
            'purchases' => $purchases,
            'debt' => number_format(max(0.0, round($debt, 2)), 2, '.', ''),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Supplier::class);

        return view('suppliers.form', [
            'supplier' => new Supplier(['is_active' => true]),
        ]);
    }

    public function store(SupplierRequest $request, CurrentBusiness $current): RedirectResponse
    {
        $this->authorize('create', Supplier::class);

        $supplier = new Supplier($request->validated());
        $supplier->business_id = $current->require()->getKey();
        $supplier->is_active = $request->boolean('is_active');
        $supplier->save();

        $this->audit->log(
            action: AuditAction::SupplierCreated,
            summary: 'Supplier "'.$supplier->name.'" ditambahkan.',
            subject: $supplier,
            changes: $this->audit->diff([], $supplier, ['name', 'phone', 'email', 'address', 'is_active']),
        );

        return redirect()
            ->route('suppliers.index')
            ->with('status', 'Supplier "'.$supplier->name.'" ditambahkan.');
    }

    public function edit(Supplier $supplier): View
    {
        $this->authorize('update', $supplier);

        return view('suppliers.form', compact('supplier'));
    }

    public function update(SupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $before = $supplier->getAttributes();

        $supplier->fill($request->validated());
        $supplier->is_active = $request->boolean('is_active');
        $supplier->save();

        $this->audit->log(
            action: AuditAction::SupplierUpdated,
            summary: 'Supplier "'.$supplier->name.'" diubah.',
            subject: $supplier,
            changes: $this->audit->diff($before, $supplier, ['name', 'phone', 'email', 'address', 'is_active']),
        );

        return redirect()
            ->route('suppliers.index')
            ->with('status', 'Supplier "'.$supplier->name.'" diperbarui.');
    }

    /**
     * Non-destruktif: supplier dinonaktifkan agar riwayat pembelian tetap
     * terbaca (requirements.md Bagian 4).
     */
    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);

        $before = $supplier->getAttributes();

        $supplier->is_active = false;
        $supplier->save();

        $this->audit->log(
            action: AuditAction::SupplierDeactivated,
            summary: 'Supplier "'.$supplier->name.'" dinonaktifkan.',
            subject: $supplier,
            changes: $this->audit->diff($before, $supplier, ['is_active']),
        );

        return redirect()
            ->route('suppliers.index')
            ->with('status', 'Supplier "'.$supplier->name.'" dinonaktifkan.');
    }
}
