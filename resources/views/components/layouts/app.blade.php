@php
    use App\Support\Role;
    $u = auth()->user();
    $menu = [
        ['dashboard', 'Dashboard', 'dashboard', true],
        ['peta', 'Peta kondisi', 'peta', true],
        ['laporan.index', 'Laporan', 'laporan', true],
        ['pintu.index', 'Pintu air', 'pintu', true],
        ['kejadian.index', 'Kejadian kekeringan', 'kejadian', true],
        ['pengingat', 'Pengingat laporan', 'lonceng', $u->can('lihat-petugas')],
        ['petugas.index', 'Kontak petugas', 'petugas', $u->can('lihat-petugas') || $u->can('kelola-petugas')],
        ['master.daerah-irigasi.index', 'Daerah irigasi', 'di', true],
        ['master.bangunan.index', 'Infrastruktur', 'bangunan', $u->can('lihat-master')],
        ['master.peta.index', 'Peta batas D.I.', 'peta', $u->can('lihat-master')],
        ['master.jadwal-tanam.index', 'Jadwal tanam', 'jadwal', $u->can('lihat-jadwal')],
        ['master.p3a.index', 'P3A', 'p3a', $u->can('lihat-master')],
        ['pengguna.index', 'Pengguna', 'pengguna', $u->can('kelola-pengguna')],
        ['hak-akses.index', 'Hak akses', 'kunci', $u->can('kelola-hak-akses')],
        ['audit', 'Log aktivitas', 'audit', $u->can('lihat-audit')],
        ['cadangan.index', 'Cadangan data', 'unduh', $u->can('kelola-hak-akses')],
    ];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>@include('layouts.head')</head>
<body class="min-h-screen" x-data="{ nav: false }">
<a href="#isi" class="sr-only focus:not-sr-only focus:fixed focus:left-2 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2">Lompat ke isi</a>

{{-- bilah atas seluler --}}
<header class="sticky top-0 z-30 flex items-center justify-between bg-saluran-600 px-4 py-2.5 text-white lg:hidden">
    <a href="{{ route('dashboard') }}"><x-logo /></a>
    <button type="button" class="rounded p-2 hover:bg-saluran-700" @click="nav = true" aria-label="Buka menu"><x-ikon n="menu" class="h-6 w-6"/></button>
</header>

<div x-show="nav" x-cloak class="fixed inset-0 z-40 bg-basalt/50 lg:hidden" @click="nav = false"></div>
<aside :class="nav && '!translate-x-0'"
       class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col bg-saluran-600 text-white transition-transform lg:translate-x-0">
    <div class="flex items-center justify-between px-4 py-4">
        <a href="{{ route('dashboard') }}"><x-logo /></a>
        <button type="button" class="rounded p-1.5 hover:bg-saluran-700 lg:hidden" @click="nav = false" aria-label="Tutup menu"><x-ikon n="tutup"/></button>
    </div>
    @if($u->can('lapor') || $u->can('lapor-air') || $u->can('isi-pintu'))
        <div class="space-y-2 px-3 pb-3">
            @can('lapor-air')
                <a href="{{ route('laporan-air.create') }}" class="flex items-center justify-center gap-2 rounded-md bg-white px-3 py-2.5 text-sm font-semibold text-saluran-700 hover:bg-saluran-50">
                    <x-ikon n="di"/> Isi laporan Manager Air
                </a>
            @endcan
            @can('lapor')
                <a href="{{ route('laporan.create') }}" class="flex items-center justify-center gap-2 rounded-md bg-white px-3 py-2.5 text-sm font-semibold text-saluran-700 hover:bg-saluran-50">
                    <x-ikon n="tambah"/> Isi laporan PPA
                </a>
            @endcan
            @can('isi-pintu')
                <a href="{{ route('pembacaan-pintu.create') }}" class="flex items-center justify-center gap-2 rounded-md border border-white/60 px-3 py-2 text-sm font-semibold text-white hover:bg-saluran-700">
                    <x-ikon n="pintu"/> Baca pintu air
                </a>
            @endcan
        </div>
    @endif
    <nav class="flex-1 space-y-0.5 overflow-y-auto px-3" aria-label="Menu utama">
        @foreach($menu as [$route, $label, $ikon, $tampil])
            @continue(! $tampil)
            @php $aktif = request()->routeIs(str_replace('.index', '', $route).'*') || ($route === 'laporan.index' && request()->routeIs('laporan-air*')); @endphp
            <a href="{{ route($route) }}" @class(['nav-link', 'active' => $aktif]) @if($aktif) aria-current="page" @endif>
                <x-ikon :n="$ikon"/> {{ $label }}
            </a>
        @endforeach
    </nav>
    <x-pasang-aplikasi gaya="menu" class="px-3 pb-2"/>
    <div class="border-t border-saluran-700 px-4 py-3 text-sm">
        <p class="font-semibold">{{ $u->name }}</p>
        <p class="text-xs text-saluran-100">{{ $u->roleLabel() }}@if($u->daerahIrigasi) · D.I. {{ $u->daerahIrigasi->nama }}@endif</p>
        <div class="mt-2 flex gap-3 text-xs">
            <a href="{{ route('password.edit') }}" class="text-saluran-100 underline-offset-2 hover:underline">Ganti password</a>
            <form method="POST" action="{{ route('logout') }}">@csrf
                <button class="text-saluran-100 underline-offset-2 hover:underline">Keluar</button>
            </form>
        </div>
    </div>
</aside>

<main id="isi" class="lg:pl-64">
    <div class="{{ $lebar ?? 'mx-auto max-w-7xl px-4 py-6 sm:px-6' }}">
        @include('layouts.flash')
        {{ $slot }}
    </div>
</main>
@stack('scripts')
</body>
</html>
