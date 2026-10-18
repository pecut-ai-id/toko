<x-layouts.app :title="$product->name.' --- TokoFlow'">
    <main class="mx-auto w-full max-w-4xl flex-1 px-4 py-8">
        <a href="{{ route('products.index') }}" class="text-sm text-slate-500 underline">&larr; Kembali ke produk</a>

        <div class="mt-3 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">{{ $product->name }}</h1>
                <p class="mt-1 font-mono text-sm text-slate-500">{{ $product->sku }}</p>
            </div>

            <div class="flex items-center gap-2">
                @if ($product->is_active)
                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-sm text-emerald-700">Aktif</span>
                @else
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-500">Nonaktif</span>
                @endif

                @can('update', $product)
                    <a href="{{ route('products.edit', $product) }}" class="rounded-md px-3 py-1.5 text-sm ring-1 ring-slate-300 hover:bg-white">
                        Ubah
                    </a>
                @endcan
            </div>
        </div>

        <dl class="mt-6 grid gap-4 rounded-xl bg-white p-6 ring-1 ring-slate-200 sm:grid-cols-4">
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">Stok saat ini</dt>
                <dd class="mt-1 text-lg font-semibold tabular-nums">{{ angka($product->stock) }} {{ $product->unit }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">Satuan</dt>
                <dd class="mt-1 text-sm">{{ $product->unit }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">Harga jual</dt>
                <dd class="mt-1 text-sm tabular-nums">{{ rupiah($product->sell_price) }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">Harga pokok</dt>
                <dd class="mt-1 text-sm tabular-nums">{{ rupiah($product->cost_price) }}</dd>
            </div>
        </dl>

        @can('adjustStock', $product)
            <div class="mt-4 rounded-xl bg-white p-6 ring-1 ring-slate-200">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Penyesuaian stok</h2>
                <p class="mt-1 text-xs text-slate-500">
                    Untuk stok awal, koreksi hitung, atau barang rusak/hilang. Setiap penyesuaian meninggalkan
                    satu baris di riwayat pergerakan stok beserta alasannya. Stok tidak boleh menjadi negatif.
                </p>

                @if ($errors->any())
                    <div class="mt-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-200">
                        <ul class="list-disc space-y-1 pl-4">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form
                    method="POST"
                    action="{{ route('products.stock-adjustments.store', $product) }}"
                    class="mt-4 grid gap-4 sm:grid-cols-4"
                >
                    @csrf

                    <div>
                        <label for="direction" class="block text-xs text-slate-500">Arah</label>
                        <select
                            id="direction"
                            name="direction"
                            class="mt-1 block w-full rounded-md border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-300"
                        >
                            <option value="in" @selected(old('direction', 'in') === 'in')>Masuk (+)</option>
                            <option value="out" @selected(old('direction') === 'out')>Keluar (&minus;)</option>
                        </select>
                    </div>

                    <div>
                        <label for="quantity" class="block text-xs text-slate-500">Jumlah</label>
                        <input
                            id="quantity"
                            name="quantity"
                            value="{{ old('quantity') }}"
                            inputmode="decimal"
                            placeholder="0"
                            required
                            class="mt-1 block w-full rounded-md border-0 bg-slate-50 px-3 py-2 text-right text-sm ring-1 ring-slate-300"
                        >
                    </div>

                    <div class="sm:col-span-2">
                        <label for="reason" class="block text-xs text-slate-500">Alasan</label>
                        <input
                            id="reason"
                            name="reason"
                            value="{{ old('reason') }}"
                            list="adjustment-reasons"
                            required
                            minlength="3"
                            maxlength="255"
                            placeholder="Stok awal / koreksi hitung / barang rusak"
                            class="mt-1 block w-full rounded-md border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-300"
                        >
                        <datalist id="adjustment-reasons">
                            <option value="Stok awal"></option>
                            <option value="Koreksi hitung stok"></option>
                            <option value="Barang rusak"></option>
                            <option value="Barang hilang"></option>
                            <option value="Retur ke supplier"></option>
                        </datalist>
                    </div>

                    <div>
                        <label for="occurred_at" class="block text-xs text-slate-500">Tanggal (opsional)</label>
                        <input
                            type="date"
                            id="occurred_at"
                            name="occurred_at"
                            value="{{ old('occurred_at') }}"
                            class="mt-1 block w-full rounded-md border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-300"
                        >
                    </div>

                    <div class="flex items-end sm:col-span-3">
                        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                            Catat penyesuaian
                        </button>
                    </div>
                </form>
            </div>
        @endcan

        <h2 class="mt-6 text-sm font-semibold uppercase tracking-wide text-slate-500">
            Riwayat pergerakan stok
        </h2>

        <div class="mt-2 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Waktu</th>
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
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">
                                Belum ada pergerakan stok untuk produk ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <h2 class="mt-6 text-sm font-semibold uppercase tracking-wide text-slate-500">
            Riwayat pembelian
        </h2>

        <div class="mt-2 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Nomor</th>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Supplier</th>
                        <th class="px-4 py-3 text-right">Kuantitas</th>
                        <th class="px-4 py-3 text-right">Harga beli</th>
                        <th class="px-4 py-3 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($purchaseItems as $item)
                        <tr>
                            <td class="px-4 py-3">
                                @if ($item->purchase)
                                    <a href="{{ route('purchases.show', $item->purchase) }}" class="font-mono text-xs underline">
                                        {{ $item->purchase->number }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $item->purchase?->purchased_at?->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">{{ $item->purchase?->supplier?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ angka($item->quantity) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($item->unit_cost) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($item->subtotal) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">
                                Produk ini belum pernah dibeli.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </main>
</x-layouts.app>
