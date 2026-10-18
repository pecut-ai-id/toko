@php($isEdit = $customer->exists)

<x-layouts.app :title="($isEdit ? 'Ubah' : 'Tambah').' Pelanggan --- TokoFlow'">
    <main class="mx-auto w-full max-w-3xl flex-1 px-4 py-8">
        <a href="{{ route('customers.index') }}" class="text-sm text-slate-500 underline">&larr; Kembali ke pelanggan</a>

        <h1 class="mt-3 text-xl font-semibold">{{ $isEdit ? 'Ubah pelanggan' : 'Tambah pelanggan' }}</h1>

        <form
            method="POST"
            action="{{ $isEdit ? route('customers.update', $customer) : route('customers.store') }}"
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

            <div>
                <label for="name" class="block text-sm font-medium text-slate-700">Nama pelanggan/reseller</label>
                <input id="name" name="name" value="{{ old('name', $customer->name) }}" required
                    class="mt-1 block w-full rounded-md border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-300">
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="phone" class="block text-sm font-medium text-slate-700">Telepon (opsional)</label>
                    <input id="phone" name="phone" value="{{ old('phone', $customer->phone) }}"
                        class="mt-1 block w-full rounded-md border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-300">
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700">Email (opsional)</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $customer->email) }}"
                        class="mt-1 block w-full rounded-md border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-300">
                </div>
            </div>

            <div>
                <label for="address" class="block text-sm font-medium text-slate-700">Alamat (opsional)</label>
                <textarea id="address" name="address" rows="3"
                    class="mt-1 block w-full rounded-md border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-300">{{ old('address', $customer->address) }}</textarea>
            </div>

            <div>
                <input type="hidden" name="is_active" value="0">
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $customer->is_active ?? true))
                        class="rounded border-slate-300">
                    Pelanggan aktif
                </label>
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    Simpan
                </button>
                <a href="{{ route('customers.index') }}" class="text-sm text-slate-500 underline">Batal</a>
            </div>
        </form>

        @if ($isEdit && $customer->is_active)
            @can('delete', $customer)
                <form method="POST" action="{{ route('customers.destroy', $customer) }}" class="mt-4"
                    onsubmit="return confirm('Nonaktifkan pelanggan ini?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-sm text-red-600 underline">Nonaktifkan pelanggan</button>
                </form>
            @endcan
        @endif
    </main>
</x-layouts.app>
