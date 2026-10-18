<x-layouts.app title="Riwayat aktivitas --- TokoFlow">
    <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
        <h1 class="text-xl font-semibold">Riwayat aktivitas</h1>
        <p class="mt-1 text-sm text-slate-500">
            Catatan siapa melakukan apa pada data bisnis ini. Barisnya tidak bisa diubah atau dihapus, dan hanya
            perubahan penting yang disimpan &mdash; bukan salinan seluruh data.
        </p>

        <x-period-form :period="$period" :export="false">
            <div>
                <label for="action" class="block text-xs text-slate-500">Jenis aktivitas</label>
                <select
                    id="action"
                    name="action"
                    class="mt-1 max-w-xs rounded-md border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-300"
                >
                    <option value="">Semua aktivitas</option>
                    @foreach ($actionGroups as $group => $cases)
                        <optgroup label="{{ $group }}">
                            @foreach ($cases as $case)
                                <option value="{{ $case->value }}" @selected($selectedAction === $case)>
                                    {{ $case->label() }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="user_id" class="block text-xs text-slate-500">Pelaku</label>
                <select
                    id="user_id"
                    name="user_id"
                    class="mt-1 max-w-xs rounded-md border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-300"
                >
                    <option value="">Semua pengguna</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->getKey() }}" @selected($selectedUserId === $user->getKey())>
                            {{ $user->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </x-period-form>

        <p class="mt-3 text-sm text-slate-500">
            {{ $logs->total() }} catatan pada {{ $period->label() }}.
        </p>

        <div class="mt-3 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Waktu</th>
                        <th class="px-4 py-3">Pelaku</th>
                        <th class="px-4 py-3">Aktivitas</th>
                        <th class="px-4 py-3">Keterangan</th>
                        <th class="px-4 py-3">Dokumen</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($logs as $log)
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap">{{ $log->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3">{{ $log->user?->name ?? 'Pengguna dihapus' }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">
                                    {{ $log->action->label() }}
                                </span>
                                <span class="mt-1 block text-xs text-slate-400">{{ $log->action->group() }}</span>
                            </td>
                            <td class="px-4 py-3">
                                {{ $log->summary }}
                                <x-audit-changes :changes="$log->changes" />
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $subject = $log->subject;

                                    $url = match (true) {
                                        $subject instanceof App\Models\Purchase => route('purchases.show', $subject),
                                        $subject instanceof App\Models\Sale => route('sales.show', $subject),
                                        $subject instanceof App\Models\Product => route('products.show', $subject),
                                        $subject instanceof App\Models\Supplier => route('suppliers.show', $subject),
                                        $subject instanceof App\Models\Customer => route('customers.show', $subject),
                                        default => null,
                                    };
                                @endphp

                                @if ($url !== null)
                                    <a href="{{ $url }}" class="text-xs underline">Lihat dokumen</a>
                                @else
                                    <span class="text-xs text-slate-400">&mdash;</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">
                                Tidak ada aktivitas pada rentang dan penyaring ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $logs->links() }}</div>
    </main>
</x-layouts.app>
