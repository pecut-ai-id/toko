<x-layouts.app :title="$supplier->name.' --- TokoFlow'">
    <main class="mx-auto w-full max-w-4xl flex-1 px-4 py-8">
        <a href="{{ route('suppliers.index') }}" class="text-sm text-slate-500 underline">&larr; Kembali ke supplier</a>

        <div class="mt-3 flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">{{ $supplier->name }}</h1>
                <p class="mt-1 text-sm text-slate-500">Detail supplier dan riwayat pembelian.</p>
            </div>

            <div class="flex items-center gap-2">
                @if ($supplier->is_active)
                    <span class="rounded-full bg-emerald-50 px-3 py-1 text-sm text-emerald-700">Aktif</span>
                @else
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-500">Nonaktif</span>
                @endif

                @can('update', $supplier)
                    <a href="{{ route('suppliers.edit', $supplier) }}" class="rounded-md px-3 py-1.5 text-sm ring-1 ring-slate-300 hover:bg-white">
                        Ubah
                    </a>
                @endcan
            </div>
        </div>

        <dl class="mt-6 grid gap-4 rounded-xl bg-white p-6 ring-1 ring-slate-200 sm:grid-cols-2">
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">Telepon</dt>
                <dd class="mt-1 text-sm">{{ $supplier->phone ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-slate-500">Email</dt>
                <dd class="mt-1 text-sm">{{ $supplier->email ?? '—' }}</dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs uppercase tracking-wide text-slate-500">Alamat</dt>
                <dd class="mt-1 text-sm whitespace-pre-line">{{ $supplier->address ?? '—' }}</dd>
            </div>
        </dl>

        <div class="mt-4 rounded-xl bg-white p-4 ring-1 ring-slate-200">
            <span class="text-xs uppercase tracking-wide text-slate-500">Total utang berjalan</span>
            <p class="mt-1 text-lg font-semibold tabular-nums {{ \App\Support\Money::toFloat($debt) > 0 ? 'text-amber-700' : 'text-emerald-700' }}">
                {{ rupiah($debt) }}
            </p>
            <p class="mt-1 text-xs text-slate-500">
                Dihitung dari pembelian yang sudah diterima dikurangi pembayaran yang masih berlaku.
                Draf dan pembelian yang dibatalkan tidak dihitung.
            </p>
        </div>

        <h2 class="mt-6 text-sm font-semibold uppercase tracking-wide text-slate-500">Riwayat pembelian</h2>

        <div class="mt-2 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Nomor</th>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-right">Sisa utang</th>
                        <th class="px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($purchases as $purchase)
                        <tr>
                            <td class="px-4 py-3">
                                <a href="{{ route('purchases.show', $purchase) }}" class="font-mono text-xs underline">
                                    {{ $purchase->number }}
                                </a>
                            </td>
                            <td class="px-4 py-3">{{ $purchase->purchased_at?->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($purchase->total) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($purchase->dueAmount()) }}</td>
                            <td class="px-4 py-3">{{ $purchase->status->label() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">
                                Supplier ini belum punya pembelian.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </main>
</x-layouts.app>
