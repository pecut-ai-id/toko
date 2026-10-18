<?php

namespace App\Http\Controllers;

use App\Enums\AuditAction;
use App\Exceptions\DomainException;
use App\Http\Requests\ProductRequest;
use App\Http\Requests\StockAdjustmentRequest;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\InventoryService;
use App\Support\CurrentBusiness;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * Kolom produk yang layak masuk jejak audit. `stock` tidak di sini
     * karena perubahannya punya catatannya sendiri (pergerakan stok), dan
     * `is_active` selalu disertakan agar aktivasi/nonaktivasi terlihat.
     */
    private const AUDITED_PRODUCT_FIELDS = ['sku', 'name', 'unit', 'sell_price', 'cost_price', 'is_active'];

    public function __construct(
        protected InventoryService $inventory,
        protected AuditLogger $audit,
    ) {}

    public function index(Request $request, CurrentBusiness $current): View
    {
        $this->authorize('viewAny', Product::class);

        $term = trim((string) $request->query('q'));

        $products = Product::query()
            ->forBusiness($current->require())
            ->when($term !== '', function ($query) use ($term): void {
                $query->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', "%{$term}%")
                        ->orWhere('sku', 'like', "%{$term}%");
                });
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('products.index', [
            'products' => $products,
            'term' => $term,
        ]);
    }

    public function show(Product $product, CurrentBusiness $current): View
    {
        $this->authorize('view', $product);

        $movements = InventoryMovement::query()
            ->forBusiness($current->require())
            ->forProduct($product)
            ->with(['user', 'source'])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $purchaseItems = $product->purchaseItems()
            ->with('purchase.supplier')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        return view('products.show', [
            'product' => $product,
            'movements' => $movements,
            'purchaseItems' => $purchaseItems,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Product::class);

        return view('products.form', [
            'product' => new Product(['unit' => 'pcs', 'is_active' => true]),
        ]);
    }

    public function store(ProductRequest $request, CurrentBusiness $current): RedirectResponse
    {
        $this->authorize('create', Product::class);

        $product = new Product($request->validated());
        $product->business_id = $current->require()->getKey();
        $product->is_active = $request->boolean('is_active');
        $product->save();

        $this->audit->log(
            action: AuditAction::ProductCreated,
            summary: 'Produk "'.$product->name.'" ditambahkan.',
            subject: $product,
            changes: $this->audit->diff([], $product, self::AUDITED_PRODUCT_FIELDS),
        );

        return redirect()
            ->route('products.index')
            ->with('status', 'Produk "'.$product->name.'" ditambahkan.');
    }

    public function edit(Product $product): View
    {
        $this->authorize('update', $product);

        return view('products.form', compact('product'));
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $before = $product->getAttributes();

        $product->fill($request->validated());
        $product->is_active = $request->boolean('is_active');
        $product->save();

        $this->audit->log(
            action: AuditAction::ProductUpdated,
            summary: 'Produk "'.$product->name.'" diubah.',
            subject: $product,
            changes: $this->audit->diff($before, $product, self::AUDITED_PRODUCT_FIELDS),
        );

        return redirect()
            ->route('products.index')
            ->with('status', 'Produk "'.$product->name.'" diperbarui.');
    }

    /**
     * Non-destruktif: produk dinonaktifkan, bukan dihapus, agar riwayat
     * transaksi tetap terbaca (requirements.md Bagian 4).
     */
    public function destroy(Product $product): RedirectResponse
    {
        $this->authorize('delete', $product);

        $before = $product->getAttributes();

        $product->is_active = false;
        $product->save();

        $this->audit->log(
            action: AuditAction::ProductDeactivated,
            summary: 'Produk "'.$product->name.'" dinonaktifkan.',
            subject: $product,
            changes: $this->audit->diff($before, $product, ['is_active']),
        );

        return redirect()
            ->route('products.index')
            ->with('status', 'Produk "'.$product->name.'" dinonaktifkan.');
    }

    /**
     * Penyesuaian stok manual: stok awal, koreksi hitung, atau barang
     * rusak/hilang.
     *
     * Tetap lewat InventoryService, jadi satu-satunya jalur perubahan
     * stok tetap satu pintu: pergerakan tercatat, stok tidak pernah
     * negatif, dan produknya dikunci saat diubah (D-013, D-028).
     */
    public function adjustStock(
        StockAdjustmentRequest $request,
        Product $product,
    ): RedirectResponse {
        $this->authorize('adjustStock', $product);

        /** @var User $user */
        $user = $request->user();

        $data = $request->validated();
        $stockBefore = $product->stock;
        $quantity = Money::toFloat($data['quantity']);

        if ($data['direction'] === 'out') {
            $quantity *= -1;
        }

        try {
            DB::transaction(function () use ($product, $quantity, $data, $user): void {
                $this->inventory->record(
                    product: $product,
                    quantity: number_format($quantity, 2, '.', ''),
                    reason: $data['reason'],
                    source: null,
                    user: $user,
                    occurredAt: filled($data['occurred_at'] ?? null)
                        ? Carbon::parse($data['occurred_at'])
                        : null,
                );
            });
        } catch (DomainException $exception) {
            return back()->withInput()->withErrors(['quantity' => $exception->getMessage()]);
        }

        $fresh = $product->fresh();

        $this->audit->log(
            action: AuditAction::StockAdjusted,
            summary: sprintf(
                'Stok "%s" disesuaikan %s%s menjadi %s. Alasan: %s',
                $product->name,
                $quantity > 0 ? '+' : '',
                angka($quantity),
                angka($fresh?->stock),
                $data['reason'],
            ),
            subject: $product,
            changes: $this->audit->diff(['stock' => $stockBefore], $fresh ?? $product, ['stock']),
        );

        return redirect()
            ->route('products.show', $product)
            ->with('status', 'Stok "'.$product->name.'" disesuaikan menjadi '.angka($fresh?->stock).'.');
    }
}
