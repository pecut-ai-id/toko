<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Enums\SaleStatus;
use App\Http\Requests\CustomerRequest;
use App\Models\Customer;
use App\Models\Sale;
use App\Services\AuditLogger;
use App\Support\CurrentBusiness;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CustomerController extends Controller
{
    public function __construct(protected AuditLogger $audit) {}

    public function index(Request $request, CurrentBusiness $current): View
    {
        $this->authorize('viewAny', Customer::class);

        $term = trim((string) $request->query('q'));

        $customers = Customer::query()
            ->forBusiness($current->require())
            ->when($term !== '', function ($query) use ($term): void {
                $query->where('name', 'like', "%{$term}%");
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('customers.index', [
            'customers' => $customers,
            'term' => $term,
        ]);
    }

    public function show(Customer $customer, CurrentBusiness $current): View
    {
        $this->authorize('view', $customer);

        $business = $current->require();

        $sales = Sale::query()
            ->forBusiness($business)
            ->where('customer_id', $customer->getKey())
            ->with('payments')
            ->orderByDesc('sold_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        // Piutang dihitung dari seluruh penjualan yang belum dibatalkan,
        // bukan hanya 20 baris yang ditampilkan. Pembayaran yang sudah
        // di-void dikecualikan, sama seperti perhitungan di model.
        $openSales = Sale::query()
            ->forBusiness($business)
            ->where('customer_id', $customer->getKey())
            ->where('status', SaleStatus::Completed)
            ->withSum(['payments' => fn ($query) => $query->whereNull('voided_at')], 'amount')
            ->get(['id', 'total']);

        $receivable = 0.0;

        foreach ($openSales as $openSale) {
            $receivable += Money::toFloat($openSale->total) - Money::toFloat($openSale->payments_sum_amount);
        }

        return view('customers.show', [
            'customer' => $customer,
            'sales' => $sales,
            'receivable' => number_format(max(0.0, round($receivable, 2)), 2, '.', ''),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Customer::class);

        return view('customers.form', [
            'customer' => new Customer(['is_active' => true]),
        ]);
    }

    public function store(CustomerRequest $request, CurrentBusiness $current): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $customer = new Customer($request->validated());
        $customer->business_id = $current->require()->getKey();
        $customer->is_active = $request->boolean('is_active');
        $customer->save();

        $this->audit->log(
            action: AuditAction::CustomerCreated,
            summary: 'Pelanggan "'.$customer->name.'" ditambahkan.',
            subject: $customer,
            changes: $this->audit->diff([], $customer, ['name', 'phone', 'email', 'address', 'is_active']),
        );

        return redirect()
            ->route('customers.index')
            ->with('status', 'Pelanggan "'.$customer->name.'" ditambahkan.');
    }

    public function edit(Customer $customer): View
    {
        $this->authorize('update', $customer);

        return view('customers.form', compact('customer'));
    }

    public function update(CustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $before = $customer->getAttributes();

        $customer->fill($request->validated());
        $customer->is_active = $request->boolean('is_active');
        $customer->save();

        $this->audit->log(
            action: AuditAction::CustomerUpdated,
            summary: 'Pelanggan "'.$customer->name.'" diubah.',
            subject: $customer,
            changes: $this->audit->diff($before, $customer, ['name', 'phone', 'email', 'address', 'is_active']),
        );

        return redirect()
            ->route('customers.index')
            ->with('status', 'Pelanggan "'.$customer->name.'" diperbarui.');
    }

    /**
     * Non-destruktif: pelanggan dinonaktifkan agar riwayat penjualan tetap
     * terbaca (requirements.md Bagian 4).
     */
    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        $before = $customer->getAttributes();

        $customer->is_active = false;
        $customer->save();

        $this->audit->log(
            action: AuditAction::CustomerDeactivated,
            summary: 'Pelanggan "'.$customer->name.'" dinonaktifkan.',
            subject: $customer,
            changes: $this->audit->diff($before, $customer, ['is_active']),
        );

        return redirect()
            ->route('customers.index')
            ->with('status', 'Pelanggan "'.$customer->name.'" dinonaktifkan.');
    }
}
