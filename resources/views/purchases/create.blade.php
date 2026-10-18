@php
    $oldItems = old('items', [['product_id' => '', 'quantity' => '', 'unit_cost' => '']]);

    if (! is_array($oldItems) || $oldItems === []) {
        $oldItems = [['product_id' => '', 'quantity' => '', 'unit_cost' => '']];
    }
@endphp

<x-layouts.app title="Tambah Pembelian --- TokoFlow">
    <main class="mx-auto w-full max-w-4xl flex-1 px-4 py-8">
        <a href="{{ route('purchases.index') }}" class="text-sm text-slate-500 underline">&larr; Kembali ke pembelian</a>

        <h1 class="mt-3 text-xl font-semibold">Tambah pembelian</h1>
        <p class="mt-1 text-sm text-slate-500">
            Disimpan sebagai draf. Stok baru bertambah setelah penerimaan dikonfirmasi.
        </p>

        <form
            method="POST"
            action="{{ route('purchases.store') }}"
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
                    <label for="supplier_id" class="block text-sm font-medium text-slate-700">Supplier</label>
                    <select
                        id="supplier_id"
                        name="supplier_id"
                        required
                        class="mt-1 block w-full rounded-md border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-300"
                    >
                        <option value="">Pilih supplier</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>
                                {{ $supplier->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="purchased_at" class="block text-sm font-medium text-slate-700">Tanggal</label>
                    <input
                        type="date"
                        id="purchased_at"
                        name="purchased_at"
                        value="{{ old('purchased_at', now()->toDateString()) }}"
                        required
                        class="mt-1 block w-full rounded-md border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-300"
                    >
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between">
                    <span class="text-sm font-medium text-slate-700">Item pembelian</span>
                    <button
                        type="button"
                        data-add-purchase-item
                        class="rounded-md px-3 py-1.5 text-sm ring-1 ring-slate-300 hover:bg-slate-50"
                    >
                        Tambah baris
                    </button>
                </div>

                <div class="mt-2 space-y-2" data-purchase-items>
                    @foreach ($oldItems as $index => $item)
                        @include('purchases.partials.item-row', [
                            'index' => $index,
                            'item' => $item,
                            'products' => $products,
                        ])
                    @endforeach
                </div>

                <p class="mt-2 text-xs text-slate-500">
                    Angka boleh ditulis dengan format Indonesia, misalnya 85.000 atau 1.275.000,50.
                </p>
            </div>

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
                    Simpan draf
                </button>
                <a href="{{ route('purchases.index') }}" class="text-sm text-slate-500 underline">Batal</a>
            </div>
        </form>

        <template id="purchase-item-template">
            @include('purchases.partials.item-row', [
                'index' => '__INDEX__',
                'item' => ['product_id' => '', 'quantity' => '', 'unit_cost' => ''],
                'products' => $products,
            ])
        </template>
    </main>
</x-layouts.app>
