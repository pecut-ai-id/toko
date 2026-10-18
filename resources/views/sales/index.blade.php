<x-layouts.app title="Penjualan --- TokoFlow">
    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Penjualan</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Stok berkurang saat penjualan disimpan. Sisa piutang dihitung dari pembayaran yang tercatat.
                </p>
            </div>

            @can('create', App\Models\Sale::class)
                <a href="{{ route('sales.create') }}" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    Tambah penjualan
                </a>
            @endcan
        </div>

        @php
            $filters = [
                '' => 'Semua',
                'completed' => 'Selesai',
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
                <a href="{{ route('sales.index', array_filter(['status' => $value, 'pembayaran' => $pembayaran])) }}"
                    class="rounded-full px-3 py-1 text-sm ring-1 {{ $status === $value
                        ? 'bg-slate-900 text-white ring-slate-900'
                        : 'bg-white text-slate-600 ring-slate-300 hover:bg-slate-50' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <div class="mt-2 flex flex-wrap items-center gap-2">
            <span class="text-xs text-slate-500">Pembayaran:</span>
            @foreach ($paymentFilters as $value => $label)
                <a href="{{ route('sales.index', array_filter(['status' => $status, 'pembayaran' => $value])) }}"
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
                        <th class="px-4 py-3">Pelanggan</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-right">Dibayar</th>
                        <th class="px-4 py-3 text-right">Sisa piutang</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($sales as $sale)
                        <tr>
                            <td class="px-4 py-3 font-mono text-xs">{{ $sale->number }}</td>
                            <td class="px-4 py-3">{{ $sale->sold_at?->format('d/m/Y') }}</td>
                            <td class="px-4 py-3">{{ $sale->customer?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($sale->total) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($sale->paidAmount()) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums {{ \App\Support\Money::toFloat($sale->dueAmount()) > 0 ? 'font-medium text-amber-700' : '' }}">
                                {{ rupiah($sale->dueAmount()) }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($sale->status->isCancelled())
                                    <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs text-red-700">Dibatalkan</span>
                                @else
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700">Selesai</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('sales.show', $sale) }}" class="text-sm underline">Detail</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-sm text-slate-500">
                                Belum ada penjualan pada bisnis ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $sales->links() }}</div>
    </main>
</x-layouts.app>
