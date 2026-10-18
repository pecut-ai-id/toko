<x-layouts.app title="Supplier --- TokoFlow">
    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Supplier</h1>
                <p class="mt-1 text-sm text-slate-500">Daftar supplier pada bisnis aktif.</p>
            </div>

            @can('create', App\Models\Supplier::class)
                <a href="{{ route('suppliers.create') }}" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    Tambah supplier
                </a>
            @endcan
        </div>

        <form method="GET" action="{{ route('suppliers.index') }}" class="mt-6 flex flex-wrap gap-2">
            <input name="q" value="{{ $term }}" placeholder="Cari nama supplier"
                class="w-full max-w-xs rounded-md border-0 bg-white px-3 py-2 text-sm ring-1 ring-slate-300">
            <button type="submit" class="rounded-md px-3 py-2 text-sm ring-1 ring-slate-300 hover:bg-white">Cari</button>
        </form>

        <div class="mt-6 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Nama</th>
                        <th class="px-4 py-3">Telepon</th>
                        <th class="px-4 py-3">Email</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($suppliers as $supplier)
                        <tr>
                            <td class="px-4 py-3">
                                <a href="{{ route('suppliers.show', $supplier) }}" class="underline">{{ $supplier->name }}</a>
                            </td>
                            <td class="px-4 py-3">{{ $supplier->phone ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $supplier->email ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($supplier->is_active)
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs text-emerald-700">Aktif</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">Nonaktif</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <a href="{{ route('suppliers.show', $supplier) }}" class="text-sm underline">Detail</a>
                                @can('update', $supplier)
                                    <a href="{{ route('suppliers.edit', $supplier) }}" class="ml-3 text-sm underline">Ubah</a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">
                                Belum ada supplier pada bisnis ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $suppliers->links() }}</div>
    </main>
</x-layouts.app>
