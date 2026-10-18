{{--
    Bagian pembayaran untuk dokumen bersaldo (penjualan = piutang,
    pembelian = utang). Dipakai ulang oleh sales/show dan purchases/show
    supaya aturan tampilannya tidak menyimpang antar dokumen.

    Parameter:
    - $document  : model dokumen (Sale/Purchase), wajib memuat relasi payments.user, payments.voider
    - $storeRoute: nama route untuk mencatat pembayaran
--}}

<h2 class="mt-6 text-sm font-semibold uppercase tracking-wide text-slate-500">Pembayaran</h2>

<div class="mt-2 overflow-x-auto rounded-xl bg-white ring-1 ring-slate-200">
    <table class="w-full text-left text-sm">
        <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
            <tr>
                <th class="px-4 py-3">Tanggal</th>
                <th class="px-4 py-3 text-right">Jumlah</th>
                <th class="px-4 py-3">Metode</th>
                <th class="px-4 py-3">Referensi</th>
                <th class="px-4 py-3">Dicatat oleh</th>
                <th class="px-4 py-3">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($document->payments->sortBy('id') as $payment)
                <tr class="{{ $payment->isVoided() ? 'text-slate-400' : '' }}">
                    <td class="px-4 py-3">{{ $payment->paid_at?->format('d/m/Y') }}</td>
                    <td class="px-4 py-3 text-right tabular-nums {{ $payment->isVoided() ? 'line-through' : '' }}">
                        {{ rupiah($payment->amount) }}
                    </td>
                    <td class="px-4 py-3">{{ $payment->method ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $payment->reference ?? '—' }}</td>
                    <td class="px-4 py-3">{{ $payment->user?->name ?? '—' }}</td>
                    <td class="px-4 py-3">
                        @if ($payment->isVoided())
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">Dibatalkan</span>
                            <span class="mt-1 block text-xs text-slate-500">
                                {{ $payment->void_reason }}
                                @if ($payment->voider)
                                    &middot; {{ $payment->voider->name }}
                                @endif
                            </span>
                        @elseif ($document->isOpen())
                            @can('void', $payment)
                                <details>
                                    <summary class="cursor-pointer text-xs text-red-600 underline">Batalkan</summary>

                                    <form
                                        method="POST"
                                        action="{{ route('payments.void', $payment) }}"
                                        onsubmit="return confirm('Batalkan pembayaran {{ rupiah($payment->amount) }}? Barisnya tetap tersimpan sebagai riwayat, tetapi tidak lagi dihitung sebagai pelunasan.')"
                                        class="mt-2 flex flex-wrap items-end gap-2"
                                    >
                                        @csrf

                                        <input
                                            name="reason"
                                            value="{{ old('reason') }}"
                                            required
                                            minlength="3"
                                            maxlength="500"
                                            placeholder="Alasan pembatalan"
                                            class="w-48 rounded-md border-0 bg-slate-50 px-2 py-1 text-xs ring-1 ring-slate-300"
                                        >

                                        <button type="submit" class="rounded-md bg-red-600 px-2 py-1 text-xs font-medium text-white hover:bg-red-700">
                                            Simpan
                                        </button>
                                    </form>
                                </details>
                            @endcan
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-6 text-center text-sm text-slate-500">
                        Belum ada pembayaran.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($document->canRecordPayment())
    @can('recordPayment', $document)
        <form
            method="POST"
            action="{{ route($storeRoute, $document) }}"
            class="mt-4 space-y-4 rounded-xl bg-white p-6 ring-1 ring-slate-200"
        >
            @csrf

            <p class="text-sm text-slate-600">
                Sisa saat ini: <span class="font-medium tabular-nums">{{ rupiah($document->dueAmount()) }}</span>
            </p>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="amount" class="block text-xs text-slate-500">Jumlah bayar</label>
                    <input
                        id="amount"
                        name="amount"
                        value="{{ old('amount') }}"
                        inputmode="decimal"
                        placeholder="0"
                        required
                        class="mt-1 block w-full rounded-md border-0 bg-slate-50 px-3 py-2 text-right text-sm ring-1 ring-slate-300"
                    >
                </div>

                <div>
                    <label for="paid_at" class="block text-xs text-slate-500">Tanggal bayar</label>
                    <input
                        type="date"
                        id="paid_at"
                        name="paid_at"
                        value="{{ old('paid_at', now()->toDateString()) }}"
                        required
                        class="mt-1 block w-full rounded-md border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-300"
                    >
                </div>

                <div>
                    <label for="method" class="block text-xs text-slate-500">Metode</label>
                    <input
                        id="method"
                        name="method"
                        value="{{ old('method') }}"
                        placeholder="Tunai / Transfer"
                        class="mt-1 block w-full rounded-md border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-300"
                    >
                </div>
            </div>

            <div>
                <label for="reference" class="block text-xs text-slate-500">Referensi</label>
                <input
                    id="reference"
                    name="reference"
                    value="{{ old('reference') }}"
                    class="mt-1 block w-full rounded-md border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-300"
                >
            </div>

            <button type="submit" class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                Catat pembayaran
            </button>
        </form>
    @endcan
@endif
