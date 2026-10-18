{{--
    Navigasi antar laporan. Membawa rentang tanggal yang sedang aktif
    supaya berpindah tab tidak mengubah periode yang sedang dilihat.

    Parameter: $period (ReportPeriod), $active (nama route aktif).
--}}
@php
    $tabs = [
        'reports.sales' => 'Penjualan',
        'reports.purchases' => 'Pembelian',
        'reports.stock' => 'Stok',
        'reports.receivables' => 'Piutang',
        'reports.payables' => 'Utang',
    ];
@endphp

<nav class="mt-6 flex flex-wrap gap-1 border-b border-slate-200">
    @foreach ($tabs as $name => $label)
        <a
            href="{{ route($name, ['from' => $period->fromDate(), 'to' => $period->toDate()]) }}"
            class="-mb-px rounded-t-md px-3 py-2 text-sm {{ $name === $active
                ? 'border-b-2 border-slate-900 font-medium text-slate-900'
                : 'text-slate-500 hover:text-slate-800' }}"
        >
            {{ $label }}
        </a>
    @endforeach
</nav>
