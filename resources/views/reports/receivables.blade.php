<x-layouts.app title="Laporan piutang --- TokoFlow">
    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
        <h1 class="text-xl font-semibold">Laporan</h1>
        <p class="mt-1 text-sm text-slate-500">
            Sisa tagihan pelanggan yang belum dibayar. Ini saldo berjalan dari seluruh penjualan selesai, jadi tidak dibatasi rentang tanggal.
        </p>

        @include('reports.partials.tabs', ['active' => 'reports.receivables'])

        @php
            $csvUrl = url()->current().'?'.http_build_query(array_filter([
                'from' => $period->fromDate(),
                'to' => $period->toDate(),
                'format' => 'csv',
            ]));
        @endphp

        @include('reports.partials.summary', ['cards' => [
            [
                'label' => 'Total piutang berjalan',
                'value' => rupiah($total),
                'hint' => $rows->count().' pelanggan masih punya sisa tagihan',
                'tone' => \App\Support\Money::toFloat($total) > 0 ? 'warning' : 'good',
            ],
        ]])

        <div class="mt-8 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Piutang per pelanggan</h2>
            <a href="{{ $csvUrl }}" class="rounded-md px-3 py-1.5 text-sm ring-1 ring-slate-300 hover:bg-white">Ekspor CSV</a>
        </div>

        <div class="mt-3 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Pelanggan</th>
                        <th class="px-4 py-3 text-right">Jumlah penjualan</th>
                        <th class="px-4 py-3 text-right">Total penjualan</th>
                        <th class="px-4 py-3 text-right">Sudah dibayar</th>
                        <th class="px-4 py-3 text-right">Sisa piutang</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($rows as $row)
                        <tr>
                            <td class="px-4 py-3">
                                @if ($row->customer_id !== null)
                                    <a href="{{ route('customers.show', $row->customer_id) }}" class="underline">{{ $row->name }}</a>
                                @else
                                    {{ $row->name }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ $row->sales_count }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($row->document_total) }}</td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($row->paid_total) }}</td>
                            <td class="px-4 py-3 text-right font-medium tabular-nums text-amber-700">{{ rupiah($row->due_total) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">
                                Semua penjualan sudah lunas. Tidak ada piutang berjalan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot class="border-t border-slate-200 bg-slate-50 font-medium">
                        <tr>
                            <td class="px-4 py-3">Total</td>
                            <td class="px-4 py-3"></td>
                            <td class="px-4 py-3"></td>
                            <td class="px-4 py-3"></td>
                            <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($total) }}</td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>

        <p class="mt-3 text-xs text-slate-500">
            Pembayaran yang dibatalkan (void) tidak dihitung sebagai pelunasan. Lihat detail pembayaran di masing-masing penjualan.
        </p>
    </main>
</x-layouts.app>
