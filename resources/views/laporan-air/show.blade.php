@php use App\Models\LaporanHarian as LH; $maks = max(1, $riwayat->max('debit_intake_lps')); @endphp
<x-layouts.app title="Laporan air">
<x-judul :judul="'Laporan air D.I. '.$laporan->daerahIrigasi->nama" :sub="$laporan->tanggal->translatedFormat('l, d F Y').' · '.$laporan->user->name">
    @if($bolehUbah)
        <a href="{{ route('laporan-air.edit', $laporan) }}" class="btn-ghost"><x-ikon n="edit" class="h-4 w-4"/> Ubah</a>
        <form method="POST" action="{{ route('laporan-air.destroy', $laporan) }}" onsubmit="return confirm('Hapus laporan air ini beserta fotonya?')">@csrf @method('DELETE')<button class="btn-ghost text-status-kritis"><x-ikon n="hapus" class="h-4 w-4"/> Hapus</button></form>
    @endif
</x-judul>
<x-tab-laporan aktif="air"/>
<div class="grid gap-5 lg:grid-cols-3">
    <div class="space-y-5 lg:col-span-2">
        <section class="panel p-5">
            <div class="flex flex-wrap items-center gap-2"><x-status :s="$laporan->status()" :label="'Saluran '.strtolower(LH::KONDISI[$laporan->kondisi_saluran] ?? '')"/>@if($laporan->mt)<span class="chip-kosong">MT {{ ['', 'I', 'II', 'III'][$laporan->mt] }}</span>@endif</div>
            <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-3">
                <div><dt class="text-xs text-basalt-soft">Debit sungai</dt><dd class="font-gauge text-2xl font-semibold">{{ angka_r($laporan->debit_sungai_m3s) }} <span class="text-sm">m³/dt</span></dd></div>
                <div><dt class="text-xs text-basalt-soft">TMA sungai</dt><dd class="font-gauge text-2xl font-semibold">{{ angka_r($laporan->tma_sungai_cm) }} <span class="text-sm">cm</span></dd></div>
                <div><dt class="text-xs text-basalt-soft">Debit intake</dt><dd class="font-gauge text-2xl font-semibold">{{ angka_r($laporan->debit_intake_lps) }} <span class="text-sm">L/dt</span></dd></div>
                <div><dt class="text-xs text-basalt-soft">TMA intake</dt><dd class="font-gauge text-2xl font-semibold">{{ angka_r($laporan->tma_intake_cm) }} <span class="text-sm">cm</span></dd></div>
                <div><dt class="text-xs text-basalt-soft">Bukaan pintu intake</dt><dd class="font-gauge text-2xl font-semibold">{{ $laporan->bukaan_pintu_persen !== null ? $laporan->bukaan_pintu_persen.' %' : '–' }}</dd></div>
                @if($laporan->debit_sungai_m3s)<div><dt class="text-xs text-basalt-soft">Diambil ke intake</dt><dd class="font-gauge text-2xl font-semibold">{{ angka_r(round($laporan->debit_intake_lps / ($laporan->debit_sungai_m3s * 1000) * 100, 1)) }} <span class="text-sm">% debit sungai</span></dd></div>@endif
            </dl>
            @if($laporan->catatan)<div class="mt-4 border-t border-savana-line pt-3 text-sm"><p class="text-basalt-soft">Catatan</p><p class="whitespace-pre-line">{{ $laporan->catatan }}</p></div>@endif
            @if($laporan->lat)<p class="mt-3 text-sm"><a href="https://www.google.com/maps?q={{ $laporan->lat }},{{ $laporan->lng }}" target="_blank" rel="noopener" class="font-medium text-saluran-600"><x-ikon n="lokasi" class="inline h-4 w-4"/> {{ number_format($laporan->lat, 6) }}, {{ number_format($laporan->lng, 6) }}</a></p>@endif
        </section>
        @if($laporan->foto->isNotEmpty())<section class="panel p-5"><h2 class="mb-3 text-lg font-semibold">Foto</h2><x-galeri-foto :foto="$laporan->foto"/></section>@endif
    </div>
    <section class="panel p-5">
        <h2 class="text-lg font-semibold">Debit intake 14 laporan terakhir</h2>
        <ul class="mt-3 space-y-1.5">
            @foreach($riwayat as $r)
                <li class="flex items-center gap-2 text-xs">
                    <span class="w-14 shrink-0 text-basalt-soft">{{ $r->tanggal->translatedFormat('d M') }}</span>
                    <span class="h-2.5 rounded-full" style="width: {{ max(2, $r->debit_intake_lps / $maks * 100) }}%; background: {{ \App\Support\StatusKondisi::WARNA[$r->status()] }}"></span>
                    <span class="shrink-0 tabular-nums">{{ angka_r($r->debit_intake_lps) }}</span>
                </li>
            @endforeach
        </ul>
    </section>
</div>
</x-layouts.app>
