<x-layouts.app :title="'Dashboard --- TokoFlow'">
    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Dashboard</h1>
                <p class="mt-1 text-sm text-slate-500">
                    Bisnis aktif: {{ $business->name }}
                    &middot; periode {{ $summary['period']->label() }}
                </p>
            </div>

            <a href="{{ route('reports.sales') }}" class="rounded-md px-3 py-1.5 text-sm ring-1 ring-slate-300 hover:bg-white">
                Buka laporan
            </a>
        </div>

        @php
            $salesToday = $summary['salesToday'];
            $salesMonth = $summary['salesMonth'];
            $purchasesMonth = $summary['purchasesMonth'];
        @endphp

        @include('reports.partials.summary', ['cards' => [
            [
                'label' => 'Penjualan hari ini',
                'value' => rupiah($salesToday['total']),
                'hint' => $salesToday['count'].' transaksi',
            ],
            [
                'label' => 'Penjualan bulan ini',
                'value' => rupiah($salesMonth['total']),
                'hint' => $salesMonth['count'].' transaksi · margin kotor '.rupiah($salesMonth['margin']),
            ],
            [
                'label' => 'Pembelian diterima bulan ini',
                'value' => rupiah($purchasesMonth['total']),
                'hint' => $purchasesMonth['count'].' pembelian diterima',
            ],
            [
                'label' => 'Piutang berjalan',
                'value' => rupiah($summary['receivable']),
                'hint' => 'Sisa tagihan pelanggan yang belum dibayar',
                'tone' => \App\Support\Money::toFloat($summary['receivable']) > 0 ? 'warning' : 'good',
            ],
            [
                'label' => 'Utang berjalan',
                'value' => rupiah($summary['payable']),
                'hint' => 'Sisa utang ke supplier yang belum dibayar',
                'tone' => \App\Support\Money::toFloat($summary['payable']) > 0 ? 'warning' : 'good',
            ],
            [
                'label' => 'Nilai persediaan',
                'value' => rupiah($summary['inventoryValue']),
                'hint' => 'Total stok x harga pokok produk',
            ],
        ]])

        <details class="mt-4 rounded-xl bg-white p-4 text-sm ring-1 ring-slate-200">
            <summary class="cursor-pointer text-slate-600">Definisi metrik</summary>
            <ul class="mt-3 list-disc space-y-1 pl-5 text-xs text-slate-500">
                <li><strong>Penjualan</strong> = total penjualan berstatus selesai pada rentang tanggalnya. Penjualan yang dibatalkan tidak dihitung.</li>
                <li><strong>Pembelian</strong> = total pembelian yang sudah <em>diterima</em>. Draf dan pembelian yang dibatalkan tidak dihitung.</li>
                <li><strong>Margin kotor</strong> = penjualan - harga pokok penjualan (HPP), dihitung dari harga pokok yang tersimpan saat penjualan dibuat. Bukan laba bersih.</li>
                <li><strong>Piutang/utang</strong> = saldo berjalan dari semua dokumen, bukan hanya periode ini. Pembayaran yang dibatalkan (void) tidak dihitung.</li>
                <li><strong>Nilai persediaan</strong> = stok x harga pokok, termasuk produk nonaktif karena barangnya masih ada.</li>
                <li><strong>Stok menipis</strong> = produk aktif dengan stok {{ $summary['lowStockThreshold'] }} atau kurang.</li>
            </ul>
        </details>

        <div class="mt-8 grid gap-6 lg:grid-cols-2">
            <section>
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">Penjualan terbaru</h2>

                <div class="mt-3 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Nomor</th>
                                <th class="px-4 py-3">Pelanggan</th>
                                <th class="px-4 py-3 text-right">Total</th>
                                <th class="px-4 py-3 text-right">Sisa</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($summary['recentSales'] as $sale)
                                <tr>
                                    <td class="px-4 py-3">
                                        <a href="{{ route('sales.show', $sale) }}" class="font-mono text-xs underline">{{ $sale->number }}</a>
                                        <span class="mt-1 block text-xs text-slate-500">{{ $sale->sold_at?->format('d/m/Y') }}</span>
                                    </td>
                                    <td class="px-4 py-3">{{ $sale->customer?->name ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($sale->total) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($sale->dueAmount()) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-sm text-slate-500">
                                        Belum ada penjualan pada bisnis ini.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="space-y-6">
                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">
                        Perlu restock ({{ $summary['lowStockCount'] }})
                    </h2>

                    <div class="mt-3 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Produk</th>
                                    <th class="px-4 py-3 text-right">Stok</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($summary['lowStock'] as $product)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <a href="{{ route('products.show', $product) }}" class="underline">{{ $product->name }}</a>
                                            <span class="mt-1 block font-mono text-xs text-slate-500">{{ $product->sku }}</span>
                                        </td>
                                        <td class="px-4 py-3 text-right tabular-nums {{ \App\Support\Money::toFloat($product->stock) <= 0 ? 'text-red-700' : 'text-amber-700' }}">
                                            {{ angka($product->stock) }} {{ $product->unit }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="px-4 py-6 text-center text-sm text-slate-500">
                                            Semua produk aktif masih di atas ambang {{ $summary['lowStockThreshold'] }}.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div>
                    <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-500">
                        Draf pembelian menunggu penerimaan ({{ $summary['draftPurchaseCount'] }})
                    </h2>

                    <div class="mt-3 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
                        <table class="w-full text-left text-sm">
                            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-3">Nomor</th>
                                    <th class="px-4 py-3">Supplier</th>
                                    <th class="px-4 py-3 text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @forelse ($summary['draftPurchases'] as $purchase)
                                    <tr>
                                        <td class="px-4 py-3">
                                            <a href="{{ route('purchases.show', $purchase) }}" class="font-mono text-xs underline">{{ $purchase->number }}</a>
                                        </td>
                                        <td class="px-4 py-3">{{ $purchase->supplier?->name ?? '—' }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($purchase->total) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="px-4 py-6 text-center text-sm text-slate-500">
                                            Tidak ada draf pembelian yang menunggu.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        @if (auth()->user()->activeBusinesses->count() > 1)
            <section class="mt-8">
                <h2 class="text-sm font-medium text-slate-700">Pindah bisnis</h2>
                <ul class="mt-3 space-y-2">
                    @foreach (auth()->user()->activeBusinesses as $candidate)
                        <li class="flex items-center justify-between rounded-md bg-white px-3 py-2 ring-1 ring-slate-200">
                            <span class="text-sm">{{ $candidate->name }}</span>

                            @if ($candidate->is($business))
                                <span class="text-xs text-slate-400">aktif</span>
                            @else
                                <form method="POST" action="{{ route('businesses.switch', $candidate) }}">
                                    @csrf
                                    <button type="submit" class="text-sm text-slate-700 underline">
                                        Pindah
                                    </button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </main>
</x-layouts.app>
