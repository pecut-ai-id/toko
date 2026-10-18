<x-layouts.app title="Masuk --- TokoFlow">
    <div class="flex flex-1 items-center justify-center px-4 py-16">
        <div class="w-full max-w-sm">
            <div class="mb-8 text-center">
                <h1 class="text-2xl font-semibold tracking-tight">TokoFlow</h1>
                <p class="mt-1 text-sm text-slate-500">Masuk untuk mengelola bisnis Anda</p>
            </div>

            <form method="POST" action="{{ route('login') }}" class="space-y-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                @csrf

                @if ($errors->any())
                    <div class="rounded-md bg-red-50 px-3 py-2 text-sm text-red-700 ring-1 ring-red-200">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
                    <input
                        id="email"
                        name="email"
                        type="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        autocomplete="username"
                        class="mt-1 block w-full rounded-md border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-300 focus:ring-2 focus:ring-slate-900"
                    >
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-700">Password</label>
                    <input
                        id="password"
                        name="password"
                        type="password"
                        required
                        autocomplete="current-password"
                        class="mt-1 block w-full rounded-md border-0 bg-slate-50 px-3 py-2 text-sm ring-1 ring-slate-300 focus:ring-2 focus:ring-slate-900"
                    >
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remember" value="1" class="rounded border-slate-300">
                    Ingat saya
                </label>

                <button
                    type="submit"
                    class="w-full rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800"
                >
                    Masuk
                </button>
            </form>
        </div>
    </div>
</x-layouts.app>
