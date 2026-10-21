<x-layouts.app title="Beranda petugas">
<x-judul :judul="'Halo, '.\Illuminate\Support\Str::of(auth()->user()->name)->words(2, '')" :sub="today()->translatedFormat('l, d F Y')"/>

<x-pasang-aplikasi gaya="kartu" class="mb-5"/>

<section aria-labelledby="judul-tugas">
    <h2 id="judul-tugas" class="mb-1 text-lg font-semibold">Laporan PPA pekan ini</h2>
    <p class="mb-2 text-sm text-basalt-soft">Rutin setiap Senin pukul 07.00–09.00 WITA: baca semua pintu tugas Anda dan isi kondisi tanaman tiap petak tersier. Di luar Senin, kirim laporan insidental bila ada panen, pengolahan lahan, banjir, kerusakan pintu, atau kekurangan air.</p>
    @if($subs->isEmpty())
        <div class="panel"><x-kosong judul="Anda belum ditugaskan ke Sub-D.I.">Minta Admin OP mengatur penugasan Anda agar bisa mengisi laporan PPA.</x-kosong></div>
    @else
        <ul class="grid gap-3 sm:grid-cols-2">
            @foreach($subs as $s)
                @php
                    $daftarPetak = $petakSaya->get($s->id, collect());
                    $lap = $pekanIni->get($s->id, collect());
                    $hariIni = $lap->first(fn ($l) => $l->tanggal->isToday());
                @endphp
                <li class="panel flex items-center justify-between gap-3 p-4">
                    <div class="min-w-0">
                        <p class="truncate font-semibold">{{ $s->nama }}</p>
                        <p class="text-xs text-basalt-soft">D.I. {{ $s->daerahIrigasi->nama }}@if($s->luas_ha) · {{ angka_r($s->luas_ha) }} Ha @endif</p>
                        @if($daftarPetak->isNotEmpty())<p class="mt-0.5 text-xs text-basalt-soft">Petak tugas: {{ $daftarPetak->pluck('kode')->implode(', ') }}</p>
                        @elseif($petakAdaDiSub->has($s->id))<p class="mt-1 text-xs text-status-siaga">Belum ada petak tersier yang ditugaskan kepada Anda — minta Manager mengatur penugasan pintu air.</p>@endif
                        @if($lap->isNotEmpty())
                            <p class="mt-1 flex flex-wrap items-center gap-1 text-xs font-medium text-status-aman"><x-ikon n="cek" class="h-3.5 w-3.5"/> {{ $lap->count() }} laporan sejak Senin {{ $senin->translatedFormat('d M') }} · <x-verifikasi :s="$lap->last()->status_verifikasi"/></p>
                        @else
                            <p class="mt-1 text-xs font-semibold text-[#8A3510]">Belum ada laporan sejak Senin {{ $senin->translatedFormat('d M') }}</p>
                        @endif
                    </div>
                    @if($hariIni)
                        <a href="{{ route('laporan.show', $hariIni) }}" class="btn-ghost shrink-0">Lihat</a>
                    @else
                        <a href="{{ route('laporan.create', ['sub' => $s->id]) }}" class="{{ $lap->isEmpty() ? 'btn-primary' : 'btn-ghost' }} shrink-0"><x-ikon n="tambah" class="h-4 w-4"/> {{ $lap->isEmpty() ? 'Lapor' : 'Insidental' }}</a>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</section>

@if($pintu->isNotEmpty())
<section class="mt-8" aria-labelledby="judul-pintu">
    <h2 id="judul-pintu" class="mb-1 text-lg font-semibold">Pembacaan pintu air saya</h2>
    <p class="mb-2 text-sm text-basalt-soft">Pembacaan pintu sudah termasuk di laporan PPA. Gunakan "Baca" hanya untuk pembacaan tambahan tanpa laporan tanaman.</p>
    <ul class="grid gap-3 sm:grid-cols-2">
        @foreach($pintu as $r)
            <li class="panel flex items-center justify-between gap-3 p-4">
                <div class="min-w-0">
                    <p class="truncate font-semibold">D.I. {{ $r['di']->nama }}</p>
                    <p class="text-xs {{ $r['belum'] ? 'font-semibold text-[#8A3510]' : 'text-status-aman' }}">{{ $r['dibaca'] }}/{{ $r['total'] }} pintu dibaca sejak Senin</p>
                </div>
                <a href="{{ route('pembacaan-pintu.create', ['di' => $r['di']->id]) }}" class="{{ $r['belum'] ? 'btn-primary' : 'btn-ghost' }} shrink-0"><x-ikon n="pintu" class="h-4 w-4"/> Baca</a>
            </li>
        @endforeach
    </ul>
</section>
@endif

<section class="mt-8" aria-labelledby="judul-riwayat">
    <div class="mb-2 flex items-baseline justify-between">
        <h2 id="judul-riwayat" class="text-lg font-semibold">Laporan saya</h2>
        <a href="{{ route('laporan.index', ['milik_saya' => 1]) }}" class="text-sm font-medium text-saluran-600">Semua</a>
    </div>
    <div class="panel divide-y divide-savana-line">
        @forelse($riwayat as $l)
            <a href="{{ route('laporan.show', $l) }}" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-savana">
                <span>
                    <span class="block text-sm font-semibold">{{ $l->subDaerahIrigasi->nama }}@if($l->petak_count) · {{ $l->petak_count }} petak @endif</span>
                    <span class="block text-xs text-basalt-soft">{{ $l->tanggal->translatedFormat('d M Y') }} · {{ $l->jenis_label }} · kering {{ angka($l->luas_kering_ha, 1) }}/{{ angka($l->luas_tanam_ha, 1) }} Ha</span>
                </span>
                <span class="flex flex-col items-end gap-1"><x-status :s="$l->status()"/><x-verifikasi :s="$l->status_verifikasi"/></span>
            </a>
        @empty
            <x-kosong judul="Belum ada laporan">Laporan yang Anda kirim akan muncul di sini.</x-kosong>
        @endforelse
    </div>
</section>

<div class="mt-6 flex flex-wrap gap-2">
    @can('lapor-kejadian')<a href="{{ route('kejadian.create') }}" class="btn-ghost"><x-ikon n="kejadian" class="h-4 w-4"/> Laporkan kejadian kekeringan</a>@endcan
    <a href="{{ route('peta') }}" class="btn-ghost"><x-ikon n="peta" class="h-4 w-4"/> Peta wilayah saya</a>
</div>
</x-layouts.app>
