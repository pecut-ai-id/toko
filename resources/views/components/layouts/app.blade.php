<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'TokoFlow' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <div class="flex min-h-screen flex-col">
        @auth
            <header class="border-b border-slate-200 bg-white">
                <div class="mx-auto flex w-full max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3">
                    <div class="flex flex-wrap items-center gap-5">
                        <span class="text-base font-semibold tracking-tight">TokoFlow</span>
                        <nav class="flex flex-wrap items-center gap-4 text-sm text-slate-600">
                            <a href="{{ route('dashboard') }}" class="hover:text-slate-900">Dashboard</a>
                            <a href="{{ route('products.index') }}" class="hover:text-slate-900">Produk</a>
                            <a href="{{ route('suppliers.index') }}" class="hover:text-slate-900">Supplier</a>
                            <a href="{{ route('customers.index') }}" class="hover:text-slate-900">Pelanggan</a>
                            <a href="{{ route('purchases.index') }}" class="hover:text-slate-900">Pembelian</a>
                            <a href="{{ route('sales.index') }}" class="hover:text-slate-900">Penjualan</a>
                            <a href="{{ route('reports.sales') }}" class="hover:text-slate-900">Laporan</a>
                            <a href="{{ route('audit-logs.index') }}" class="hover:text-slate-900">Riwayat</a>
                        </nav>
                    </div>

                    <div class="flex items-center gap-4">
                        @if (($layoutBusiness ?? null) !== null)
                            <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
                                {{ $layoutBusiness->name }}
                            </span>
                        @endif

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="rounded-md px-3 py-1.5 text-sm text-slate-600 ring-1 ring-slate-300 hover:bg-slate-50">
                                Keluar
                            </button>
                        </form>
                    </div>
                </div>
            </header>
        @endauth

        @if (session('status'))
            <div class="mx-auto w-full max-w-6xl px-4 pt-6">
                <div class="rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-700 ring-1 ring-emerald-200">
                    {{ session('status') }}
                </div>
            </div>
        @endif

        {{ $slot }}
    </div>
</body>
</html>
