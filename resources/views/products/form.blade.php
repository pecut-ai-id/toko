@php($isEdit = $product->exists)

<x-layouts.app :title="($isEdit ? 'Ubah' : 'Tambah').' Produk --- TokoFlow'">
    <main class="mx-auto w-full max-w-3xl flex-1 px-4 py-8">
        <a href="{{ route('products.index') }}" class="text-sm text-slate-500 underline">&larr; Kembali ke produk</a>

        <h1 class="mt-3 text-xl font-semibold">{{ $isEdit ? 'Ubah produk' : 'Tambah produk' }}</h1>
        @if ($isEdit)
            <a href="{{ route('products.show', $product) }}" class="mt-1 inline-block text-sm text-slate-500 underline">
                Lihat detail produk
            </a>
        @endif

        <form
            method="POST"
            action="{{ $isEdit ? route('products.update', $product) : route('products.store') }}"
            class="mt-6 space-y-5 rounded-xl bg-white p-6 ring-1 ring-slate-200"
        >
            @csrf
            @if ($isEdit)
                @method('PUT')
            @endif

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
                    <label for="sku" class="block text-sm font-medium text-slate-700">SKU</label>
                    <input id="sku" name="sku" value="{{ old('sku', $product->sku) }}" required
                        class="mt-1 block w-full rounded-md border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-300">
                </div>

                <div>
                    <label for="unit" class="block text-sm font-medium text-slate-700">Satuan</label>
                    <input id="unit" name="unit" value="{{ old('unit', $product->unit ?? 'pcs') }}" required
                        class="mt-1 block w-full rounded-md border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-300">
                </div>
            </div>

            <div>
                <label for="name" class="block text-sm font-medium text-slate-700">Nama produk</label>
                <input id="name" name="name" value="{{ old('name', $product->name) }}" required
                    class="mt-1 block w-full rounded-md border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-300">
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="sell_price" class="block text-sm font-medium text-slate-700">Harga jual</label>
                    <input id="sell_price" name="sell_price" inputmode="decimal" placeholder="0"
                        value="{{ old('sell_price', angka($product->sell_price ?? 0)) }}" required
                        class="mt-1 block w-full rounded-md border-0 bg-slate-50 px-3 py-2 text-right text-sm ring-1 ring-slate-300">
                </div>

                <div>
                    <label for="cost_price" class="block text-sm font-medium text-slate-700">Harga pokok</label>
                    <input id="cost_price" name="cost_price" inputmode="decimal" placeholder="0"
                        value="{{ old('cost_price', angka($product->cost_price ?? 0)) }}" required
                        class="mt-1 block w-full rounded-md border-0 bg-slate-50 px-3 py-2 text-right text-sm ring-1 ring-slate-300">
                </div>
            </div>

            <p class="text-xs text-slate-500">
                Angka boleh ditulis dengan format Indonesia, misalnya 85.000 atau 1.275.000,50.
            </p>

            <div>
                <input type="hidden" name="is_active" value="0">
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $product->is_active ?? true))
                        class="rounded border-slate-300">
                    Produk aktif
                </label>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    Simpan
                </button>
                <a href="{{ route('products.index') }}" class="text-sm text-slate-500 underline">Batal</a>
            </div>
        </form>

        @if ($isEdit && $product->is_active)
            @can('delete', $product)
                <form method="POST" action="{{ route('products.destroy', $product) }}" class="mt-4"
                    onsubmit="return confirm('Nonaktifkan produk ini?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm text-red-600 underline">Nonaktifkan produk</button>
                </form>
            @endcan
        @endif
    </main>
</x-layouts.app>
