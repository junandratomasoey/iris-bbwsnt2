@php
    use App\Support\StatusKondisi as SK;
    use App\Services\RekapService;
    $urut = ['kritis', 'siaga', 'waspada', 'aman', 'kosong'];
    $di = collect($r['di']);
    $totalDi = $di->count();
    $statusDi = $di->map(fn ($d) => RekapService::statusMode($d, $mode));
    $perStatus = collect($urut)->mapWithKeys(fn ($s) => [$s => $statusDi->filter(fn ($x) => $x === $s)->count()]);
    $luasPerStatus = collect($urut)->mapWithKeys(fn ($s) => [$s => $di->filter(fn ($d) => RekapService::statusMode($d, $mode) === $s)->sum('luas_ha')]);
    $hariDi = $r['total']['hari_di'];
    $totalHariDi = max(1, array_sum($hariDi));
    $peringkat = $di->sortByDesc(fn ($d) => [$d['hari']['kritis'], $d['hari_berisiko'], $d['puncak_kering_ha']])->values();
    $q = $periode->query();
@endphp
<x-layouts.app title="Rekap kondisi">
<x-judul :judul="'Rekap kondisi irigasi · '.$periode->label" :sub="$periode->keterangan().' · '.$totalDi.' Daerah Irigasi'">
    <a href="{{ route('peta', $q) }}" class="btn-ghost"><x-ikon n="peta" class="h-4 w-4"/> Buka peta</a>
    <a href="{{ route('rekap.unduh', $q) }}" class="btn-ghost"><x-ikon n="unduh" class="h-4 w-4"/> Unduh rekap</a>
    @if($menunggu)<a href="{{ route('laporan.index', ['verifikasi' => 'menunggu']) }}" class="btn-primary">{{ $menunggu }} laporan menunggu verifikasi</a>@endif
</x-judul>

<x-pilih-periode :periode="$periode" rute="dashboard" :tambahan="$mode === 'tersering' ? ['warna' => 'tersering'] : []" class="mb-5"/>

{{-- status D.I. dalam periode --}}
<section class="panel p-5" aria-labelledby="judul-status">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 id="judul-status" class="text-lg font-semibold">Status Daerah Irigasi dalam periode</h2>
            <p class="text-sm text-basalt-soft">{{ $mode === 'tersering' ? 'Status yang paling banyak harinya (tanpa hari tanpa laporan).' : 'Status terberat yang pernah dicapai: satu hari kritis sudah dihitung kritis.' }}</p>
        </div>
        <div class="flex rounded-md border border-savana-line p-0.5 text-sm" role="group" aria-label="Cara menentukan status">
            <a href="{{ route('dashboard', $q) }}" @class(['rounded px-3 py-1 font-medium', 'bg-basalt text-white' => $mode === 'terberat', 'text-basalt-soft' => $mode !== 'terberat'])>Terberat</a>
            <a href="{{ route('dashboard', $q + ['warna' => 'tersering']) }}" @class(['rounded px-3 py-1 font-medium', 'bg-basalt text-white' => $mode === 'tersering', 'text-basalt-soft' => $mode !== 'tersering'])>Tersering</a>
        </div>
    </div>
    <div class="mt-4 flex h-10 gap-[2px] overflow-hidden rounded" role="img" aria-label="Jumlah D.I. per status: {{ collect($urut)->map(fn ($s) => SK::label($s).' '.$perStatus[$s])->implode(', ') }}">
        @foreach($urut as $s)
            @if($perStatus[$s])
                <div class="flex items-center justify-center font-gauge text-lg font-semibold text-white" style="width: {{ $perStatus[$s] / max(1, $totalDi) * 100 }}%; background: {{ SK::WARNA[$s] }}" title="{{ SK::label($s) }}: {{ $perStatus[$s] }} D.I.">{{ $perStatus[$s] }}</div>
            @endif
        @endforeach
    </div>
    <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-5">
        @foreach($urut as $s)
            <div class="border-l-4 pl-3" style="border-color: {{ SK::WARNA[$s] }}">
                <dt class="text-xs text-basalt-soft">{{ SK::label($s) }}</dt>
                <dd class="font-gauge text-2xl font-semibold">{{ $perStatus[$s] }} <span class="text-sm font-medium text-basalt-soft">D.I.</span></dd>
                <dd class="text-xs text-basalt-mute">{{ angka($luasPerStatus[$s]) }} Ha · {{ angka($hariDi[$s]) }} hari-D.I.</dd>
            </div>
        @endforeach
    </dl>
    <div class="mt-5">
        <p class="text-xs font-medium text-basalt-soft">Proporsi hari per status (semua D.I. × {{ $r['jumlah_hari'] }} hari)</p>
        <div class="mt-1 flex h-3 gap-[2px] overflow-hidden rounded-sm" role="img" aria-label="Proporsi hari per status">
            @foreach($urut as $s)
                @if($hariDi[$s])<div style="width: {{ $hariDi[$s] / $totalHariDi * 100 }}%; background: {{ SK::WARNA[$s] }}" title="{{ SK::label($s) }}: {{ angka($hariDi[$s]) }} hari-D.I. ({{ round($hariDi[$s] / $totalHariDi * 100) }}%)"></div>@endif
            @endforeach
        </div>
        <p class="mt-1 text-xs text-basalt-mute">{{ round($hariDi['kosong'] / $totalHariDi * 100) }}% hari tanpa laporan. Makin kecil angka ini, makin lengkap gambaran kondisinya.</p>
    </div>
