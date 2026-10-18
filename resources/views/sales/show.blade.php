<x-layouts.app :title="'Penjualan '.$sale->number.' --- TokoFlow'">
    <main class="mx-auto w-full max-w-4xl flex-1 px-4 py-8">
        <a href="{{ route('sales.index') }}" class="text-sm text-slate-500 underline">&larr; Kembali ke penjualan</a>

        <div class="mt-3 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="font-mono text-xl font-semibold">{{ $sale->number }}</h1>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $sale->customer?->name ?? 'Tanpa pelanggan' }}
                    &middot; {{ $sale->sold_at?->format('d/m/Y') }}
                </p>
            </div>

            @if ($sale->status->isCancelled())
                <span class="rounded-full bg-red-50 px-3 py-1 text-sm text-red-700">Dibatalkan</span>
            @else
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-sm text-emerald-700">Selesai</span>
            @endif
        </div>

        @if ($errors->any())
            <div class="mt-4 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-200">
                <ul class="list-disc space-y-1 pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <dl class="mt-6 grid gap-4 rounded-xl bg-white p-6 ring-1 ring-slate-200 sm:grid-cols-3">
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">Total penjualan</dt>
                <dd class="mt-1 text-lg font-semibold tabular-nums">{{ rupiah($sale->totalAmount()) }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">Dibayar</dt>
                <dd class="mt-1 text-lg tabular-nums">{{ rupiah($sale->paidAmount()) }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">Sisa piutang</dt>
                <dd class="mt-1 text-lg font-semibold tabular-nums {{ \App\Support\Money::toFloat($sale->dueAmount()) > 0 ? 'text-amber-700' : 'text-emerald-700' }}">
                    {{ rupiah($sale->dueAmount()) }}
                </dd>
            </div>
        </dl>

        <dl class="mt-4 grid gap-4 rounded-xl bg-white p-6 ring-1 ring-slate-200 sm:grid-cols-3">
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">HPP (snapshot)</dt>
                <dd class="mt-1 text-sm tabular-nums">{{ rupiah($sale->costTotal()) }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">Margin kotor</dt>
                <dd class="mt-1 text-sm tabular-nums">{{ rupiah($sale->marginTotal()) }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">Total kuantitas</dt>
                <dd class="mt-1 text-sm tabular-nums">{{ angka($sale->totalQuantity()) }}</dd>
            </div>
        </dl>

        @if (filled($sale->notes))
            <div class="mt-4 rounded-xl bg-white p-4 text-sm ring-1 ring-slate-200">
                <span class="text-xs uppercase tracking-wide text-slate-500">Catatan</span>
                <p class="mt-1 whitespace-pre-line">{{ $sale->notes }}</p>
            </div>
        @endif

        @if ($sale->cancelled_at)
            <div class="mt-4 rounded-xl bg-white p-4 text-sm ring-1 ring-slate-200">
                <span class="text-xs uppercase tracking-wide text-slate-500">Dibatalkan</span>
                <p class="mt-1">
                    {{ $sale->cancelled_at->format('d/m/Y H:i') }}
                    @if ($sale->canceller)
                        <span class="text-slate-500">oleh {{ $sale->canceller->name }}</span>
                    @endif
                </p>
            </div>
        @endif

        <h2 class="mt-6 text-sm font-semibold uppercase tracking-wide text-slate-500">Item</h2>

        <div class="mt-2 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Produk</th>
                        <th class="px-4 py-3 text-right">Kuantitas</th>
                        <th class="px-4 py-3 text-right">Harga jual</th>
                        <th class="px-4 py-3 text-right">Harga pokok</th>
                        <th class="px-4 py-3 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($sale->items as $item)
                        <tr>
                            <td class="px-4 py-3">
                                @if ($item->product)
                                    <a href="{{ route('products.show', $item->product) }}" class="underline">
                                        {{ $item->product->name }}
                                    </a>
                                    <span class="ml-1 font-mono text-xs text-slate-500">{{ $item->product->sku }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">
                                {{ angka($item->quantity) }} {{ $item->product?->unit }}
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($item->unit_price) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums text-slate-500">{{ rupiah($item->unit_cost) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($item->subtotal) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-slate-50 text-sm">
                    <tr>
                        <th colspan="4" class="px-4 py-3 text-right">Total</th>
                        <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ rupiah($sale->totalAmount()) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @include('partials.payments', ['document' => $sale, 'storeRoute' => 'sales.payments.store'])

        @if ($sale->movements->isNotEmpty())
            <h2 class="mt-6 text-sm font-semibold uppercase tracking-wide text-slate-500">Pergerakan stok</h2>

            <div class="mt-2 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Waktu</th>
                            <th class="px-4 py-3">Produk</th>
                            <th class="px-4 py-3 text-right">Perubahan</th>
                            <th class="px-4 py-3 text-right">Saldo</th>
                            <th class="px-4 py-3">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($sale->movements->sortBy('occurred_at') as $movement)
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $movement->occurred_at?->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3">{{ $movement->product?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-right tabular-nums {{ $movement->isIncoming() ? 'text-emerald-700' : 'text-red-700' }}">
                                    {{ $movement->isIncoming() ? '+' : '' }}{{ angka($movement->quantity) }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ angka($movement->balance_after) }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $movement->reason }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($sale->canBeCancelled())
            @can('cancel', $sale)
                <div class="mt-6">
                    <form method="POST" action="{{ route('sales.cancel', $sale) }}"
                        onsubmit="return confirm('Batalkan {{ $sale->number }}? Stok akan dikembalikan lewat pergerakan reversal.')">
                        @csrf
                        <button type="submit" class="text-sm text-red-600 underline">Batalkan penjualan</button>
                    </form>
                    <p class="mt-1 text-xs text-slate-500">
                        Stok akan dikembalikan lewat pergerakan reversal yang tercatat, bukan dengan menghapus dokumen.
                    </p>
                </div>
            @endcan
        @elseif ($sale->hasActivePayments())
            <div class="mt-6">
                <p class="text-xs text-slate-500">
                    Penjualan ini masih punya pembayaran yang berlaku sehingga belum bisa dibatalkan.
                    Batalkan (void) pembayarannya lebih dulu bila penjualan ini memang harus dibatalkan.
                </p>
            </div>
        @endif
    </main>
</x-layouts.app>
