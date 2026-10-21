@php
    $r = $ringkas;
    $pekanLalu = $senin->copy()->subWeek()->toDateString();
    $pekanDepan = $senin->copy()->addWeek();
@endphp
<x-layouts.app title="Pengingat laporan">
<x-judul judul="Pengingat laporan rutin" :sub="'Pekan Senin '.$senin->translatedFormat('d M').' – '.$senin->copy()->addDays(6)->translatedFormat('d M Y').'. Laporan PPA dan laporan Manager Air diisi setiap Senin pukul 07.00–09.00 WITA. Tekan WhatsApp untuk mengingatkan petugas yang belum melapor.'">
    <a href="{{ route('pengingat', ['pekan' => $pekanLalu, 'belum' => $hanyaBelum ? 1 : 0]) }}" class="btn-ghost">‹ Pekan lalu</a>
    @if($pekanDepan->lte(today()))<a href="{{ route('pengingat', ['pekan' => $pekanDepan->toDateString(), 'belum' => $hanyaBelum ? 1 : 0]) }}" class="btn-ghost">Pekan berikut ›</a>@endif
</x-judul>

<div class="mb-5 grid gap-3 sm:grid-cols-3">
    <div class="panel p-4">
        <p class="text-sm text-basalt-soft">PPA belum melapor</p>
        <p class="font-gauge text-3xl font-semibold {{ $r['ppa_belum'] ? 'text-[#8A3510]' : 'text-status-aman' }}">{{ $r['ppa_belum'] }}<span class="text-lg text-basalt-mute"> / {{ $r['ppa_total'] }}</span></p>
    </div>
    <div class="panel p-4">
        <p class="text-sm text-basalt-soft">D.I. belum ada laporan Manager Air</p>
        <p class="font-gauge text-3xl font-semibold {{ $r['air_belum'] ? 'text-[#8A3510]' : 'text-status-aman' }}">{{ $r['air_belum'] }}<span class="text-lg text-basalt-mute"> / {{ $r['di_total'] }}</span></p>
    </div>
    <div class="panel p-4">
        <p class="text-sm text-basalt-soft">Sub-D.I. tanpa PPA</p>
        <p class="font-gauge text-3xl font-semibold">{{ $r['sub_tanpa_ppa'] }}</p>
        @if($r['sub_tanpa_ppa'])<p class="text-xs text-basalt-mute">Tugaskan PPA di menu Kontak petugas.</p>@endif
    </div>
</div>

<div class="mb-3 flex items-center gap-2 text-sm">
    <span class="text-basalt-soft">Tampilkan:</span>
    <a href="{{ route('pengingat', ['pekan' => $senin->toDateString(), 'belum' => 1]) }}" class="{{ $hanyaBelum ? 'chip-siaga' : 'chip-kosong' }}">Yang belum melapor</a>
    <a href="{{ route('pengingat', ['pekan' => $senin->toDateString(), 'belum' => 0]) }}" class="{{ ! $hanyaBelum ? 'chip-siaga' : 'chip-kosong' }}">Semua</a>
</div>

