{{--
    Formulir rentang tanggal + (opsional) tautan ekspor CSV.

    Rentang bersifat inklusif: satu tanggal berarti satu hari penuh.
    Komponen ini punya slot untuk filter tambahan (mis. filter produk di
    laporan stok, filter aktivitas di riwayat audit); filter tambahan itu
    otomatis ikut ke tautan ekspor CSV.

    `export=false` dipakai halaman yang tidak punya ekspor CSV (riwayat
    audit), supaya tautannya tidak dibuat sama sekali.
--}}
@props(['period', 'productId' => null, 'export' => true])

@php
    $exportQuery = http_build_query(array_filter([
        'from' => $period->fromDate(),
        'to' => $period->toDate(),
        'product_id' => $productId,
        'format' => 'csv',
    ]));
@endphp

<form
    method="GET"
    action="{{ url()->current() }}"
    class="mt-4 flex flex-wrap items-end gap-3 rounded-xl bg-white p-4 ring-1 ring-slate-200"
>
    <div>
        <label for="from" class="block text-xs text-slate-500">Dari tanggal</label>
        <input
            type="date"
            id="from"
            name="from"
            value="{{ $period->fromDate() }}"
            class="mt-1 rounded-md border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-300"
        >
    </div>

    <div>
        <label for="to" class="block text-xs text-slate-500">Sampai tanggal</label>
        <input
            type="date"
            id="to"
            name="to"
            value="{{ $period->toDate() }}"
            class="mt-1 rounded-md border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-300"
        >
    </div>

    {{ $slot }}

    <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
        Terapkan
    </button>

    <a href="{{ url()->current() }}" class="py-2 text-sm text-slate-500 underline">Bulan ini</a>

    @if ($export)
        <a
            href="{{ url()->current().'?'.$exportQuery }}"
            class="ml-auto rounded-md px-3 py-2 text-sm ring-1 ring-slate-300 hover:bg-slate-50"
        >
            Ekspor CSV
        </a>
    @endif
</form>

@if ($errors->any())
    <div class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-200">
        <ul class="list-disc space-y-1 pl-4">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
