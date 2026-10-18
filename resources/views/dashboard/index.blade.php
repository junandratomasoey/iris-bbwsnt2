@php
    use App\Support\StatusKondisi as SK;
    $totalDi = $ringkasan->count();
    $urut = ['kritis', 'siaga', 'waspada', 'aman', 'kosong'];
    $persenLapor = $totalSub ? round($laporHariIni / $totalSub * 100) : 0;
    $berisiko = $ringkasan->whereIn('status', ['siaga', 'kritis'])->sum('luas_ha');
@endphp
<x-layouts.app title="Dashboard">
<x-judul judul="Kondisi irigasi hari ini" :sub="$hari->translatedFormat('l, d F Y').' · '.$totalDi.' Daerah Irigasi'">
    <a href="{{ route('peta') }}" class="btn-ghost"><x-ikon n="peta" class="h-4 w-4"/> Buka peta</a>
    @if($menunggu)
        <a href="{{ route('laporan.index', ['verifikasi' => 'menunggu']) }}" class="btn-primary">{{ $menunggu }} laporan menunggu verifikasi</a>
    @endif
</x-judul>

<x-pilih-periode :periode="$periode" rute="dashboard" class="mb-5"/>

{{-- papan duga --}}
<section class="panel p-5" aria-labelledby="judul-status">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 id="judul-status" class="text-lg font-semibold">Status Daerah Irigasi</h2>
        <p class="text-sm text-basalt-soft"><span class="font-gauge text-xl font-semibold text-status-siaga">{{ angka($berisiko) }} Ha</span> areal fungsional berstatus Siaga atau Kritis</p>
    </div>
    <div class="relative mt-4">
        <div class="gauge-strip flex h-10 overflow-hidden rounded" role="img" aria-label="Proporsi D.I. per status">
            @foreach($urut as $s)
                @if($perStatus[$s])
                    <div class="flex items-center justify-center font-gauge text-lg font-semibold text-white" style="width: {{ $perStatus[$s] / max(1, $totalDi) * 100 }}%; background: {{ SK::WARNA[$s] }}">{{ $perStatus[$s] }}</div>
                @endif
            @endforeach
        </div>
        <div class="mt-1 flex justify-between text-[10px] text-basalt-mute" aria-hidden="true">@for($i = 0; $i <= 10; $i++)<span>{{ $i * 10 }}</span>@endfor</div>
    </div>
    <dl class="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-5">
        @foreach($urut as $s)
            <div class="border-l-4 pl-3" style="border-color: {{ SK::WARNA[$s] }}">
                <dt class="text-xs text-basalt-soft">{{ SK::label($s) }}</dt>
                <dd class="font-gauge text-2xl font-semibold">{{ $perStatus[$s] }} <span class="text-sm font-medium text-basalt-soft">D.I.</span></dd>
                <dd class="text-xs text-basalt-mute">{{ angka($luasPerStatus[$s]) }} Ha</dd>
            </div>
        @endforeach
    </dl>
</section>

