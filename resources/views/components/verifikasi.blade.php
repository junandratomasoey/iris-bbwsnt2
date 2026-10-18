@props(['s'])
@php $c = ['menunggu' => 'chip-waspada', 'disetujui' => 'chip-aman', 'perbaikan' => 'chip-kritis'][$s] ?? 'chip-kosong'; @endphp
<span class="{{ $c }}">{{ \App\Models\LaporanHarian::VERIFIKASI[$s] ?? $s }}</span>