@forelse($di as $d)
    @continue($hanyaBelum && ! $d['belum'] && collect($d['ppa'])->whereNull('user')->isEmpty())
    <section class="panel mb-4" x-data="{ disalin: false }">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-savana-line px-4 py-3">
            <h2 class="text-lg font-semibold">D.I. {{ $d['nama'] }}
                @if($d['belum'])<span class="chip-siaga ml-1 align-middle">{{ $d['belum'] }} belum</span>@else<span class="chip-aman ml-1 align-middle">Lengkap</span>@endif</h2>
            <button type="button" class="btn-ghost py-1 text-xs"
                    @click="navigator.clipboard?.writeText($refs.grup.value).then(() => { disalin = true; setTimeout(() => disalin = false, 2000) })">
                <x-ikon n="wa" class="h-3.5 w-3.5"/> <span x-text="disalin ? 'Pesan disalin' : 'Salin pesan untuk grup WA'"></span></button>
            <textarea x-ref="grup" class="sr-only" tabindex="-1" aria-hidden="true">{{ $d['pesan_grup'] }}</textarea>
        </div>
        <ul class="divide-y divide-savana-line">
            {{-- Manager Air --}}
            @if(! $hanyaBelum || ! $d['air_sudah'])
                <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold">Laporan Manager Air <span class="font-normal text-basalt-soft">· {{ collect($d['manager'])->pluck('user.name')->implode(', ') ?: 'belum ada akun Manager' }}</span></p>
                        @if($d['air_sudah'])
                            <p class="text-xs font-medium text-status-aman">Sudah masuk {{ $d['air']->last()->tanggal->translatedFormat('D d M') }}</p>
                        @else
                            <p class="text-xs font-semibold text-[#8A3510]">Belum masuk pekan ini</p>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($d['manager'] as $m)
                            @if($m['wa'])<a href="{{ $m['wa'] }}" target="_blank" rel="noopener" class="btn-wa"><x-ikon n="wa" class="h-3.5 w-3.5"/> {{ \Illuminate\Support\Str::of($m['user']->name)->words(2, '') }}</a>
                            @elseif(! $d['air_sudah'])<span class="text-xs text-basalt-mute">{{ $m['user']->name }}: no. HP belum ada</span>@endif
                        @endforeach
                        @if($d['air_sudah'])<a href="{{ route('laporan-air.show', $d['air']->last()) }}" class="text-xs font-semibold text-saluran-600">Lihat</a>@endif
                    </div>
                </li>
            @endif
            {{-- PPA --}}
            @foreach($d['ppa'] as $p)
                @continue($hanyaBelum && $p['sudah'])
                <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5">
                    <div class="min-w-0">
                        @if($p['user'])
                            <p class="text-sm font-semibold">{{ $p['user']->name }} <span class="font-normal text-basalt-soft">· PPA Sub-D.I. {{ $p['sub']->nama }}</span></p>
                            @if($p['petak'])<p class="text-xs text-basalt-soft">Petak tugas: {{ implode(', ', $p['petak']) }}</p>@endif
                            @if($p['sudah'])
                                <p class="text-xs font-medium text-status-aman">Sudah masuk {{ $p['laporan']->last()->tanggal->translatedFormat('D d M') }}{{ $p['laporan']->count() > 1 ? ' ('.$p['laporan']->count().' laporan)' : '' }}</p>
                            @else
                                <p class="text-xs font-semibold text-[#8A3510]">Belum melapor pekan ini</p>
                            @endif
                        @else
                            <p class="text-sm font-semibold">Sub-D.I. {{ $p['sub']->nama }}</p>
                            <p class="text-xs text-basalt-mute">Belum ada PPA yang ditugaskan.</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        @if($p['wa'])<a href="{{ $p['wa'] }}" target="_blank" rel="noopener" class="btn-wa"><x-ikon n="wa" class="h-3.5 w-3.5"/> Ingatkan</a>
                        @elseif($p['user'] && ! $p['sudah'])<span class="text-xs text-basalt-mute">No. HP belum ada</span>@endif
                        @if($p['sudah'])<a href="{{ route('laporan.show', $p['laporan']->last()) }}" class="text-xs font-semibold text-saluran-600">Lihat</a>@endif
                    </div>
                </li>
            @endforeach
        </ul>
    </section>
@empty
    <div class="panel"><x-kosong judul="Tidak ada Sub-D.I. di wilayah Anda">Daftar muncul setelah Sub-D.I. dan PPA ditugaskan.</x-kosong></div>
@endforelse
@if($hanyaBelum && collect($di)->every(fn ($d) => ! $d['belum'] && collect($d['ppa'])->whereNull('user')->isEmpty()) && count($di))
    <div class="panel"><x-kosong judul="Semua sudah melapor pekan ini">Tidak ada yang perlu diingatkan.</x-kosong></div>
@endif
</x-layouts.app>
