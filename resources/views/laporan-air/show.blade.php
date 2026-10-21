@php
    use App\Support\KodeIsian as K;
    $l = $laporan;
    $maks = max(1, $riwayat->max('debit_intake_lps'));
    $baca = $l->sesiPintu?->pembacaan ?? collect();
    $titik = $baca->filter(fn ($b) => $b->pintu->tingkat === 'primer')->sortBy(fn ($b) => [$b->pintu->urutan, $b->pintu->kode]);
    $nilai = fn ($v, $satuan) => $v !== null ? angka_r($v).' '.$satuan : '–';
@endphp
<x-layouts.app title="Laporan Manager Air">
<x-judul :judul="'Laporan Manager Air · D.I. '.$l->daerahIrigasi->nama" :sub="$l->tanggal->translatedFormat('l, d F Y').($l->jam ? ' · pukul '.substr($l->jam, 0, 5) : '').' · '.$l->user->name">
    @if($bolehUbah)
        <a href="{{ route('laporan-air.edit', $l) }}" class="btn-ghost"><x-ikon n="edit" class="h-4 w-4"/> Ubah</a>
        <form method="POST" action="{{ route('laporan-air.destroy', $l) }}" onsubmit="return confirm('Hapus laporan ini beserta fotonya?')">@csrf @method('DELETE')<button class="btn-ghost text-status-kritis"><x-ikon n="hapus" class="h-4 w-4"/> Hapus</button></form>
    @endif
