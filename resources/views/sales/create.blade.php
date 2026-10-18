@php
    $oldItems = old('items', [['product_id' => '', 'quantity' => '', 'unit_price' => '']]);

    if (! is_array($oldItems) || $oldItems === []) {
        $oldItems = [['product_id' => '', 'quantity' => '', 'unit_price' => '']];
    }
@endphp

<x-layouts.app title="Tambah Penjualan --- TokoFlow">
    <main class="mx-auto w-full max-w-4xl flex-1 px-4 py-8">
        <a href="{{ route('sales.index') }}" class="text-sm text-slate-500 underline">&larr; Kembali ke penjualan</a>

        <h1 class="mt-3 text-xl font-semibold">Tambah penjualan</h1>
        <p class="mt-1 text-sm text-slate-500">
            Stok langsung berkurang saat disimpan. Penjualan ditolak bila stok tidak cukup.
        </p>

        <form
            method="POST"
            action="{{ route('sales.store') }}"
            class="mt-6 space-y-5 rounded-xl bg-white p-6 ring-1 ring-slate-200"
        >
            @csrf

            @if ($errors->any())
                <div class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-200">
                    <ul class="list-disc space-y-1 pl-4">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="customer_id" class="block text-sm font-medium text-slate-700">Pelanggan</label>
                    <select
                        id="customer_id"
                        name="customer_id"
                        required
                        class="mt-1 block w-full rounded-md border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-300"
                    >
                        <option value="">Pilih pelanggan</option>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}" @selected((string) old('customer_id') === (string) $customer->id)>
                                {{ $customer->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="sold_at" class="block text-sm font-medium text-slate-700">Tanggal</label>
                    <input
                        type="date"
                        id="sold_at"
                        name="sold_at"
                        value="{{ old('sold_at', now()->toDateString()) }}"
                        required
                        class="mt-1 block w-full rounded-md border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-300"
                    >
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-slate-700">Item penjualan</span>
                    <button
                        type="button"
                        data-add-sale-item
                        class="rounded-md px-3 py-1.5 text-sm ring-1 ring-slate-300 hover:bg-slate-50"
                    >
                        Tambah baris
                    </button>
                </div>

                <div class="mt-2 space-y-2" data-sale-items>
                    @foreach ($oldItems as $index => $item)
                        @include('sales.partials.item-row', [
                            'index' => $index,
                            'item' => $item,
                            'products' => $products,
                        ])
                    @endforeach
                </div>

                <p class="mt-2 text-xs text-slate-500">
                    Harga jual terisi otomatis dari produk dan boleh disesuaikan. Angka boleh
                    ditulis dengan format Indonesia, misalnya 85.000 atau 1.275.000,50.
                </p>
            </div>

            <fieldset class="rounded-lg bg-slate-50 p-4 ring-1 ring-slate-200">
                <legend class="px-1 text-sm font-medium text-slate-700">Pembayaran awal (opsional)</legend>
                <p class="mt-1 text-xs text-slate-500">
                    Biarkan kosong untuk penjualan kredit penuh. Pembayaran tidak boleh melebihi total.
                </p>

                <div class="mt-3 grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="paid_amount" class="block text-xs text-slate-500">Jumlah dibayar</label>
                        <input
                            id="paid_amount"
                            name="paid_amount"
                            value="{{ old('paid_amount') }}"
                            inputmode="decimal"
                            placeholder="0"
                            class="mt-1 block w-full rounded-md border-0 bg-white px-3 py-2 text-right text-sm ring-1 ring-slate-300"
                        >
                    </div>

                    <div>
                        <label for="paid_at" class="block text-xs text-slate-500">Tanggal bayar</label>
                        <input
                            type="date"
                            id="paid_at"
                            name="paid_at"
                            value="{{ old('paid_at', now()->toDateString()) }}"
                            class="mt-1 block w-full rounded-md border-0 bg-white px-3 py-2 text-sm ring-1 ring-slate-300"
                        >
                    </div>

                    <div>
                        <label for="payment_method" class="block text-xs text-slate-500">Metode</label>
                        <input
                            id="payment_method"
                            name="payment_method"
                            value="{{ old('payment_method') }}"
                            placeholder="Tunai / Transfer"
                            class="mt-1 block w-full rounded-md border-0 bg-white px-3 py-2 text-sm ring-1 ring-slate-300"
                        >
                    </div>
                </div>

                <div class="mt-4">
                    <label for="payment_reference" class="block text-xs text-slate-500">Referensi</label>
                    <input
                        id="payment_reference"
                        name="payment_reference"
                        value="{{ old('payment_reference') }}"
                        class="mt-1 block w-full rounded-md border-0 bg-white px-3 py-2 text-sm ring-1 ring-slate-300"
                    >
                </div>
            </fieldset>

            <div>
                <label for="notes" class="block text-sm font-medium text-slate-700">Catatan</label>
                <textarea
                    id="notes"
                    name="notes"
                    rows="2"
                    class="mt-1 block w-full rounded-md border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-300"
                >{{ old('notes') }}</textarea>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    Simpan penjualan
                </button>
                <a href="{{ route('sales.index') }}" class="text-sm text-slate-500 underline">Batal</a>
            </div>
        </form>

        <template id="sale-item-template">
            @include('sales.partials.item-row', [
                'index' => '__INDEX__',
                'item' => ['product_id' => '', 'quantity' => '', 'unit_price' => ''],
                'products' => $products,
            ])
        </template>
    </main>
</x-layouts.app>
