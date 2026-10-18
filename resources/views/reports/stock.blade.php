<x-layouts.app title="Laporan stok --- TokoFlow">
    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
        <h1 class="text-xl font-semibold">Laporan</h1>
        <p class="mt-1 text-sm text-slate-500">
            Stok saat ini beserta nilainya, ditambah riwayat pergerakan stok pada rentang tanggal yang dipilih.
        </p>

        @include('reports.partials.tabs', ['active' => 'reports.stock'])

        <x-period-form :period="$period" :product-id="$productId">
            <div>
                <label for="product_id" class="block text-xs text-slate-500">Filter produk (riwayat)</label>
                <select
                    id="product_id"
                    name="product_id"
                    class="mt-1 max-w-xs rounded-md border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-300"
                >
                    <option value="">Semua produk</option>
                    @foreach ($products as $product)
                        <option value="{{ $product->id }}" @selected($productId === $product->id)>
                            {{ $product->name }} ({{ $product->sku }})
                        </option>
                    @endforeach
                </select>
            </div>
        </x-period-form>

        @include('reports.partials.summary', ['cards' => [
            [
                'label' => 'Nilai persediaan',
                'value' => rupiah($inventoryValue),
                'hint' => 'Stok x harga pokok seluruh produk, termasuk yang nonaktif',
            ],
            [
                'label' => 'Produk stok menipis',
                'value' => $lowStockCount.' produk',
                'hint' => 'Produk aktif dengan stok '.$lowStockThreshold.' atau kurang',
                'tone' => $lowStockCount > 0 ? 'warning' : 'good',
            ],
            [
                'label' => 'Jumlah produk',
                'value' => $products->count().' produk',
                'hint' => $products->where('is_active', true)->count().' aktif · '
                    .$products->where('is_active', false)->count().' nonaktif',
            ],
        ]])

        <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-slate-500">Stok saat ini</h2>

        <div class="mt-3 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Produk</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3 text-right">Stok</th>
                        <th class="px-4 py-3 text-right">Harga pokok</th>
                        <th class="px-4 py-3 text-right">Nilai</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($products as $product)
                        @php
                            $stock = \App\Support\Money::toFloat($product->stock);
                        @endphp
                        <tr>
                            <td class="px-4 py-3">
                                <a href="{{ route('products.show', $product) }}" class="underline">{{ $product->name }}</a>
                                <span class="mt-1 block font-mono text-xs text-slate-500">{{ $product->sku }}</span>
                            </td>
                            <td class="px-4 py-3">
                                @if ($product->is_active)
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700">Aktif</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums {{ $stock <= 0 ? 'text-red-700' : ($stock <= $lowStockThreshold ? 'text-amber-700' : 'text-slate-900') }}">
                                {{ angka($product->stock) }} {{ $product->unit }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($product->cost_price) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                {{ rupiah(\App\Support\Money::multiply($product->stock, $product->cost_price)) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">
                                Belum ada produk pada bisnis ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($products->isNotEmpty())
                    <tfoot class="border-t border-slate-200 bg-slate-50 font-medium">
                        <tr>
                            <td class="px-4 py-3" colspan="4">Total nilai persediaan</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($inventoryValue) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-slate-500">
            Riwayat pergerakan stok &middot; {{ $period->label() }}
        </h2>

        <div class="mt-3 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Waktu</th>
                        <th class="px-4 py-3">Produk</th>
                        <th class="px-4 py-3 text-right">Perubahan</th>
                        <th class="px-4 py-3 text-right">Saldo</th>
                        <th class="px-4 py-3">Keterangan</th>
                        <th class="px-4 py-3">Referensi</th>
                        <th class="px-4 py-3">Pengguna</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($movements as $movement)
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $movement->occurred_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3">{{ $movement->product?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums {{ $movement->isIncoming() ? 'text-emerald-700' : 'text-red-700' }}">
                                {{ $movement->isIncoming() ? '+' : '' }}{{ angka($movement->quantity) }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ angka($movement->balance_after) }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $movement->reason }}</td>
                            <td class="px-4 py-3">
                                @if ($movement->source instanceof App\Models\Purchase)
                                    <a href="{{ route('purchases.show', $movement->source) }}" class="font-mono text-xs underline">
                                        {{ $movement->source->number }}
                                    </a>
                                @elseif ($movement->source instanceof App\Models\Sale)
                                    <a href="{{ route('sales.show', $movement->source) }}" class="font-mono text-xs underline">
                                        {{ $movement->source->number }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $movement->user?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">
                                Tidak ada pergerakan stok pada rentang tanggal ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $movements->links() }}</div>
    </main>
</x-layouts.app>
