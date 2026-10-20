@php
    use App\Models\LaporanHarian as LH;
    $s = $laporan->subDaerahIrigasi;
    $maxTanam = max(1, $riwayat->max('luas_tanam_ha'));
@endphp
<x-layouts.app :title="'Laporan '.$s->nama">
<x-judul :judul="$s->nama.($laporan->petak ? ' · Petak '.$laporan->petak->kode : '')" :sub="'D.I. '.$s->daerahIrigasi->nama.($laporan->petak?->nama ? ' · '.$laporan->petak->nama : '').' · '.$laporan->tanggal->translatedFormat('l, d F Y')">
    @can('update', $laporan)<a href="{{ route('laporan.edit', $laporan) }}" class="btn-ghost"><x-ikon n="edit" class="h-4 w-4"/> Ubah</a>@endcan
    @can('delete', $laporan)
        <form method="POST" action="{{ route('laporan.destroy', $laporan) }}" onsubmit="return confirm('Hapus laporan ini beserta fotonya?')">@csrf @method('DELETE')
            <button class="btn-ghost text-status-kritis"><x-ikon n="hapus" class="h-4 w-4"/> Hapus</button></form>
    @endcan
</x-judul>
<x-tab-laporan aktif="tanaman"/>

<div class="grid gap-5 lg:grid-cols-3">
    <div class="space-y-5 lg:col-span-2">
        <section class="panel p-5">
            <div class="flex flex-wrap items-center gap-2"><x-status :s="$laporan->status()"/><x-verifikasi :s="$laporan->status_verifikasi"/>@if($laporan->mt)<span class="chip-kosong">MT {{ ['', 'I', 'II', 'III'][$laporan->mt] }}</span>@endif</div>
            @if($laporan->debit_intake_lps !== null)
            <p class="mt-3 text-xs text-basalt-mute">Data air di bawah berasal dari form lama (sebelum laporan air dipisah ke Manager).</p>
            <dl class="mt-2 grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-4">
                <div><dt class="text-xs text-basalt-soft">{{ $laporan->petak ? 'Debit pintu '.$laporan->petak->kode : 'Debit intake' }}</dt><dd class="font-gauge text-2xl font-semibold">{{ angka($laporan->debit_intake_lps, 1) }} <span class="text-sm">L/dt</span></dd></div>
                <div><dt class="text-xs text-basalt-soft">TMA</dt><dd class="font-gauge text-2xl font-semibold">{{ angka($laporan->tma_cm, 1) }} <span class="text-sm">cm</span></dd></div>
                <div><dt class="text-xs text-basalt-soft">Kondisi saluran</dt><dd class="font-gauge text-2xl font-semibold">{{ LH::KONDISI[$laporan->kondisi_saluran] ?? '-' }}</dd></div>
                <div><dt class="text-xs text-basalt-soft">Bukaan pintu</dt><dd class="font-gauge text-2xl font-semibold">{{ $laporan->bukaan_pintu_persen !== null ? $laporan->bukaan_pintu_persen.' %' : '–' }}</dd></div>
            </dl>
            @endif
            <dl class="mt-5 grid grid-cols-2 gap-x-6 gap-y-3 border-t border-savana-line pt-4 text-sm sm:grid-cols-3">
                <div><dt class="text-basalt-soft">Fase tanaman</dt><dd class="font-medium">{{ LH::FASE[$laporan->fase_tanaman] ?? '–' }}</dd></div>
                <div><dt class="text-basalt-soft">Luas tanam</dt><dd class="font-medium">{{ angka($laporan->luas_tanam_ha, 2) }} Ha @if($laporan->petak?->luas_layanan_ha)<span class="text-basalt-mute">dari {{ angka_r($laporan->petak->luas_layanan_ha) }} Ha petak</span>@elseif($s->luas_ha)<span class="text-basalt-mute">dari {{ angka($s->luas_ha) }} Ha</span>@endif</dd></div>
                <div class="col-span-2 sm:col-span-3"><dt class="text-basalt-soft">Komoditas</dt>
                    <dd class="mt-1 flex flex-wrap gap-2">
                        @forelse($laporan->komoditas() as $k)
                            <span class="rounded-md border border-savana-line px-2.5 py-1"><b>{{ $k['label'] }}</b> {{ angka($k['luas'], 2) }} Ha @if($k['jenis'])<span class="text-basalt-soft">· {{ $k['jenis'] }}</span>@endif</span>
                        @empty
                            <span class="text-basalt-mute">Tidak ada tanaman (bera)</span>
                        @endforelse
                    </dd></div>
                <div><dt class="text-basalt-soft">Tanaman sehat</dt><dd class="font-medium">{{ angka($laporan->luas_hijau_ha, 2) }} Ha</dd></div>
                <div><dt class="text-basalt-soft">Tanaman kekeringan</dt><dd class="font-medium text-status-siaga">{{ angka($laporan->luas_kering_ha, 2) }} Ha</dd></div>
                <div><dt class="text-basalt-soft">Puso</dt><dd class="font-medium text-status-kritis">{{ angka($laporan->luas_puso_ha, 2) }} Ha</dd></div>
                <div><dt class="text-basalt-soft">Panen</dt><dd class="font-medium">{{ angka($laporan->luas_panen_ha, 2) }} Ha</dd></div>
            </dl>
            @if($laporan->kendala)
                <div class="mt-4 rounded-md bg-savana px-4 py-3 text-sm"><p class="font-semibold">Kendala</p><p class="whitespace-pre-line">{{ $laporan->kendala }}</p></div>
            @endif
        </section>
        @if($laporan->sesiPintu && $laporan->sesiPintu->pembacaan->isNotEmpty())
            <section class="panel overflow-x-auto p-5">
                <h2 class="text-lg font-semibold">Debit &amp; TMA pintu air</h2>
                <table class="table-base mt-2">
                    <thead><tr><th>Pintu</th><th class="text-right">Debit (L/dt)</th><th class="text-right">TMA mistar (cm)</th><th class="text-right">Q rencana</th></tr></thead>
                    <tbody>
                    @foreach($laporan->sesiPintu->pembacaan->sortBy(fn ($b) => [\App\Models\PintuAir::URUTAN_TINGKAT[$b->pintu->tingkat] ?? 9, $b->pintu->kode]) as $b)
                        <tr>
                            <td><a href="{{ route('pintu.show', $b->pintu) }}" class="font-semibold text-saluran-600">{{ $b->pintu->kode }}</a><span class="block text-xs text-basalt-soft">{{ $b->pintu->tingkat_label }}</span></td>
                            <td class="whitespace-nowrap text-right">{{ angka_r($b->debit_lps) }} <x-status-pintu :s="$b->pintu->statusDebit($b->debit_lps)"/></td>
                            <td class="whitespace-nowrap text-right">{{ angka_r($b->tma_cm) }} <x-status-pintu :s="$b->pintu->statusTma($b->tma_cm)"/></td>
                            <td class="text-right text-xs">{{ $b->pintu->debit_rencana_lps ? angka_r($b->pintu->debit_rencana_lps).' L/dt' : '–' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </section>
        @endif

        @if($laporan->foto->isNotEmpty())
            <section class="panel p-5">
                <h2 class="mb-3 text-lg font-semibold">Foto lapangan</h2>
<x-galeri-foto :foto="$laporan->foto"/>
            </section>
        @endif

        <section class="panel p-5">
            <h2 class="text-lg font-semibold">Kekeringan 14 laporan terakhir</h2>
            @if($riwayat->count() < 2)
                <p class="mt-2 text-sm text-basalt-soft">Grafik muncul setelah ada minimal dua laporan untuk {{ $laporan->petak ? 'petak' : 'Sub-D.I.' }} ini.</p>
            @else
                <div class="mt-4 flex h-40 items-end gap-1.5" role="img" aria-label="Grafik luas tanam dan kekeringan">
                    @foreach($riwayat as $r)
                        <div class="group relative flex h-full flex-1 flex-col items-center justify-end" title="{{ $r->tanggal->format('d/m') }}: tanam {{ angka($r->luas_tanam_ha, 1) }} Ha, kering {{ angka($r->luas_kering_ha, 1) }} Ha">
                            <span class="mb-1 hidden text-[10px] tabular-nums text-basalt-soft group-hover:block">{{ angka($r->luas_kering_ha, 1) }}</span>
                            <div class="relative w-full max-w-[28px] rounded-t bg-saluran-100" style="height: {{ max(2, $r->luas_tanam_ha / $maxTanam * 100) }}%">
                                <div class="absolute inset-x-0 bottom-0 rounded-t {{ $r->id === $laporan->id ? 'bg-[#D9622B]' : 'bg-[#EBA07A]' }}" style="height: {{ $r->luas_tanam_ha > 0 ? min(100, $r->luas_kering_ha / $r->luas_tanam_ha * 100) : 0 }}%"></div>
                            </div>
                            <span class="mt-1 text-[10px] text-basalt-mute">{{ $r->tanggal->format('d/m') }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="mt-2 text-xs text-basalt-mute">Batang = luas tanam; bagian oranye = luas kekeringan.</p>
            @endif
        </section>
    </div>

    <div class="space-y-5">
        <section class="panel p-5 text-sm">
            <h2 class="mb-3 text-lg font-semibold">Pelapor</h2>
            <p class="font-semibold">{{ $laporan->user->name }}</p>
            <p class="text-basalt-soft">{{ $laporan->user->jabatan ?? $laporan->user->roleLabel() }} · dikirim {{ $laporan->created_at->translatedFormat('d M Y H:i') }}</p>
            @if($laporan->user->waLink() && $laporan->user_id !== auth()->id())
                <a href="{{ $laporan->user->waLink('Selamat siang, terkait laporan IRIS '.$s->nama.' tanggal '.$laporan->tanggal->format('d/m/Y').':') }}" target="_blank" rel="noopener" class="btn-wa mt-2"><x-ikon n="wa" class="h-3.5 w-3.5"/> Hubungi via WhatsApp</a>
            @endif
            @if($laporan->lat)
                <p class="mt-3 text-basalt-soft">Titik lokasi: <a class="text-saluran-600 underline" target="_blank" rel="noopener" href="https://www.google.com/maps?q={{ $laporan->lat }},{{ $laporan->lng }}">{{ number_format($laporan->lat, 5) }}, {{ number_format($laporan->lng, 5) }}</a></p>
            @endif
        </section>

        <section class="panel p-5 text-sm">
            <h2 class="mb-3 text-lg font-semibold">Verifikasi</h2>
            @if($laporan->verifikator)
                <p><x-verifikasi :s="$laporan->status_verifikasi"/> oleh <b>{{ $laporan->verifikator->name }}</b>, {{ $laporan->diverifikasi_pada->translatedFormat('d M Y H:i') }}</p>
                @if($laporan->catatan_verifikasi)<p class="mt-2 rounded bg-savana px-3 py-2">{{ $laporan->catatan_verifikasi }}</p>@endif
            @else
                <p class="text-basalt-soft">Belum diverifikasi.</p>
            @endif
            @cannot('verify', $laporan)
                @if($laporan->status_verifikasi !== 'disetujui')
                    @php $calon = $laporan->calonVerifikator(); @endphp
                    <div class="mt-4 border-t border-savana-line pt-3">
                        <p class="font-medium">{{ $laporan->status_verifikasi === 'perbaikan' ? 'Setelah diperbaiki, laporan diperiksa lagi oleh:' : 'Akan diverifikasi oleh salah satu:' }}</p>
                        <ul class="mt-2 space-y-1.5">
                            @forelse($calon->take(6) as $v)
                                <li class="flex items-center justify-between gap-2">
                                    <span>{{ $v->name }} <span class="text-xs text-basalt-mute">{{ $v->roleLabel() }}{{ $v->jabatan && $v->jabatan !== $v->roleLabel() ? ' · '.$v->jabatan : '' }}</span></span>
                                    @if($v->waLink())<a href="{{ $v->waLink('Selamat siang, mohon verifikasi laporan IRIS '.$s->nama.' tanggal '.$laporan->tanggal->format('d/m/Y').': '.route('laporan.show', $laporan)) }}" target="_blank" rel="noopener" class="shrink-0 text-xs font-semibold text-[#1f8f4e]">WA</a>@endif
                                </li>
                            @empty
                                <li class="text-basalt-soft">Belum ada Manager/Koordinator untuk wilayah ini. Hubungi Admin OP.</li>
                            @endforelse
                        </ul>
                        @if($laporan->status_verifikasi === 'menunggu')<p class="mt-2 text-xs text-basalt-mute">Laporan masih bisa diubah sampai disetujui.</p>@endif
                    </div>
                @endif
            @endcannot
            @can('verify', $laporan)
                <form method="POST" action="{{ route('laporan.verifikasi', $laporan) }}" class="mt-4 space-y-3 border-t border-savana-line pt-4" x-data="{ k: 'disetujui' }">
                    @csrf
                    <p class="font-medium">{{ $laporan->status_verifikasi === 'menunggu' ? 'Periksa angka & foto, lalu putuskan:' : 'Ubah keputusan:' }}</p>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="cursor-pointer"><input type="radio" name="keputusan" value="disetujui" x-model="k" class="peer sr-only"><span class="block rounded-md border border-savana-line py-2 text-center font-medium peer-checked:border-status-aman peer-checked:bg-[#E4F0DD]">Setujui</span></label>
                        <label class="cursor-pointer"><input type="radio" name="keputusan" value="perbaikan" x-model="k" class="peer sr-only"><span class="block rounded-md border border-savana-line py-2 text-center font-medium peer-checked:border-status-kritis peer-checked:bg-[#F6D9D7]">Minta perbaikan</span></label>
                    </div>
                    <textarea name="catatan_verifikasi" rows="2" class="input" :placeholder="k === 'perbaikan' ? 'Tulis apa yang perlu diperbaiki' : 'Catatan (opsional)'" :required="k === 'perbaikan'"></textarea>
                    <div class="flex flex-wrap gap-2">
                        <button class="btn-primary">Simpan keputusan</button>
                        <button name="lanjut" value="1" class="btn-ghost">Simpan &amp; laporan berikutnya</button>
                    </div>
                </form>
            @endcan
        </section>
    </div>
</div>
@if(session('hapus_draft'))
    <script>try { Object.keys(localStorage).filter(k => k.startsWith('iris-draf-laporan-')).forEach(k => localStorage.removeItem(k)); } catch (e) {}</script>
@endif
</x-layouts.app>