</x-judul>
<x-tab-laporan aktif="air"/>
<div class="grid gap-5 lg:grid-cols-3">
    <div class="space-y-5 lg:col-span-2">
        {{-- A --}}
        <section class="panel p-5">
            <div class="flex flex-wrap items-center gap-2">
                <x-status :s="$l->status()"/>
                <span class="chip-kosong">{{ $l->jenis_label }}</span>
                @if($l->mt)<span class="chip-kosong">{{ K::MT[$l->mt] ?? 'MT '.$l->mt }}</span>@endif
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-4">
                <div><dt class="text-basalt-soft">Kabupaten</dt><dd class="font-medium">{{ $l->daerahIrigasi->kabupaten?->nama ?? '–' }}</dd></div>
                <div><dt class="text-basalt-soft">Luas baku D.I.</dt><dd class="font-medium">{{ ($lb = $l->daerahIrigasi->luas_baku_ha ?: $l->daerahIrigasi->luas_permen_ha) ? angka_r($lb).' Ha' : '–' }}</dd></div>
                <div><dt class="text-basalt-soft">Cuaca</dt><dd class="font-medium">{{ K::CUACA[$l->cuaca] ?? '–' }}</dd></div>
                <div><dt class="text-basalt-soft">Curah hujan</dt><dd class="font-medium">{{ $nilai($l->curah_hujan_mm, 'mm') }}</dd></div>
            </dl>
        </section>

        {{-- B --}}
        <section class="panel p-5">
            <h2 class="text-lg font-semibold"><span class="bagian-huruf">B</span>Bangunan utama (bendung / intake)</h2>
            @if($l->kondisi_saluran && ! $l->bukaan_intake_cm && ! $l->kondisi_bendung)
                <p class="mt-1 text-xs text-basalt-mute">Laporan format lama: kondisi saluran umum {{ \App\Models\LaporanHarian::KONDISI[$l->kondisi_saluran] ?? $l->kondisi_saluran }}@if($l->bukaan_pintu_persen !== null), bukaan intake {{ $l->bukaan_pintu_persen }}%@endif.</p>
            @endif
            <dl class="mt-3 grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-3">
                <div><dt class="text-xs text-basalt-soft">TMA hulu bendung</dt><dd class="font-gauge text-2xl font-semibold">{{ $nilai($l->tma_sungai_cm, 'cm') }}</dd></div>
                <div><dt class="text-xs text-basalt-soft">Air melimpas di mercu</dt><dd class="font-gauge text-2xl font-semibold">{{ $l->melimpas === null ? '–' : ($l->melimpas ? 'Ya' : 'Tidak') }}@if($l->melimpas && $l->tinggi_limpasan_cm !== null) <span class="text-sm">{{ angka_r($l->tinggi_limpasan_cm) }} cm</span>@endif</dd></div>
                <div><dt class="text-xs text-basalt-soft">Bukaan pintu pengambilan</dt><dd class="font-gauge text-2xl font-semibold">{{ $nilai($l->bukaan_intake_cm, 'cm') }}</dd></div>
                <div><dt class="text-xs text-basalt-soft">TMA hilir intake</dt><dd class="font-gauge text-2xl font-semibold">{{ $nilai($l->tma_intake_cm, 'cm') }}</dd></div>
                <div><dt class="text-xs text-basalt-soft">Debit masuk saluran induk</dt><dd class="font-gauge text-2xl font-semibold">{{ $nilai($l->debit_intake_lps, 'L/dt') }}</dd></div>
                <div><dt class="text-xs text-basalt-soft">Bukaan pintu penguras</dt><dd class="font-gauge text-2xl font-semibold">{{ $nilai($l->bukaan_penguras_cm, 'cm') }}</dd></div>
                @if($l->debit_sungai_m3s !== null)<div><dt class="text-xs text-basalt-soft">Debit sungai</dt><dd class="font-gauge text-2xl font-semibold">{{ angka_r($l->debit_sungai_m3s) }} <span class="text-sm">m³/dt</span></dd></div>@endif
                @if($l->debit_sungai_m3s && $l->debit_intake_lps)<div><dt class="text-xs text-basalt-soft">Diambil ke intake</dt><dd class="font-gauge text-2xl font-semibold">{{ angka_r(round($l->debit_intake_lps / ($l->debit_sungai_m3s * 1000) * 100, 1)) }} <span class="text-sm">% debit sungai</span></dd></div>@endif
            </dl>
            <dl class="mt-4 grid gap-2 border-t border-savana-line pt-3 text-sm sm:grid-cols-3">
                <div><dt class="text-basalt-soft">Kondisi bendung</dt><dd class="font-medium">{{ K::teks(K::KONDISI_BENDUNG, $l->kondisi_bendung) ?? '–' }}</dd></div>
                <div><dt class="text-basalt-soft">Kondisi pintu intake</dt><dd class="font-medium">{{ K::teks(K::KONDISI_PINTU, $l->kondisi_pintu_intake) ?? '–' }}</dd></div>
                <div><dt class="text-basalt-soft">Kantong lumpur</dt><dd class="font-medium">{{ K::teks(K::KANTONG_LUMPUR, $l->kondisi_kantong_lumpur) ?? '–' }}</dd></div>
            </dl>
        </section>

        {{-- C --}}
        <section class="panel overflow-x-auto p-5">
            <h2 class="text-lg font-semibold"><span class="bagian-huruf">C</span>Saluran primer (titik ukur / bangunan bagi)</h2>
            @if($titik->isNotEmpty())
                <table class="table-base mt-2">
                    <thead><tr><th>No</th><th>Bangunan</th><th class="text-right">TMA (cm)</th><th class="text-right">Debit terukur</th><th class="text-right">Debit kebutuhan</th><th class="text-right">Selisih</th><th>Kondisi saluran</th><th>Keterangan</th></tr></thead>
                    <tbody>
                    @foreach($titik->values() as $i => $b)
                        @php $sel = $b->selisih(); @endphp
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td><a href="{{ route('pintu.show', $b->pintu) }}" class="font-semibold text-saluran-600">{{ $b->pintu->kode }}</a>@if($b->pintu->nama)<span class="block text-xs text-basalt-soft">{{ $b->pintu->nama }}</span>@endif</td>
                            <td class="text-right">{{ angka_r($b->tma_cm) }}</td>
                            <td class="whitespace-nowrap text-right">{{ angka_r($b->debit_lps) }}</td>
                            <td class="whitespace-nowrap text-right">{{ angka_r($b->debit_kebutuhan_lps) }}</td>
                            <td class="whitespace-nowrap text-right font-semibold {{ $sel === null ? '' : ($sel < 0 ? 'text-[#8A3510]' : 'text-[#2F5A19]') }}">{{ $sel === null ? '–' : ($sel > 0 ? '+' : '').angka_r($sel) }}</td>
                            <td class="whitespace-nowrap text-xs">{{ K::singkat(K::KONDISI_SALURAN, $b->kondisi_saluran) ?? '–' }}</td>
                            <td class="text-xs">{{ $b->catatan }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                <p class="mt-1 text-xs text-basalt-mute">Debit dalam L/dt. Selisih: + di atas kebutuhan, − di bawah kebutuhan.</p>
            @else
                <p class="mt-2 text-sm text-basalt-soft">Tidak ada pembacaan titik ukur primer.</p>
            @endif
            <p class="mt-3 text-sm"><span class="text-basalt-soft">Status pemberian air:</span> <b>{{ K::PEMBERIAN_AIR[$l->status_pemberian] ?? '–' }}</b>@if($l->atas_perintah) <span class="text-basalt-soft">— atas perintah {{ $l->atas_perintah }}</span>@endif</p>
        </section>

        {{-- D --}}
        <section class="panel overflow-x-auto p-5">
            <h2 class="text-lg font-semibold"><span class="bagian-huruf">D</span>Rekap laporan PPA</h2>
            <p class="text-xs text-basalt-soft">Pekan {{ $rekapPpa['senin'] }} – {{ $rekapPpa['akhir'] }}, dihitung dari laporan PPA yang masuk.</p>
            <table class="table-base mt-2">
                <thead><tr><th>Sub-D.I.</th><th>Nama PPA</th><th>Laporan masuk</th><th class="text-right">Pintu bermasalah</th><th class="text-right">Petak kekurangan air</th></tr></thead>
                <tbody>
                @forelse($rekapPpa['sub'] as $s)
                    <tr>
                        <td class="font-medium">@if($s['url'])<a href="{{ $s['url'] }}" class="text-saluran-600">{{ $s['nama'] }}</a>@else{{ $s['nama'] }}@endif</td>
                        <td class="text-xs">{{ $s['ppa'] ?: '–' }}</td>
                        <td>@if($s['masuk'])<span class="chip-aman">Ya ({{ $s['masuk'] }})</span>@else<span class="chip-siaga">Belum</span>@endif</td>
                        <td class="text-right tabular-nums">{{ $s['pintu_bermasalah'] }}</td>
                        <td class="text-right tabular-nums">{{ $s['petak_kurang_air'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-basalt-soft">D.I. ini belum punya Sub-D.I.</td></tr>
                @endforelse
                </tbody>
            </table>
        </section>

        {{-- E --}}
        <section class="panel space-y-3 p-5 text-sm">
            <h2 class="text-lg font-semibold"><span class="bagian-huruf">E</span>Permasalahan dan dokumentasi</h2>
            <div><p class="font-semibold">Permasalahan</p><p class="whitespace-pre-line">{{ $l->catatan ?: '–' }}</p></div>
            @if($l->usulan)<div><p class="font-semibold">Usulan tindak lanjut ke Balai</p><p class="whitespace-pre-line">{{ $l->usulan }}</p></div>@endif
            @if($l->dokumentasi)
                <ul class="grid gap-1 sm:grid-cols-2">@foreach(K::DOKUMENTASI_MANAGER as $k => $lb)<li class="{{ in_array($k, $l->dokumentasi) ? '' : 'text-basalt-mute line-through' }}">{{ in_array($k, $l->dokumentasi) ? '☑' : '☐' }} {{ $lb }}</li>@endforeach</ul>
            @endif
            @if($l->lat)<p><a href="https://www.google.com/maps?q={{ $l->lat }},{{ $l->lng }}" target="_blank" rel="noopener" class="font-medium text-saluran-600"><x-ikon n="lokasi" class="inline h-4 w-4"/> {{ number_format($l->lat, 6) }}, {{ number_format($l->lng, 6) }}</a></p>@endif
            @if($l->foto->isNotEmpty())<x-galeri-foto :foto="$l->foto"/>@endif
        </section>
    </div>
    <section class="panel self-start p-5">
        <h2 class="text-lg font-semibold">Debit masuk saluran induk, 14 laporan terakhir</h2>
        <ul class="mt-3 space-y-1.5">
            @foreach($riwayat as $r)
                <li class="flex items-center gap-2 text-xs">
                    <span class="w-14 shrink-0 text-basalt-soft">{{ $r->tanggal->translatedFormat('d M') }}</span>
                    <span class="h-2.5 rounded-full" style="width: {{ max(2, (float) $r->debit_intake_lps / $maks * 100) }}%; background: {{ \App\Support\StatusKondisi::WARNA[$r->status()] }}"></span>
                    <span class="shrink-0 tabular-nums">{{ angka_r($r->debit_intake_lps) }}</span>
                </li>
            @endforeach
        </ul>
    </section>
</div>
</x-layouts.app>
