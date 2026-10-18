{{--
    Ringkasan perubahan satu baris jejak audit: kolom => [nilai lama, nilai baru].
    Hanya kolom yang layak dicatat yang tersimpan, jadi daftarnya pendek.
--}}
@props(['changes' => null])

@php
    $labels = [
        'name' => 'nama',
        'sku' => 'SKU',
        'unit' => 'satuan',
        'sell_price' => 'harga jual',
        'cost_price' => 'harga pokok',
        'stock' => 'stok',
        'is_active' => 'status aktif',
        'phone' => 'telepon',
        'email' => 'email',
        'address' => 'alamat',
        'status' => 'status',
        'total' => 'total',
        'amount' => 'jumlah',
        'voided' => 'dibatalkan',
        'reason' => 'alasan',
    ];

    $format = function (mixed $value): string {
        if (is_bool($value)) {
            return $value ? 'ya' : 'tidak';
        }

        if ($value === null || $value === '') {
            return '—';
        }

        if (is_numeric($value)) {
            return angka($value);
        }

        return (string) $value;
    };
@endphp

@if (! empty($changes))
    <ul class="space-y-0.5 text-xs text-slate-500">
        @foreach ($changes as $field => $pair)
            @php
                $old = is_array($pair) ? ($pair[0] ?? null) : null;
                $new = is_array($pair) ? ($pair[1] ?? null) : $pair;
            @endphp
            <li>
                {{ $labels[$field] ?? $field }}:
                <span class="text-slate-400">{{ $format($old) }}</span>
                <span class="text-slate-400">&rarr;</span>
                <span class="font-medium text-slate-700">{{ $format($new) }}</span>
            </li>
        @endforeach
    </ul>
@endif
