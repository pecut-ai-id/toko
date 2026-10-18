<x-layouts.app title="Produk --- TokoFlow">
    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Produk</h1>
                <p class="mt-1 text-sm text-slate-500">Daftar produk pada bisnis aktif.</p>
            </div>

            @can('create', App\Models\Product::class)
                <a href="{{ route('products.create') }}" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    Tambah produk
                </a>
            @endcan
        </div>

        <form method="GET" action="{{ route('products.index') }}" class="mt-6 flex flex-wrap gap-2">
            <input
                name="q"
                value="{{ $term }}"
                placeholder="Cari nama atau SKU"
                class="w-full max-w-xs rounded-md border-0 bg-white px-3 py-2 text-sm ring-1 ring-slate-300"
            >
            <button type="submit" class="rounded-md px-3 py-2 text-sm ring-1 ring-slate-300 hover:bg-white">
                Cari
            </button>
        </form>

        <div class="mt-6 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">SKU</th>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Satuan</th>
                        <th class="px-4 py-3 text-right">Stok</th>
                        <th class="px-4 py-3 text-right">Harga jual</th>
                        <th class="px-4 py-3 text-right">Harga pokok</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($products as $product)
                        <tr>
                            <td class="px-4 py-3 font-mono text-xs">{{ $product->sku }}</td>
                            <td class="px-4 py-3">
                                <a href="{{ route('products.show', $product) }}" class="underline">{{ $product->name }}</a>
                            </td>
                            <td class="px-4 py-3">{{ $product->unit }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ angka($product->stock) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($product->sell_price) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($product->cost_price) }}</td>
                            <td class="px-4 py-3">
                                @if ($product->is_active)
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700">Aktif</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('products.show', $product) }}" class="text-sm underline">Detail</a>
                                @can('update', $product)
                                    <a href="{{ route('products.edit', $product) }}" class="ml-3 text-sm underline">Ubah</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-sm text-slate-500">
                                Belum ada produk pada bisnis ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $products->links() }}</div>
    </main>
</x-layouts.app>
