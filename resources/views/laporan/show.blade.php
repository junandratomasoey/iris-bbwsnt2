@php
    use App\Models\LaporanHarian as LH;
    use App\Support\KodeIsian as K;
    $s = $laporan->subDaerahIrigasi;
    $maxTanam = max(1, $riwayat->max('luas_tanam_ha'));
    $kodePetak = $laporan->petak->pluck('pintu.kode')->filter();
@endphp
<x-layouts.app :title="'Laporan PPA '.$s->nama">
<x-judul :judul="'Laporan PPA · '.$s->nama" :sub="'D.I. '.$s->daerahIrigasi->nama.' · '.$laporan->tanggal->translatedFormat('l, d F Y').($laporan->jam ? ' · pukul '.substr($laporan->jam, 0, 5) : '').($kodePetak->isNotEmpty() ? ' · '.$kodePetak->count().' petak' : '')">
    @can('update', $laporan)<a href="{{ route('laporan.edit', $laporan) }}" class="btn-ghost"><x-ikon n="edit" class="h-4 w-4"/> Ubah</a>@endcan
    @can('delete', $laporan)
        <form method="POST" action="{{ route('laporan.destroy', $laporan) }}" onsubmit="return confirm('Hapus laporan ini beserta fotonya?')">@csrf @method('DELETE')
            <button class="btn-ghost text-status-kritis"><x-ikon n="hapus" class="h-4 w-4"/> Hapus</button></form>
    @endcan
</x-judul>
<x-tab-laporan aktif="tanaman"/>

