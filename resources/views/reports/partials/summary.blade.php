{{--
    Kartu ringkasan angka.

    Parameter: $cards = daftar ['label' => ..., 'value' => ..., 'hint' => ...,
    'tone' => 'default'|'good'|'warning'|'danger'].
--}}
@php
    $tones = [
        'default' => 'text-slate-900',
        'good' => 'text-emerald-700',
        'warning' => 'text-amber-700',
        'danger' => 'text-red-700',
    ];
@endphp

<dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    @foreach ($cards as $card)
        <div class="rounded-xl bg-white p-4 ring-1 ring-slate-200">
            <dt class="text-xs uppercase tracking-wide text-slate-500">{{ $card['label'] }}</dt>
            <dd class="mt-1 text-xl font-semibold tabular-nums {{ $tones[$card['tone'] ?? 'default'] ?? $tones['default'] }}">
                {{ $card['value'] }}
            </dd>
            @if (! empty($card['hint']))
                <p class="mt-1 text-xs text-slate-500">{{ $card['hint'] }}</p>
            @endif
        </div>
    @endforeach
</dl>
