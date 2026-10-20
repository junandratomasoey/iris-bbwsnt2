<x-layouts.app title="Beranda petugas">
<x-judul :judul="'Halo, '.\Illuminate\Support\Str::of(auth()->user()->name)->words(2, '')" :sub="today()->translatedFormat('l, d F Y')"/>

<x-pasang-aplikasi gaya="kartu" class="mb-5"/>

<section aria-labelledby="judul-tugas">
    <h2 id="judul-tugas" class="mb-2 text-lg font-semibold">Laporan hari ini</h2>
    @if($subs->isEmpty())
        <div class="panel"><x-kosong judul="Anda belum ditugaskan ke Sub-D.I.">Minta Admin OP mengatur penugasan Anda agar bisa mengisi laporan harian.</x-kosong></div>
    @else
        <ul class="grid gap-3 sm:grid-cols-2">
            @foreach($subs as $s)
                @php
                    $daftarPetak = $petakSaya->get($s->id, collect());
                    $unit = $daftarPetak->isNotEmpty()
                        ? $daftarPetak->map(fn ($p) => ['judul' => 'Petak '.$p->kode, 'sub' => $p->nama ?: $s->nama, 'luas' => $p->luas_layanan_ha, 'kunci' => $s->id.':'.$p->id, 'url' => route('laporan.create', ['sub' => $s->id, 'petak' => $p->id])])
                        : collect([['judul' => $s->nama, 'sub' => null, 'luas' => $s->luas_ha, 'kunci' => $s->id.':0', 'url' => route('laporan.create', ['sub' => $s->id])]]);
                @endphp
                @foreach($unit as $u)
                    @php $l = $hariIni->get($u['kunci']); @endphp
                    <li class="panel flex items-center justify-between gap-3 p-4">
                        <div class="min-w-0">
                            <p class="truncate font-semibold">{{ $u['judul'] }}</p>
                            <p class="text-xs text-basalt-soft">{{ $u['sub'] ? $s->nama.' · ' : '' }}D.I. {{ $s->daerahIrigasi->nama }}@if($u['luas']) · {{ angka_r($u['luas']) }} Ha @endif</p>
                            @if(! $l && $daftarPetak->isEmpty() && $petakAdaDiSub->has($s->id))<p class="mt-1 text-xs text-status-siaga">Sub-D.I. ini dilaporkan per petak — minta Manager menugaskan petak Anda.</p>@endif
                            @if($l)<p class="mt-1 flex items-center gap-1 text-xs font-medium text-status-aman"><x-ikon n="cek" class="h-3.5 w-3.5"/> Terkirim · <x-verifikasi :s="$l->status_verifikasi"/></p>@endif
                        </div>
                        @if($l)
                            <a href="{{ route('laporan.show', $l) }}" class="btn-ghost shrink-0">Lihat</a>
                        @else
                            <a href="{{ $u['url'] }}" class="btn-primary shrink-0"><x-ikon n="tambah" class="h-4 w-4"/> Lapor</a>
                        @endif
                    </li>
                @endforeach
            @endforeach
        </ul>
    @endif
</section>

@if($pintu->isNotEmpty())
<section class="mt-8" aria-labelledby="judul-pintu">
    <h2 id="judul-pintu" class="mb-1 text-lg font-semibold">Pembacaan pintu air saya</h2>
    <p class="mb-2 text-sm text-basalt-soft">Jadwal rutin setiap Senin. Di luar Senin, baca juga bila ada panen, pekerjaan di sawah, atau kejadian khusus.</p>
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
                    <span class="block text-sm font-semibold">{{ $l->subDaerahIrigasi->nama }}@if($l->petak) · Petak {{ $l->petak->kode }}@endif</span>
                    <span class="block text-xs text-basalt-soft">{{ $l->tanggal->translatedFormat('d M Y') }} · debit {{ angka($l->debit_intake_lps, 1) }} L/dt</span>
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
