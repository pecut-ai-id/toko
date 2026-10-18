<x-layouts.app title="Laporan pembelian --- TokoFlow">
    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
        <h1 class="text-xl font-semibold">Laporan</h1>
        <p class="mt-1 text-sm text-slate-500">
            Hanya pembelian yang sudah <strong>diterima</strong> yang dihitung, karena hanya penerimaan yang menambah stok dan menimbulkan utang.
        </p>

        @include('reports.partials.tabs', ['active' => 'reports.purchases'])

        <x-period-form :period="$period" />

        @include('reports.partials.summary', ['cards' => [
            [
                'label' => 'Total pembelian diterima',
                'value' => rupiah($totals['total']),
                'hint' => $totals['count'].' pembelian pada '.$period->label(),
            ],
        ]])

        <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-slate-500">Pembelian per supplier</h2>

        <div class="mt-3 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Supplier</th>
                        <th class="px-4 py-3 text-right">Jumlah pembelian</th>
                        <th class="px-4 py-3 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($bySupplier as $row)
                        <tr>
                            <td class="px-4 py-3">{{ $row->name }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $row->purchase_count }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($row->purchase_total) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-10 text-center text-sm text-slate-500">
                                Tidak ada pembelian yang diterima pada rentang tanggal ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($bySupplier->isNotEmpty())
                    <tfoot class="border-t border-slate-200 bg-slate-50 font-medium">
                        <tr>
                            <td class="px-4 py-3">Total</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $totals['count'] }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($totals['total']) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-slate-500">Pembelian per hari</h2>

        <div class="mt-3 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Tanggal</th>
                        <th class="px-4 py-3 text-right">Pembelian</th>
                        <th class="px-4 py-3 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($byDay as $row)
                        <tr>
                            <td class="px-4 py-3">{{ \Illuminate\Support\Carbon::parse($row->purchased_at)->format('d/m/Y') }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $row->purchase_count }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($row->purchase_total) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-10 text-center text-sm text-slate-500">
                                Tidak ada pembelian yang diterima pada rentang tanggal ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </main>
</x-layouts.app>