<div class="grid gap-5 lg:grid-cols-3">
    <div class="space-y-5 lg:col-span-2">
        {{-- A. Identitas --}}
        <section class="panel p-5">
            <div class="flex flex-wrap items-center gap-2">
                <x-status :s="$laporan->status()"/><x-verifikasi :s="$laporan->status_verifikasi"/>
                <span class="chip-kosong">{{ $laporan->jenis_label }}</span>
                @if($laporan->mt)<span class="chip-kosong">{{ K::MT[$laporan->mt] ?? 'MT '.$laporan->mt }}</span>@endif
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-4">
                <div><dt class="text-basalt-soft">Kabupaten</dt><dd class="font-medium">{{ $s->daerahIrigasi->kabupaten?->nama ?? '–' }}</dd></div>
                <div><dt class="text-basalt-soft">Cuaca</dt><dd class="font-medium">{{ K::CUACA[$laporan->cuaca] ?? '–' }}</dd></div>
                <div><dt class="text-basalt-soft">Hujan 7 hari</dt><dd class="font-medium">{{ K::HUJAN_7_HARI[$laporan->hujan_7hari] ?? '–' }}</dd></div>
                <div><dt class="text-basalt-soft">Tindakan pintu</dt><dd class="font-medium">{{ K::TINDAKAN_PINTU[$laporan->tindakan_operasi] ?? '–' }}@if($laporan->atas_perintah)<span class="block text-xs font-normal text-basalt-soft">atas perintah {{ $laporan->atas_perintah }}</span>@endif</dd></div>
            </dl>
            <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-3 border-t border-savana-line pt-4 text-sm sm:grid-cols-4">
                <div><dt class="text-basalt-soft">Luas tanam</dt><dd class="font-gauge text-xl font-semibold">{{ angka($laporan->luas_tanam_ha, 2) }} <span class="text-sm">Ha</span></dd></div>
                <div><dt class="text-basalt-soft">Terdampak kering</dt><dd class="font-gauge text-xl font-semibold text-status-siaga">{{ angka($laporan->luas_kering_ha, 2) }} <span class="text-sm">Ha</span></dd></div>
                <div><dt class="text-basalt-soft">Sudah panen</dt><dd class="font-gauge text-xl font-semibold">{{ angka($laporan->luas_panen_ha, 2) }} <span class="text-sm">Ha</span></dd></div>
                <div><dt class="text-basalt-soft">Petak kekurangan air</dt><dd class="font-gauge text-xl font-semibold">{{ $laporan->petakKekuranganAir() }} <span class="text-sm">dari {{ $laporan->petak->count() }}</span></dd></div>
            </dl>
            @if($laporan->debit_intake_lps !== null)
                <p class="mt-4 border-t border-savana-line pt-3 text-xs text-basalt-mute">Form lama (sebelum laporan air dipisah ke Manager): debit {{ angka($laporan->debit_intake_lps, 1) }} L/dt · TMA {{ angka($laporan->tma_cm, 1) }} cm · saluran {{ LH::KONDISI[$laporan->kondisi_saluran] ?? '–' }}.</p>
            @endif
        </section>

        {{-- B. Pembacaan pintu --}}
        <section class="panel overflow-x-auto p-5">
            <h2 class="text-lg font-semibold"><span class="bagian-huruf">B</span>Pembacaan pintu air</h2>
            @if($laporan->sesiPintu && $laporan->sesiPintu->pembacaan->isNotEmpty())
                <table class="table-base mt-2">
                    <thead><tr><th>Pintu</th><th class="text-right">TMA hulu (cm)</th><th class="text-right">Bukaan (cm)</th><th class="text-right">Debit (L/dt)</th><th>Kondisi pintu</th><th>Kondisi saluran</th><th>Keterangan</th></tr></thead>
                    <tbody>
                    @foreach($laporan->sesiPintu->pembacaan->sortBy(fn ($b) => [\App\Models\PintuAir::URUTAN_TINGKAT[$b->pintu->tingkat] ?? 9, $b->pintu->kode]) as $b)
                        <tr @class(['bg-[#FBF0CC]/40' => $b->bermasalah()])>
                            <td class="whitespace-nowrap"><a href="{{ route('pintu.show', $b->pintu) }}" class="font-semibold text-saluran-600">{{ $b->pintu->kode }}</a><span class="block text-xs text-basalt-soft">{{ $b->pintu->tingkat_label }}</span></td>
                            <td class="whitespace-nowrap text-right">{{ angka_r($b->tma_cm) }} <x-status-pintu :s="$b->pintu->statusTma($b->tma_cm)"/></td>
                            <td class="text-right">{{ angka_r($b->bukaan_cm) }}</td>
                            <td class="whitespace-nowrap text-right">{{ angka_r($b->debit_lps) }} <x-status-pintu :s="$b->pintu->statusDebit($b->debit_lps)"/></td>
                            <td class="whitespace-nowrap text-xs" title="{{ K::teks(K::KONDISI_PINTU, $b->kondisi_pintu) }}">{{ K::singkat(K::KONDISI_PINTU, $b->kondisi_pintu) ?? '–' }}</td>
                            <td class="whitespace-nowrap text-xs">{{ K::singkat(K::KONDISI_SALURAN, $b->kondisi_saluran) ?? '–' }}</td>
                            <td class="text-xs">{{ $b->catatan }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @else
                <p class="mt-2 text-sm text-basalt-soft">Tidak ada pembacaan pintu di laporan ini.</p>
            @endif
        </section>

        {{-- C. Kondisi tanaman per petak --}}
        <section class="panel overflow-x-auto p-5">
            <h2 class="text-lg font-semibold"><span class="bagian-huruf">C</span>Kondisi tanaman per petak tersier</h2>
            <table class="table-base mt-2">
                <thead><tr><th>Petak</th><th class="text-right">Luas baku</th><th class="text-right">Luas tanam</th><th>Tanaman</th><th>Fase</th><th class="text-right">Umur</th><th>Kecukupan air</th><th class="text-right">Kering</th><th>Status</th></tr></thead>
                <tbody>
                @forelse($laporan->petak->sortBy(fn ($b) => $b->pintu?->kode) as $b)
                    <tr>
                        <td class="whitespace-nowrap"><span class="font-semibold">{{ $b->pintu?->kode ?? 'Seluruh Sub-D.I.' }}</span>@if($b->keterangan)<span class="block max-w-[12rem] text-xs text-basalt-soft">{{ $b->keterangan }}</span>@endif</td>
                        <td class="text-right">{{ $b->luas_baku_ha !== null ? angka_r($b->luas_baku_ha) : '–' }}</td>
                        <td class="text-right">{{ angka_r($b->luas_tanam_ha) }}</td>
                        <td class="text-xs">{{ K::teks(K::JENIS_TANAMAN, $b->jenis_tanaman) ?? '–' }}</td>
                        <td class="text-xs" title="{{ K::teks(K::FASE, $b->fase) }}">{{ K::singkat(K::FASE, $b->fase) ?? '–' }}</td>
                        <td class="whitespace-nowrap text-right">{{ $b->umur_hst !== null ? $b->umur_hst.' HST' : '–' }}</td>
                        <td class="whitespace-nowrap text-xs" title="{{ K::teks(K::KECUKUPAN_AIR, $b->kecukupan_air) }}">{{ K::singkat(K::KECUKUPAN_AIR, $b->kecukupan_air) ?? '–' }}</td>
                        <td class="text-right">{{ angka_r($b->luas_kering_ha) }}</td>
                        <td><x-status :s="$b->status ?? $b->hitungStatus()"/></td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-basalt-soft">Belum ada rincian petak.</td></tr>
                @endforelse
                </tbody>
            </table>
            <p class="mt-2 text-xs text-basalt-mute">Luas dalam Ha. Status petak dari kode kecukupan air (C = Aman, KR = Waspada, KS = Siaga, KB = Kritis; Bj = kelebihan air).</p>
        </section>

        {{-- D. Permasalahan --}}
        @if($laporan->kendala || $laporan->usulan || $laporan->dokumentasi)
            <section class="panel space-y-3 p-5 text-sm">
                <h2 class="text-lg font-semibold"><span class="bagian-huruf">D</span>Permasalahan dan dokumentasi</h2>
                @if($laporan->kendala)<div><p class="font-semibold">Permasalahan</p><p class="whitespace-pre-line">{{ $laporan->kendala }}</p></div>@endif
                @if($laporan->usulan)<div><p class="font-semibold">Usulan / tindak lanjut</p><p class="whitespace-pre-line">{{ $laporan->usulan }}</p></div>@endif
                @if($laporan->dokumentasi)
                    <div><p class="font-semibold">Dokumentasi foto</p>
                        <ul class="mt-1 grid gap-1 sm:grid-cols-2">@foreach(K::DOKUMENTASI_PPA as $k => $l)<li class="{{ in_array($k, $laporan->dokumentasi) ? '' : 'text-basalt-mute line-through' }}">{{ in_array($k, $laporan->dokumentasi) ? '☑' : '☐' }} {{ $l }}</li>@endforeach</ul></div>
                @endif
            </section>
        @endif

        @if($laporan->foto->isNotEmpty())
            <section class="panel p-5">
                <h2 class="mb-3 text-lg font-semibold">Foto lapangan</h2>
<x-galeri-foto :foto="$laporan->foto"/>
            </section>
        @endif

        <section class="panel p-5">
            <h2 class="text-lg font-semibold">Luas tanam &amp; kekeringan, 14 laporan terakhir</h2>
            @if($riwayat->count() < 2)
                <p class="mt-2 text-sm text-basalt-soft">Grafik muncul setelah ada minimal dua laporan petugas ini untuk Sub-D.I. ini.</p>
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