</section>

<div class="mt-5 grid gap-5 lg:grid-cols-3">
    <section class="panel p-5">
        <h2 class="text-lg font-semibold">Kepatuhan lapor</h2>
        <p class="mt-2 font-gauge text-4xl font-semibold">{{ $r['total']['kepatuhan'] }}<span class="text-xl text-basalt-mute">%</span></p>
        <p class="text-sm text-basalt-soft">{{ angka($r['total']['laporan']) }} laporan dari {{ angka($r['total']['jumlah_sub'] * $r['jumlah_hari']) }} hari-Sub-D.I.</p>
        <ul class="mt-4 space-y-2.5">
            @foreach($r['kepatuhan'] as $k)
                <li>
                    <div class="flex items-baseline justify-between gap-2 text-xs">
                        <span class="font-medium">{{ $k['kode'] ? 'Satgas Wilayah '.$k['kode'] : 'Belum masuk Satgas' }}</span>
                        <span class="shrink-0 tabular-nums text-basalt-soft">{{ $k['persen'] }}% · {{ angka($k['laporan']) }} laporan</span>
                    </div>
                    @if($k['cakupan'])<p class="text-[11px] leading-snug text-basalt-soft">{{ $k['cakupan'] }}</p>@endif
                    <p class="text-[11px] leading-snug text-basalt-mute">{{ $k['kabupaten'] }}</p>
                    <div class="mt-1 h-1.5 rounded-full bg-savana-deep"><div class="h-1.5 rounded-full {{ $k['kode'] ? 'bg-saluran-500' : 'bg-basalt-mute' }}" style="width: {{ min(100, $k['persen']) }}%"></div></div>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="panel p-5">
        <h2 class="text-lg font-semibold">Luas lahan (puncak)</h2>
        <dl class="mt-3 space-y-3">
            <div class="flex items-baseline justify-between"><dt class="text-sm text-basalt-soft">Luas tanam</dt><dd class="font-gauge text-2xl font-semibold">{{ angka($r['total']['puncak_tanam_ha'], 1) }} Ha</dd></div>
            <div class="-mt-2 flex flex-wrap justify-end gap-x-3 text-xs text-basalt-soft">
                <span>Padi <b class="text-basalt">{{ angka($r['total']['puncak_padi_ha'], 1) }}</b></span>
                <span>Palawija <b class="text-basalt">{{ angka($r['total']['puncak_palawija_ha'], 1) }}</b></span>
                <span>Hortikultura <b class="text-basalt">{{ angka($r['total']['puncak_horti_ha'], 1) }}</b></span>
            </div>
            <div class="flex items-baseline justify-between"><dt class="text-sm text-basalt-soft">Tanaman kekeringan</dt><dd class="font-gauge text-2xl font-semibold text-status-siaga">{{ angka($r['total']['puncak_kering_ha'], 1) }} Ha</dd></div>
            <div class="flex items-baseline justify-between"><dt class="text-sm text-basalt-soft">Puso</dt><dd class="font-gauge text-2xl font-semibold text-status-kritis">{{ angka($r['total']['puncak_puso_ha'], 1) }} Ha</dd></div>
            <div class="flex items-baseline justify-between"><dt class="text-sm text-basalt-soft">Panen</dt><dd class="font-gauge text-2xl font-semibold text-status-aman">{{ angka($r['total']['puncak_panen_ha'], 1) }} Ha</dd></div>
        </dl>
        <p class="mt-3 text-xs text-basalt-mute">Nilai tertinggi yang dilaporkan tiap Sub-D.I. selama periode, lalu dijumlah. Laporan harian tidak dijumlah antar hari agar luas tidak terhitung berulang.</p>
    </section>

    <section class="panel p-5">
        <div class="flex items-baseline justify-between"><h2 class="text-lg font-semibold">Kejadian baru</h2><a href="{{ route('kejadian.index') }}" class="text-sm font-medium text-saluran-600">Semua</a></div>
        <p class="text-xs text-basalt-mute">Dilaporkan {{ $periode->dari->translatedFormat('d M') }} – {{ $periode->sampai->translatedFormat('d M Y') }}</p>
        <ul class="mt-3 space-y-3">
            @foreach(\App\Models\Kejadian::STATUS as $st => $w)
                @php $k = $r['kejadian'][$st] ?? ['jumlah' => 0, 'luas' => 0]; @endphp
                <li class="flex items-center justify-between gap-2">
                    <span class="flex items-center gap-2 text-sm"><span class="h-2.5 w-2.5 rounded-full" style="background: {{ $w }}"></span>{{ $st }}</span>
                    <span class="text-right text-sm"><span class="font-gauge text-xl font-semibold">{{ $k['jumlah'] }}</span> <span class="text-xs text-basalt-mute">lokasi · {{ angka($k['luas'], 1) }} Ha</span></span>
                </li>
            @endforeach
        </ul>
        @php $kl = collect($r['kejadian'])->sum('luas'); $kt = collect($r['kejadian'])->sum('tertangani'); @endphp
        <p class="mt-3 border-t border-savana-line pt-3 text-sm text-basalt-soft">{{ angka($kt, 1) }} dari {{ angka($kl, 1) }} Ha tertangani ({{ $kl ? round($kt / $kl * 100) : 0 }}%).</p>
    </section>
