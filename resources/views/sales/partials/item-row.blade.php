<div class="flex flex-wrap items-end gap-2" data-sale-item>
    <div class="min-w-[12rem] flex-1">
        <label class="block text-xs text-slate-500">Produk</label>
        <select
            name="items[{{ $index }}][product_id]"
            data-sale-product
            class="mt-1 block w-full rounded-md border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-300"
        >
            <option value="">Pilih produk</option>
            @foreach ($products as $product)
                <option
                    value="{{ $product->id }}"
                    data-price="{{ $product->sell_price }}"
                    @selected((string) ($item['product_id'] ?? '') === (string) $product->id)
                >
                    {{ $product->name }} ({{ $product->sku }}) &middot; stok {{ angka($product->stock) }}
                </option>
            @endforeach
        </select>
    </div>

    <div class="w-28">
        <label class="block text-xs text-slate-500">Kuantitas</label>
        <input
            name="items[{{ $index }}][quantity]"
            value="{{ $item['quantity'] ?? '' }}"
            inputmode="decimal"
            placeholder="0"
            class="mt-1 block w-full rounded-md border-0 bg-slate-50 px-3 py-2 text-right text-sm ring-1 ring-slate-300"
        >
    </div>

    <div class="w-40">
        <label class="block text-xs text-slate-500">Harga jual</label>
        <input
            name="items[{{ $index }}][unit_price]"
            data-sale-unit-price
            value="{{ $item['unit_price'] ?? '' }}"
            inputmode="decimal"
            placeholder="0"
            class="mt-1 block w-full rounded-md border-0 bg-slate-50 px-3 py-2 text-right text-sm ring-1 ring-slate-300"
        >
    </div>

    <button
        type="button"
        data-remove-sale-item
        class="rounded-md px-3 py-2 text-sm text-red-600 ring-1 ring-red-200 hover:bg-red-50"
    >
        Hapus
    </button>
</div>
