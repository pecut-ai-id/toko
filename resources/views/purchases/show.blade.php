<x-layouts.app :title="'Pembelian '.$purchase->number.' --- TokoFlow'">
    <main class="mx-auto w-full max-w-4xl flex-1 px-4 py-8">
        <a href="{{ route('purchases.index') }}" class="text-sm text-slate-500 underline">&larr; Kembali ke pembelian</a>

        <div class="mt-3 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="font-mono text-xl font-semibold">{{ $purchase->number }}</h1>
                <p class="mt-1 text-sm text-slate-500">
                    {{ $purchase->supplier?->name ?? 'Tanpa supplier' }}
                    &middot; {{ $purchase->purchased_at?->format('d/m/Y') }}
                </p>
            </div>

            @if ($purchase->status->isReceived())
                <span class="rounded-full bg-emerald-50 px-3 py-1 text-sm text-emerald-700">Diterima</span>
            @elseif ($purchase->status->isCancelled())
                <span class="rounded-full bg-red-50 px-3 py-1 text-sm text-red-700">Dibatalkan</span>
            @else
                <span class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-600">Draf</span>
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
                <dt class="text-xs uppercase tracking-wide text-slate-500">Total</dt>
                <dd class="mt-1 text-lg font-semibold tabular-nums">{{ rupiah($purchase->total) }}</dd>
            </div>
            @if ($purchase->isOpen())
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Dibayar</dt>
                    <dd class="mt-1 text-lg tabular-nums">{{ rupiah($purchase->paidAmount()) }}</dd>
                </div>
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Sisa utang</dt>
                    <dd class="mt-1 text-lg font-semibold tabular-nums {{ \App\Support\Money::toFloat($purchase->dueAmount()) > 0 ? 'text-amber-700' : 'text-emerald-700' }}">
                        {{ rupiah($purchase->dueAmount()) }}
                    </dd>
                </div>
            @endif
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">Dibuat</dt>
                <dd class="mt-1 text-sm">{{ $purchase->created_at?->format('d/m/Y H:i') }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">Diterima</dt>
                <dd class="mt-1 text-sm">
                    @if ($purchase->received_at)
                        {{ $purchase->received_at->format('d/m/Y H:i') }}
                        @if ($purchase->receiver)
                            <span class="text-slate-500">oleh {{ $purchase->receiver->name }}</span>
                        @endif
                    @else
                        —
                    @endif
                </dd>
            </div>

            @if ($purchase->cancelled_at)
                <div>
                    <dt class="text-xs uppercase tracking-wide text-slate-500">Dibatalkan</dt>
                    <dd class="mt-1 text-sm">
                        {{ $purchase->cancelled_at->format('d/m/Y H:i') }}
                        @if ($purchase->canceller)
                            <span class="text-slate-500">oleh {{ $purchase->canceller->name }}</span>
                        @endif
                    </dd>
                </div>
            @endif
        </dl>

        @if ($purchase->status->isDraft())
            <p class="mt-3 text-xs text-slate-500">
                Utang ke supplier baru tercatat setelah penerimaan dikonfirmasi.
            </p>
        @endif

        @if (filled($purchase->notes))
            <div class="mt-4 rounded-xl bg-white p-4 text-sm ring-1 ring-slate-200">
                <span class="text-xs uppercase tracking-wide text-slate-500">Catatan</span>
                <p class="mt-1 whitespace-pre-line">{{ $purchase->notes }}</p>
            </div>
        @endif

        <h2 class="mt-6 text-sm font-semibold uppercase tracking-wide text-slate-500">Item</h2>

        <div class="mt-2 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Produk</th>
                        <th class="px-4 py-3 text-right">Kuantitas</th>
                        <th class="px-4 py-3 text-right">Harga beli</th>
                        <th class="px-4 py-3 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($purchase->items as $item)
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
                            <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($item->unit_cost) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($item->subtotal) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="bg-slate-50 text-sm">
                    <tr>
                        <th colspan="3" class="px-4 py-3 text-right">Total</th>
                        <td class="px-4 py-3 text-right font-semibold tabular-nums">{{ rupiah($purchase->total) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>

        @if ($purchase->movements->isNotEmpty())
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
                            <th class="px-4 py-3">Pengguna</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($purchase->movements->sortByDesc('occurred_at') as $movement)
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $movement->occurred_at?->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3">{{ $movement->product?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-right tabular-nums {{ $movement->isIncoming() ? 'text-emerald-700' : 'text-red-700' }}">
                                    {{ $movement->isIncoming() ? '+' : '' }}{{ angka($movement->quantity) }}
                                </td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ angka($movement->balance_after) }}</td>
                                <td class="px-4 py-3 text-slate-500">{{ $movement->reason }}</td>
                                <td class="px-4 py-3">{{ $movement->user?->name ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @include('partials.payments', ['document' => $purchase, 'storeRoute' => 'purchases.payments.store'])

        <div class="mt-6 flex flex-wrap items-center gap-4">
            @if ($purchase->canBeReceived())
                @can('receive', $purchase)
                    <form method="POST" action="{{ route('purchases.receive', $purchase) }}"
                        onsubmit="return confirm('Konfirmasi penerimaan {{ $purchase->number }}? Stok akan bertambah.')">
                        @csrf
                        <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                            Konfirmasi penerimaan
                        </button>
                    </form>
                @endcan
            @endif

            @if ($purchase->canBeCancelled())
                @can('cancel', $purchase)
                    <form method="POST" action="{{ route('purchases.cancel', $purchase) }}"
                        onsubmit="return confirm('Batalkan {{ $purchase->number }}? Bila sudah diterima, stok akan dikembalikan lewat pergerakan reversal.')">
                        @csrf
                        <button type="submit" class="text-sm text-red-600 underline">Batalkan pembelian</button>
                    </form>
                @endcan
            @elseif ($purchase->hasActivePayments())
                <p class="text-xs text-slate-500">
                    Pembelian ini masih punya pembayaran yang berlaku sehingga belum bisa dibatalkan.
                    Batalkan (void) pembayarannya lebih dulu.
                </p>
            @endif
        </div>
    </main>
</x-layouts.app>