</div>

{{-- tren: jumlah D.I. per status tiap hari/minggu (kolom bertumpuk 100%) --}}
<section class="panel mt-5 p-5" aria-labelledby="judul-tren">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="judul-tren" class="text-lg font-semibold">Tren status D.I. {{ $r['mingguan'] ? 'per minggu' : 'per hari' }}</h2>
        <ul class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-basalt-soft" aria-label="Legenda">
            @foreach($urut as $s)<li class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm" style="background: {{ SK::WARNA[$s] }}"></span>{{ SK::label($s) }}</li>@endforeach
        </ul>
    </div>
    <p class="text-xs text-basalt-mute">{{ $r['mingguan'] ? 'Tiap kolom = status terberat D.I. dalam minggu itu.' : 'Tiap kolom = status D.I. pada hari itu.' }} Arahkan kursor/ketuk kolom untuk angka.</p>
    <div class="mt-3 overflow-x-auto">
        <div class="flex h-40 min-w-full items-end gap-[2px]" style="min-width: {{ count($r['tren']) * 14 }}px">
            @foreach($r['tren'] as $t)
                <div class="group relative flex h-full max-w-6 flex-1 flex-col-reverse gap-[2px]" tabindex="0"
                     title="{{ $t['judul'] }} — {{ collect($urut)->filter(fn ($s) => $t[$s])->map(fn ($s) => SK::label($s).' '.$t[$s])->implode(', ') }} · {{ $t['laporan'] }} laporan"
                     aria-label="{{ $t['judul'] }}: {{ collect($urut)->filter(fn ($s) => $t[$s])->map(fn ($s) => SK::label($s).' '.$t[$s].' D.I.')->implode(', ') }}">
                    @php $ada = collect($urut)->filter(fn ($s) => $t[$s])->values(); @endphp
                    @foreach($ada as $s){{-- kritis di dasar, tanpa laporan di atas --}}
                        <div class="{{ $loop->last ? 'rounded-t' : '' }}" style="flex: {{ $t[$s] }} 1 0; background: {{ $s === 'kosong' ? '#DDE3DE' : SK::WARNA[$s] }}"></div>
                    @endforeach
                </div>
            @endforeach
        </div>
        <div class="mt-1 flex min-w-full gap-[2px] text-[10px] text-basalt-mute" style="min-width: {{ count($r['tren']) * 14 }}px" aria-hidden="true">
            @foreach($r['tren'] as $t)<span class="max-w-6 flex-1 overflow-visible whitespace-nowrap text-center">{{ $t['label'] }}</span>@endforeach
        </div>
    </div>
</section>