<div class="mt-5 grid gap-5 lg:grid-cols-3">
    <section class="panel p-5">
        <h2 class="text-lg font-semibold">Laporan masuk hari ini</h2>
        <p class="mt-2 font-gauge text-4xl font-semibold">{{ $laporHariIni }}<span class="text-xl text-basalt-mute"> / {{ $totalSub }}</span></p>
        <p class="text-sm text-basalt-soft">Sub-D.I. sudah melapor ({{ $persenLapor }}%)</p>
        <ul class="mt-4 space-y-2.5">
            @foreach($kepatuhan as $k)
                @php $p = $k['total'] ? $k['masuk'] / $k['total'] * 100 : 0; @endphp
                <li>
                    <div class="flex items-baseline justify-between gap-2 text-xs">
                        <span class="font-medium">{{ $k['kode'] ? 'Satgas Wilayah '.$k['kode'] : 'Belum masuk Satgas' }}</span>
                        <span class="shrink-0 tabular-nums text-basalt-soft">{{ $k['masuk'] }}/{{ $k['total'] }}</span>
                    </div>
                    @if($k['cakupan'])<p class="text-[11px] leading-snug text-basalt-soft">{{ $k['cakupan'] }}</p>@endif
                    <p class="text-[11px] leading-snug text-basalt-mute">{{ $k['kabupaten'] }}</p>
                    <div class="mt-1 h-1.5 rounded-full bg-savana-deep"><div class="h-1.5 rounded-full {{ $k['kode'] ? 'bg-saluran-500' : 'bg-basalt-mute' }}" style="width: {{ $p }}%"></div></div>
                </li>
            @endforeach
        </ul>
    </section>

    <section class="panel p-5">
        <h2 class="text-lg font-semibold">Luas lahan dari laporan</h2>
        <dl class="mt-3 space-y-3">
            <div class="flex items-baseline justify-between"><dt class="text-sm text-basalt-soft">Luas tanam</dt><dd class="font-gauge text-2xl font-semibold">{{ angka($ringkasan->sum('luas_tanam_ha'), 1) }} Ha</dd></div>
            <div class="-mt-2 flex flex-wrap justify-end gap-x-3 text-xs text-basalt-soft">
                <span>Padi <b class="text-basalt">{{ angka($ringkasan->sum('luas_padi_ha'), 1) }}</b></span>
                <span>Palawija <b class="text-basalt">{{ angka($ringkasan->sum('luas_palawija_ha'), 1) }}</b></span>
                <span>Hortikultura <b class="text-basalt">{{ angka($ringkasan->sum('luas_horti_ha'), 1) }}</b></span>
            </div>
            <div class="flex items-baseline justify-between"><dt class="text-sm text-basalt-soft">Tanaman kekeringan</dt><dd class="font-gauge text-2xl font-semibold text-status-siaga">{{ angka($ringkasan->sum('luas_kering_ha'), 1) }} Ha</dd></div>
            <div class="flex items-baseline justify-between"><dt class="text-sm text-basalt-soft">Puso</dt><dd class="font-gauge text-2xl font-semibold text-status-kritis">{{ angka($ringkasan->sum('luas_puso_ha'), 1) }} Ha</dd></div>
            <div class="flex items-baseline justify-between"><dt class="text-sm text-basalt-soft">Panen</dt><dd class="font-gauge text-2xl font-semibold text-status-aman">{{ angka($ringkasan->sum('luas_panen_ha'), 1) }} Ha</dd></div>
        </dl>
        <p class="mt-3 text-xs text-basalt-mute">Dijumlah dari laporan terakhir tiap Sub-D.I. dalam 3 hari.</p>
    </section>

    <section class="panel p-5">
        <div class="flex items-baseline justify-between"><h2 class="text-lg font-semibold">Kejadian kekeringan</h2><a href="{{ route('kejadian.index') }}" class="text-sm font-medium text-saluran-600">Semua</a></div>
        <ul class="mt-3 space-y-3">
            @foreach(\App\Models\Kejadian::STATUS as $st => $w)
                @php $r = $kejadian->get($st); @endphp
                <li class="flex items-center justify-between gap-2">
                    <a href="{{ route('kejadian.index', ['status' => $st]) }}" class="flex items-center gap-2 text-sm hover:underline"><span class="h-2.5 w-2.5 rounded-full" style="background: {{ $w }}"></span>{{ $st }}</a>
                    <span class="text-right text-sm"><span class="font-gauge text-xl font-semibold">{{ $r->jumlah ?? 0 }}</span> <span class="text-xs text-basalt-mute">lokasi · {{ angka($r->luas ?? 0, 1) }} Ha</span></span>
                </li>
            @endforeach
        </ul>
        @php $tl = $kejadian->sum('luas'); $tt = $kejadian->sum('tertangani'); @endphp
        <p class="mt-3 border-t border-savana-line pt-3 text-sm text-basalt-soft">{{ angka($tt, 1) }} dari {{ angka($tl, 1) }} Ha tertangani ({{ $tl ? round($tt / $tl * 100) : 0 }}%).</p>
    </section>
</div>

<section class="panel mt-5 overflow-hidden">
    <div class="flex flex-wrap items-baseline justify-between gap-2 p-5 pb-3">
        <h2 class="text-lg font-semibold">Sub-D.I. yang perlu perhatian</h2>
        <p class="text-xs text-basalt-mute">Diurutkan dari status terberat dan luas tanaman kekeringan.</p>
    </div>
    @if($kritis->isEmpty())
        <x-kosong judul="Belum ada Sub-D.I. berstatus Waspada ke atas">Status akan muncul setelah petugas mengirim laporan harian.</x-kosong>
    @else
        <div class="overflow-x-auto">
            <table class="table-base">
                <thead><tr><th>Sub-D.I.</th><th>Status</th><th class="text-right">Debit (L/dt)</th><th class="text-right">Tanam / kering / puso (Ha)</th><th>Kendala</th><th>Petugas</th></tr></thead>
                <tbody>
                @foreach($kritis as $x)
                    @php $l = $x['laporan']; $s = $l->subDaerahIrigasi; @endphp
                    <tr>
                        <td><a href="{{ route('laporan.show', $l) }}" class="font-semibold hover:underline">{{ $s->nama }}</a><div class="text-xs text-basalt-soft">D.I. {{ $s->daerahIrigasi->nama }} · {{ $l->tanggal->translatedFormat('d M') }}</div></td>
                        <td><x-status :s="$x['status']"/></td>
                        <td class="text-right tabular-nums">{{ angka($l->debit_intake_lps, 1) }}</td>
                        <td class="text-right tabular-nums">{{ angka($l->luas_tanam_ha, 1) }} / {{ angka($l->luas_kering_ha, 1) }} / {{ angka($l->luas_puso_ha, 1) }}</td>
                        <td class="max-w-xs text-xs text-basalt-soft">{{ \Illuminate\Support\Str::limit($l->kendala, 90) }}</td>
                        <td>
                            <span class="text-xs">{{ $l->user->name }}</span>
                            @if($l->user->waLink())<a href="{{ $l->user->waLink('Selamat siang, terkait laporan '.$s->nama.' tanggal '.$l->tanggal->format('d/m/Y').' di IRIS:') }}" target="_blank" rel="noopener" class="btn-wa ml-1"><x-ikon n="wa" class="h-3 w-3"/> WA</a>@endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
</section>
</x-layouts.app>
