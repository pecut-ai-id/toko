<x-layouts.app title="Laporan penjualan --- TokoFlow">
    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
        <h1 class="text-xl font-semibold">Laporan</h1>
        <p class="mt-1 text-sm text-slate-500">
            Angka dihitung dari transaksi nyata pada rentang tanggal (inklusif). Penjualan yang dibatalkan tidak dihitung.
        </p>

        @include('reports.partials.tabs', ['active' => 'reports.sales'])

        <x-period-form :period="$period" />

        @include('reports.partials.summary', ['cards' => [
            [
                'label' => 'Omzet penjualan',
                'value' => rupiah($totals['total']),
                'hint' => $totals['count'].' transaksi pada '.$period->label(),
            ],
            [
                'label' => 'Harga pokok penjualan',
                'value' => rupiah($totals['cost']),
                'hint' => 'Harga pokok tersimpan saat penjualan dibuat',
            ],
            [
                'label' => 'Margin kotor',
                'value' => rupiah($totals['margin']),
                'hint' => 'Omzet - HPP, belum termasuk biaya operasional',
                'tone' => \App\Support\Money::toFloat($totals['margin']) < 0 ? 'danger' : 'good',
            ],
        ]])

        <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-slate-500">Penjualan per produk</h2>

        <div class="mt-3 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Produk</th>
                        <th class="px-4 py-3 text-right">Kuantitas</th>
                        <th class="px-4 py-3 text-right">Omzet</th>
                        <th class="px-4 py-3 text-right">HPP</th>
                        <th class="px-4 py-3 text-right">Margin</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($byProduct as $row)
                        <tr>
                            <td class="px-4 py-3">
                                <span class="font-mono text-xs text-slate-500">{{ $row->sku }}</span>
                                <span class="mt-1 block">{{ $row->name }}</span>
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ angka($row->quantity) }} {{ $row->unit }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($row->revenue) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($row->cost) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums {{ \App\Support\Money::toFloat($row->revenue) - \App\Support\Money::toFloat($row->cost) < 0 ? 'text-red-700' : 'text-slate-900' }}">
                                {{ rupiah(\App\Support\Money::toFloat($row->revenue) - \App\Support\Money::toFloat($row->cost)) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">
                                Tidak ada penjualan pada rentang tanggal ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($byProduct->isNotEmpty())
                    <tfoot class="border-t border-slate-200 bg-slate-50 font-medium">
                        <tr>
                            <td class="px-4 py-3">Total</td>
                            <td class="px-4 py-3"></td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($totals['total']) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($totals['cost']) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($totals['margin']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-slate-500">Penjualan per hari</h2>

        <div class="mt-3 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3 text-right">Transaksi</th>
                        <th class="px-4 py-3 text-right">Omzet</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($byDay as $row)
                        <tr>
                            <td class="px-4 py-3">{{ \Illuminate\Support\Carbon::parse($row->sold_at)->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $row->sales_count }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($row->sales_total) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-10 text-center text-sm text-slate-500">
                                Tidak ada penjualan pada rentang tanggal ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </main>
</x-layouts.app>
