<x-layouts.app title="Pembelian --- TokoFlow">
    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Pembelian</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Draf belum mengubah stok. Stok bertambah saat penerimaan dikonfirmasi, dan utang ke supplier tercatat sejak saat itu.
                </p>
            </div>

            @can('create', App\Models\Purchase::class)
                <a href="{{ route('purchases.create') }}" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    Tambah pembelian
                </a>
            @endcan
        </div>

        @php
            $filters = [
                '' => 'Semua',
                'draft' => 'Draf',
                'received' => 'Diterima',
                'cancelled' => 'Dibatalkan',
            ];

            $paymentFilters = [
                '' => 'Semua',
                'belum' => 'Belum lunas',
                'sudah' => 'Sudah lunas',
            ];
        @endphp

        <div class="mt-6 flex flex-wrap gap-2">
            @foreach ($filters as $value => $label)
                <a href="{{ route('purchases.index', array_filter(['status' => $value, 'pembayaran' => $pembayaran])) }}"
                    class="rounded-full px-3 py-1 text-sm ring-1 {{ $status === $value
                        ? 'bg-slate-900 text-white ring-slate-900'
                        : 'bg-white text-slate-600 ring-slate-300 hover:bg-slate-50' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <div class="mt-2 flex flex-wrap items-center gap-2">
            <span class="text-xs text-slate-500">Utang:</span>
            @foreach ($paymentFilters as $value => $label)
                <a href="{{ route('purchases.index', array_filter(['status' => $status, 'pembayaran' => $value])) }}"
                    class="rounded-full px-3 py-1 text-sm ring-1 {{ $pembayaran === $value
                        ? 'bg-emerald-600 text-white ring-emerald-600'
                        : 'bg-white text-slate-600 ring-slate-300 hover:bg-slate-50' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <div class="mt-4 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Nomor</th>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3">Supplier</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-right">Dibayar</th>
                        <th class="px-4 py-3 text-right">Sisa utang</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($purchases as $purchase)
                        <tr>
                            <td class="px-4 py-3 font-mono text-xs">{{ $purchase->number }}</td>
                            <td class="px-4 py-3">{{ $purchase->purchased_at?->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">{{ $purchase->supplier?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($purchase->total) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($purchase->paidAmount()) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums {{ \App\Support\Money::toFloat($purchase->dueAmount()) > 0 ? 'text-amber-700' : 'text-slate-500' }}">
                                {{ rupiah($purchase->dueAmount()) }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($purchase->status->isReceived())
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700">Diterima</span>
                                @elseif ($purchase->status->isCancelled())
                                    <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700">Dibatalkan</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">Draf</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('purchases.show', $purchase) }}" class="text-sm underline">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-sm text-slate-500">
                                Belum ada pembelian pada bisnis ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $purchases->links() }}</div>
    </main>
</x-layouts.app>
