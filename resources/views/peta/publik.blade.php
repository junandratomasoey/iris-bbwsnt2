<x-layouts.guest title="Peta kondisi irigasi NTT">
<div class="flex h-[100dvh] flex-col">
    <header class="flex items-center justify-between gap-3 bg-saluran-600 px-4 py-2.5 text-white">
        <x-logo />
        <div class="hidden text-center md:block">
            <p class="font-gauge text-lg font-semibold leading-tight">Peta Kondisi Irigasi Nusa Tenggara Timur</p>
            <p class="text-xs text-saluran-100">Daerah Irigasi kewenangan pusat · BBWS Nusa Tenggara II</p>
        </div>
        @auth
            <a href="{{ route('dashboard') }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-saluran-700">Dashboard</a>
        @else
            <a href="{{ route('login') }}" class="rounded-md bg-white px-3 py-1.5 text-sm font-semibold text-saluran-700">Masuk petugas</a>
        @endauth
    </header>
    <div class="min-h-0 flex-1">
        @include('peta._peta', ['internal' => false, 'api' => route('api.publik.peta')])
    </div>
</div>
</x-layouts.guest>
