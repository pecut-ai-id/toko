<x-layouts.app :title="$customer->name.' --- TokoFlow'">
    <main class="mx-auto w-full max-w-4xl flex-1 px-4 py-8">
        <a href="{{ route('customers.index') }}" class="text-sm text-slate-500 underline">&larr; Kembali ke pelanggan</a>

        <div class="mt-3 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">{{ $customer->name }}</h1>
                <p class="mt-1 text-sm text-slate-500">Detail pelanggan/reseller.</p>
            </div>

            <div class="flex items-center gap-2">
                @if ($customer->is_active)
                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-sm text-emerald-700">Aktif</span>
                @else
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-500">Nonaktif</span>
                @endif

                @can('update', $customer)
                    <a href="{{ route('customers.edit', $customer) }}" class="rounded-md px-3 py-1.5 text-sm ring-1 ring-slate-300 hover:bg-white">
                        Ubah
                    </a>
                @endcan
            </div>
        </div>

        <dl class="mt-6 grid gap-4 rounded-xl bg-white p-6 ring-1 ring-slate-200 sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">Telepon</dt>
                <dd class="mt-1 text-sm">{{ $customer->phone ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">Email</dt>
                <dd class="mt-1 text-sm">{{ $customer->email ?? '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs uppercase tracking-wide text-slate-500">Alamat</dt>
                <dd class="mt-1 text-sm whitespace-pre-line">{{ $customer->address ?? '—' }}</dd>
            </div>
        </dl>

        <div class="mt-6 rounded-xl bg-white p-6 ring-1 ring-slate-200">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <span class="text-xs uppercase tracking-wide text-slate-500">Total piutang</span>
                <span class="text-lg font-semibold tabular-nums {{ \App\Support\Money::toFloat($receivable) > 0 ? 'text-amber-700' : 'text-emerald-700' }}">
                    {{ rupiah($receivable) }}
                </span>
            </div>
            <p class="mt-1 text-xs text-slate-500">
                Dihitung dari seluruh penjualan yang belum dibatalkan dikurangi pembayaran tercatat.
            </p>
        </div>

        <h2 class="mt-6 text-sm font-semibold uppercase tracking-wide text-slate-500">Riwayat penjualan</h2>

        <div class="mt-2 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Nomor</th>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-right">Dibayar</th>
                        <th class="px-4 py-3 text-right">Sisa</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($sales as $sale)
                        <tr>
                            <td class="px-4 py-3">
                                <a href="{{ route('sales.show', $sale) }}" class="font-mono text-xs underline">
                                    {{ $sale->number }}
                                </a>
                            </td>
                            <td class="px-4 py-3">{{ $sale->sold_at?->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($sale->total) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($sale->paidAmount()) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($sale->dueAmount()) }}</td>
                            <td class="px-4 py-3">{{ $sale->status->label() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">
                                Pelanggan ini belum punya penjualan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </main>
</x-layouts.app>
