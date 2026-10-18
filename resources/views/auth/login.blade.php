<x-layouts.guest title="Masuk">
<div class="grid min-h-screen lg:grid-cols-[1fr_minmax(0,28rem)]">
    <section class="relative hidden overflow-hidden bg-saluran-700 text-white lg:block">
        <div class="absolute inset-0 opacity-25" style="background-image: repeating-linear-gradient(0deg, transparent 0 47px, rgba(255,255,255,.35) 47px 48px), repeating-linear-gradient(0deg, transparent 0 11px, rgba(255,255,255,.18) 11px 12px); background-size: 100% 48px, 100% 12px; mask-image: linear-gradient(90deg, #000 0 64px, transparent 64px);"></div>
        <div class="relative flex h-full flex-col justify-between p-12 pl-24">
            <x-logo class="text-white" />
            <div class="max-w-lg">
                <h1 class="font-gauge text-5xl font-semibold leading-[1.05]">Air sampai ke sawah,<br>tercatat setiap hari.</h1>
                <p class="mt-4 text-lg text-saluran-100">Laporan debit, TMA, dan kondisi lahan dari 26 Daerah Irigasi kewenangan pusat di Nusa Tenggara Timur.</p>
            </div>
            <a href="{{ route('beranda') }}" class="text-sm text-saluran-100 underline-offset-4 hover:underline">Lihat peta kondisi irigasi untuk publik</a>
        </div>
    </section>
    <section class="flex items-center justify-center px-6 py-12">
        <div class="w-full max-w-sm">
            <x-logo :terang="false" class="mb-8 text-saluran-700 lg:hidden"/>
            <h2 class="text-3xl font-semibold">Masuk</h2>
            <p class="mt-1 text-sm text-basalt-soft">Petugas lapangan masuk dengan nomor HP yang terdaftar.</p>
            <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
                @csrf
                <x-field name="username" label="Username atau no. HP">
                    <input id="username" name="username" value="{{ old('username') }}" required autofocus autocomplete="username" inputmode="text" class="input py-2.5">
                </x-field>
                <x-field name="password" label="Password">
                    <input id="password" name="password" type="password" required autocomplete="current-password" class="input py-2.5">
                </x-field>
                <label class="flex items-center gap-2 text-sm text-basalt-soft"><input type="checkbox" name="remember" class="rounded border-savana-line text-saluran-600"> Ingat saya di perangkat ini</label>
                <button class="btn-primary w-full py-2.5">Masuk</button>
            </form>
            <p class="mt-6 text-sm text-basalt-soft">Lupa password? Hubungi Admin OP BBWS NT II untuk reset.</p>
            <x-pasang-aplikasi gaya="kartu" class="mt-6"/>
            <a href="{{ route('beranda') }}" class="mt-2 inline-block text-sm font-medium text-saluran-600 lg:hidden">Lihat peta publik</a>
        </div>
    </section>
</div>
</x-layouts.guest>
