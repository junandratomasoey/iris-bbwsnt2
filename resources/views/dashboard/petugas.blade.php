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
                @php $l = $hariIni->get($s->id); @endphp
                <li class="panel flex items-center justify-between gap-3 p-4">
                    <div class="min-w-0">
                        <p class="truncate font-semibold">{{ $s->nama }}</p>
                        <p class="text-xs text-basalt-soft">D.I. {{ $s->daerahIrigasi->nama }}@if($s->luas_ha) · {{ angka($s->luas_ha) }} Ha @endif</p>
                        @if($l)<p class="mt-1 flex items-center gap-1 text-xs font-medium text-status-aman"><x-ikon n="cek" class="h-3.5 w-3.5"/> Terkirim · <x-verifikasi :s="$l->status_verifikasi"/></p>@endif
                    </div>
                    @if($l)
                        <a href="{{ route('laporan.show', $l) }}" class="btn-ghost shrink-0">Lihat</a>
                    @else
                        <a href="{{ route('laporan.create', ['sub' => $s->id]) }}" class="btn-primary shrink-0"><x-ikon n="tambah" class="h-4 w-4"/> Lapor</a>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</section>

<section class="mt-8" aria-labelledby="judul-riwayat">
    <div class="mb-2 flex items-baseline justify-between">
        <h2 id="judul-riwayat" class="text-lg font-semibold">Laporan saya</h2>
        <a href="{{ route('laporan.index', ['milik_saya' => 1]) }}" class="text-sm font-medium text-saluran-600">Semua</a>
    </div>
    <div class="panel divide-y divide-savana-line">
        @forelse($riwayat as $l)
            <a href="{{ route('laporan.show', $l) }}" class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-savana">
                <span>
                    <span class="block text-sm font-semibold">{{ $l->subDaerahIrigasi->nama }}</span>
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
    <a href="{{ route('kejadian.create') }}" class="btn-ghost"><x-ikon n="kejadian" class="h-4 w-4"/> Laporkan kejadian kekeringan</a>
    <a href="{{ route('peta') }}" class="btn-ghost"><x-ikon n="peta" class="h-4 w-4"/> Peta wilayah saya</a>
</div>
</x-layouts.app>
