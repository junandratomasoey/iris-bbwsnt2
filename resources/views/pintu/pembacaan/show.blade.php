@php use App\Models\PintuAir; use App\Services\PintuService; @endphp
<x-layouts.app title="Pembacaan pintu air">
<x-judul judul="Pembacaan pintu air · {{ $sesi->tanggal->translatedFormat('l, d M Y') }}" sub="D.I. {{ $sesi->daerahIrigasi->nama }} · {{ $sesi->jenis_label }}{{ $sesi->jam ? ' · pukul '.substr($sesi->jam, 0, 5) : '' }}">
    <a href="{{ route('pintu.index', ['di' => $sesi->daerah_irigasi_id]) }}" class="btn-ghost"><x-ikon n="pintu" class="h-4 w-4"/> Pemantauan pintu</a>
    @if($bolehUbah)
        <a href="{{ route('pembacaan-pintu.edit', $sesi) }}" class="btn-ghost"><x-ikon n="edit" class="h-4 w-4"/> Ubah</a>
        <form method="POST" action="{{ route('pembacaan-pintu.destroy', $sesi) }}" onsubmit="return confirm('Hapus pembacaan ini?')">@csrf @method('DELETE')<button class="btn-ghost text-status-kritis"><x-ikon n="hapus" class="h-4 w-4"/> Hapus</button></form>
    @endif
</x-judul>

<div class="panel mb-4 flex flex-wrap items-center gap-x-6 gap-y-2 p-4 text-sm">
    <span>Petugas: <strong>{{ $sesi->user->name }}</strong></span>
    @if($sesi->user->no_hp)<a href="{{ $sesi->user->waLink('Konfirmasi pembacaan pintu air D.I. '.$sesi->daerahIrigasi->nama.' tanggal '.$sesi->tanggal->translatedFormat('d M Y')) }}" target="_blank" rel="noopener" class="btn-wa"><x-ikon n="wa" class="h-3.5 w-3.5"/> WhatsApp</a>@endif
    <span class="text-basalt-soft">{{ $baris->count() }} pintu dibaca</span>
    @if($sesi->catatan)<p class="w-full whitespace-pre-line text-basalt-soft">{{ $sesi->catatan }}</p>@endif
</div>

<div class="panel mb-5 overflow-x-auto">
    <table class="table-base">
        <thead><tr><th>Pintu</th><th class="text-right">Debit (L/dt)</th><th>Dibanding sebelumnya</th><th class="text-right">TMA (cm)</th><th>Dibanding sebelumnya</th><th class="text-right">Bukaan (cm)</th><th>Catatan</th></tr></thead>
        <tbody>
        @foreach($baris as $b)
            @php $p = $b->pintu; $lalu = $sebelum[$p->id] ?? null; @endphp
            <tr>
                <td class="min-w-[10rem]"><a href="{{ route('pintu.show', $p) }}" class="font-semibold text-saluran-600">{{ $p->kode }}</a>
                    <span class="block text-xs text-basalt-soft">{{ $p->subDaerahIrigasi?->nama ?? 'Tingkat D.I.' }}{{ $p->nama ? ' · '.$p->nama : '' }}</span></td>
                <td class="whitespace-nowrap text-right"><span class="font-semibold">{{ angka_r($b->debit_lps) }}</span> <x-status-pintu :s="$p->statusDebit($b->debit_lps)"/>
                    <span class="block text-[11px] text-basalt-mute">normal {{ PintuAir::rentangTeks($p->debit_min_lps, $p->debit_maks_lps, '') }}</span></td>
                <td><x-perubahan :d="PintuService::perubahan($b, $lalu)"/>@if($lalu)<span class="block text-[11px] text-basalt-mute">{{ $lalu->tanggal->translatedFormat('d M') }}: {{ angka_r($lalu->debit_lps) }}</span>@endif</td>
                <td class="whitespace-nowrap text-right"><span class="font-semibold">{{ angka_r($b->tma_cm) }}</span> <x-status-pintu :s="$p->statusTma($b->tma_cm)"/>
                    <span class="block text-[11px] text-basalt-mute">normal {{ PintuAir::rentangTeks($p->tma_min_cm, $p->tma_maks_cm, '') }}</span></td>
                <td><x-perubahan :d="PintuService::perubahan($b, $lalu, 'tma_cm')"/>@if($lalu)<span class="block text-[11px] text-basalt-mute">{{ $lalu->tanggal->translatedFormat('d M') }}: {{ angka_r($lalu->tma_cm) }}</span>@endif</td>
                <td class="text-right">{{ angka_r($b->bukaan_cm) }}</td>
                <td class="text-xs">{{ $b->catatan }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

@if($sesi->foto->isNotEmpty())
    <section class="panel p-4"><h2 class="mb-3 text-lg font-semibold">Foto</h2><x-galeri-foto :foto="$sesi->foto"/></section>
@endif
</x-layouts.app>