{{-- kalender kondisi: D.I. × hari/minggu --}}
<section class="panel mt-5 overflow-hidden" aria-labelledby="judul-kalender">
    <div class="flex flex-wrap items-baseline justify-between gap-2 p-5 pb-3">
        <div>
            <h2 id="judul-kalender" class="text-lg font-semibold">Kalender kondisi</h2>
            <p class="text-xs text-basalt-mute">{{ $r['mingguan'] ? 'Satu kotak = satu minggu (status terberat).' : 'Satu kotak = satu hari.' }} Titik putih = kritis. Diurutkan dari D.I. yang paling lama kritis/siaga.</p>
        </div>
        <ul class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-basalt-soft" aria-label="Legenda">
            @foreach($urut as $s)<li class="flex items-center gap-1.5"><span class="relative h-3 w-3 rounded-sm" style="background: {{ $s === 'kosong' ? '#E7ECE8' : SK::WARNA[$s] }}">@if($s === 'kritis')<span class="absolute inset-0 m-auto h-1 w-1 rounded-full bg-white"></span>@endif</span>{{ $s === 'kosong' ? 'Tanpa laporan' : SK::label($s) }}</li>@endforeach
        </ul>
    </div>
    <div class="overflow-x-auto pb-4">
        <table class="border-separate border-spacing-[2px] text-xs">
            <thead>
                <tr>
                    <th class="sticky left-0 z-10 min-w-[9rem] bg-white px-3 text-left font-medium text-basalt-soft">D.I.</th>
                    @foreach($r['kalender']['kolom'] as $k)<th class="w-3.5 min-w-[14px] p-0 text-center text-[10px] font-normal text-basalt-mute" title="{{ $k['judul'] }}">{{ $k['label'] }}</th>@endforeach
                    <th class="whitespace-nowrap px-2 text-right font-medium text-basalt-soft">Hari kritis/siaga</th>
                </tr>
            </thead>
            <tbody>
                @foreach($peringkat as $d)
                    <tr>
                        <th scope="row" class="sticky left-0 z-10 whitespace-nowrap bg-white px-3 text-left font-medium"><a href="{{ route('master.daerah-irigasi.show', $d['id']) }}" class="hover:underline">{{ $d['nama'] }}</a></th>
                        @foreach($r['kalender']['baris'][$d['id']] as $i => $s)
                            <td class="relative h-3.5 w-3.5 min-w-[14px] rounded-sm p-0" style="background: {{ $s === 'kosong' ? '#E7ECE8' : SK::WARNA[$s] }}" title="{{ $d['nama'] }} · {{ $r['kalender']['kolom'][$i]['judul'] }}: {{ $s === 'kosong' ? 'tanpa laporan' : SK::label($s) }}">
                                @if($s === 'kritis')<span class="absolute inset-0 m-auto block h-1 w-1 rounded-full bg-white"></span>@endif
                            </td>
                        @endforeach
                        <td class="px-2 text-right tabular-nums">{{ $d['hari']['kritis'] }} / {{ $d['hari']['siaga'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>

{{-- tabel rekap per D.I. (juga tampilan tabel untuk grafik di atas) --}}
<section class="panel mt-5 overflow-hidden">
    <div class="flex flex-wrap items-baseline justify-between gap-2 p-5 pb-3">
        <h2 class="text-lg font-semibold">Rekap per D.I.</h2>
        <a href="{{ route('rekap.unduh', $q) }}" class="text-sm font-medium text-saluran-600">Unduh (CSV, bisa dibuka Excel)</a>
    </div>
    <div class="overflow-x-auto">
        <table class="table-base">
            <thead>
                <tr>
                    <th>D.I.</th><th>Status {{ $mode }}</th>
                    <th class="text-right" title="Jumlah hari berstatus kritis / siaga / waspada / aman / tanpa laporan">Hari K / S / W / A / –</th>
                    <th class="text-right">Kepatuhan</th>
                    <th class="text-right">Puncak kering (Ha)</th><th class="text-right">Puncak puso (Ha)</th>
                    <th class="text-right">Debit rata-rata / min (L/dt)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($peringkat as $d)
                    <tr>
                        <td class="font-medium">{{ $d['nama'] }}<div class="text-xs text-basalt-mute">{{ $d['kabupaten'] }}{{ $d['satgas'] ? ' · Satgas '.$d['satgas'] : '' }}</div></td>
                        <td><x-status :s="RekapService::statusMode($d, $mode)"/></td>
                        <td class="whitespace-nowrap text-right tabular-nums">{{ $d['hari']['kritis'] }} / {{ $d['hari']['siaga'] }} / {{ $d['hari']['waspada'] }} / {{ $d['hari']['aman'] }} / <span class="text-basalt-mute">{{ $d['hari']['kosong'] }}</span></td>
                        <td class="text-right tabular-nums">{{ $d['kepatuhan'] }}%</td>
                        <td class="text-right tabular-nums">{{ angka($d['puncak_kering_ha'], 1) }}</td>
                        <td class="text-right tabular-nums">{{ angka($d['puncak_puso_ha'], 1) }}</td>
                        <td class="whitespace-nowrap text-right tabular-nums">{{ $d['debit_rata_lps'] !== null ? angka($d['debit_rata_lps'], 1).' / '.angka($d['debit_min_lps'], 1) : '–' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
</x-layouts.app>
