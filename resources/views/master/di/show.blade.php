@php use App\Models\Bangunan; use App\Support\StatusKondisi as SK; $kelola = auth()->user()->can('kelola-master'); @endphp
<x-layouts.app :title="'D.I. '.$di->nama">
<x-judul :judul="'D.I. '.$di->nama" :sub="collect([$di->kabupaten?->nama, $di->kabupaten?->wilayahSatgas ? 'Satgas Wilayah '.$di->kabupaten->wilayahSatgas->kode : null, 'Kewenangan '.$di->kewenangan, $di->keterangan])->filter()->implode(' · ')">
    <a href="{{ route('peta') }}" class="btn-ghost"><x-ikon n="peta" class="h-4 w-4"/> Peta</a>
    @if($kelola)<a href="{{ route('master.daerah-irigasi.edit', $di) }}" class="btn-ghost"><x-ikon n="edit" class="h-4 w-4"/> Ubah</a>@endif
</x-judul>

<div class="grid gap-5 lg:grid-cols-4">
    <section class="panel p-5 lg:col-span-1">
        <x-status :s="$ring['status'] ?? 'kosong'"/>
        <dl class="mt-3 space-y-2 text-sm">
            <div class="flex justify-between"><dt class="text-basalt-soft">Luas Permen</dt><dd class="font-semibold">{{ angka($di->luas_permen_ha) }} Ha</dd></div>
            <div class="flex justify-between"><dt class="text-basalt-soft">Luas baku (SHP)</dt><dd>{{ angka($di->luas_baku_ha) }} Ha</dd></div>
            <div class="flex justify-between"><dt class="text-basalt-soft">Potensial (SHP)</dt><dd>{{ angka($di->luas_potensial_ha) }} Ha</dd></div>
            <div class="flex justify-between"><dt class="text-basalt-soft">Fungsional (SHP)</dt><dd>{{ angka($di->luas_fungsional_ha) }} Ha</dd></div>
            <div class="flex justify-between"><dt class="text-basalt-soft">MT berjalan</dt><dd>{{ $ring['mt'] ?? 'Belum dijadwalkan' }}</dd></div>
            <div class="flex justify-between"><dt class="text-basalt-soft">Melapor (3 hari)</dt><dd>{{ $ring['sub_lapor'] ?? 0 }}/{{ $ring['jumlah_sub'] ?? 0 }} Sub-D.I.</dd></div>
            <div class="flex justify-between"><dt class="text-basalt-soft">Total debit intake</dt><dd>{{ angka($ring['debit_total_lps'] ?? 0, 1) }} L/dt</dd></div>
        </dl>
        @if($di->tenaga_op)
            <p class="mt-4 text-xs font-semibold text-basalt-soft">Tenaga O&amp;P (Permen)</p>
            <p class="text-xs">{{ collect($di->tenaga_op)->filter()->map(fn ($v, $k) => ucfirst($k).' '.(int) $v)->implode(', ') }}</p>
        @endif
    </section>

    <section class="panel overflow-hidden lg:col-span-3">
        <div class="flex items-baseline justify-between p-5 pb-2"><h2 class="text-lg font-semibold">Sub-D.I. ({{ $di->subDaerahIrigasi->count() }})</h2>
            @if($kelola)<a href="{{ route('master.daerah-irigasi.sub.create', $di) }}" class="btn-ghost py-1"><x-ikon n="tambah" class="h-4 w-4"/> Tambah</a>@endif</div>
        <div class="overflow-x-auto">
            <table class="table-base">
                <thead><tr><th>Sub-D.I.</th><th class="text-right">Luas (Ha)</th><th>Status</th><th>Laporan terakhir</th><th>Petugas</th><th>Titik</th>@if($kelola)<th></th>@endif</tr></thead>
                <tbody>
                @foreach($di->subDaerahIrigasi as $s)
                    @php $l = $laporanTerkini->get($s->id); @endphp
                    <tr>
                        <td class="font-medium">{{ $s->nama }}@if($s->lokasi)<div class="text-xs text-basalt-mute">{{ $s->lokasi }}</div>@endif</td>
                        <td class="text-right tabular-nums">{{ angka($s->luas_ha) }}</td>
                        <td><x-status :s="SK::dariLaporan($l)"/></td>
                        <td class="text-xs">@if($l)<a href="{{ route('laporan.show', $l) }}" class="text-saluran-600 hover:underline">{{ $l->tanggal->translatedFormat('d M') }} · {{ angka($l->debit_intake_lps, 1) }} L/dt</a>@else – @endif</td>
                        <td class="text-xs">@foreach($s->petugas->take(3) as $p)<div class="flex items-center gap-1">{{ $p->name }}@if($p->waLink())<a href="{{ $p->waLink() }}" target="_blank" rel="noopener" class="font-semibold text-[#1f8f4e]">WA</a>@endif</div>@endforeach @if($s->petugas->count() > 3)<span class="text-basalt-mute">+{{ $s->petugas->count() - 3 }} lagi</span>@endif</td>
                        <td class="text-xs">{{ $s->lat ? 'Ada' : 'Belum' }}</td>
                        @if($kelola)<td><a href="{{ route('master.sub.edit', $s) }}" class="text-xs font-medium text-saluran-600">Ubah</a></td>@endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="mt-5 grid gap-5 lg:grid-cols-2">
    <section class="panel p-5">
        <h2 class="text-lg font-semibold">Tren 30 hari</h2>
        @if($tren->count() < 2)
            <p class="mt-2 text-sm text-basalt-soft">Grafik muncul setelah ada laporan di minimal dua tanggal.</p>
        @else
            @php $mx = max(1, $tren->max('debit')); @endphp
            <p class="mt-1 text-xs text-basalt-soft">Total debit intake harian (L/dt) dari Sub-D.I. yang melapor.</p>
            <div class="mt-3 flex h-32 items-end gap-0.5" role="img" aria-label="Tren debit 30 hari">
                @foreach($tren as $t)<div class="flex-1 rounded-t bg-saluran-500" style="height: {{ max(2, $t->debit / $mx * 100) }}%" title="{{ \Carbon\Carbon::parse($t->tanggal)->format('d/m') }}: {{ angka($t->debit, 1) }} L/dt, {{ $t->n }} laporan"></div>@endforeach
            </div>
            <div class="mt-1 flex justify-between text-[10px] text-basalt-mute"><span>{{ \Carbon\Carbon::parse($tren->first()->tanggal)->format('d/m') }}</span><span>{{ \Carbon\Carbon::parse($tren->last()->tanggal)->format('d/m') }}</span></div>
        @endif
    </section>
    <section class="panel p-5">
        <div class="flex items-baseline justify-between"><h2 class="text-lg font-semibold">Jadwal tanam</h2>
            @if($kelola)<a href="{{ route('master.jadwal-tanam.create', ['di' => $di->id]) }}" class="text-sm font-medium text-saluran-600">Tambah jadwal</a>@endif</div>
        @forelse($di->jadwalTanam->sortByDesc('tahun')->take(6) as $j)
            <div class="mt-2 flex justify-between border-b border-savana-line py-1.5 text-sm"><span class="font-medium">{{ $j->label }}</span><span class="text-basalt-soft">{{ $j->mulai->translatedFormat('d M') }} – {{ $j->selesai->translatedFormat('d M Y') }} · {{ $j->komoditas }}</span></div>
        @empty
            <p class="mt-2 text-sm text-basalt-soft">Belum ada jadwal MT dari Bidang OP.</p>
        @endforelse
    </section>
    <section class="panel p-5">
        <h2 class="text-lg font-semibold">Bangunan ({{ $di->bangunan->count() }})</h2>
        <ul class="mt-2 divide-y divide-savana-line text-sm">
            @foreach($di->bangunan as $b)<li class="flex justify-between py-1.5"><span>{{ $b->nama }}</span><span class="text-basalt-soft">{{ Bangunan::JENIS[$b->jenis] ?? $b->jenis }}</span></li>@endforeach
        </ul>
    </section>
    <section class="panel p-5">
        <h2 class="text-lg font-semibold">Petugas ({{ $di->petugas->count() }})</h2>
        <ul class="mt-2 max-h-72 divide-y divide-savana-line overflow-y-auto text-sm">
            @foreach($di->petugas as $p)
                <li class="flex items-center justify-between gap-2 py-1.5"><span>{{ $p->name }} <span class="text-xs text-basalt-mute">{{ $p->jabatan }}</span></span>
                    @if($p->waLink())<a href="{{ $p->waLink() }}" target="_blank" rel="noopener" class="btn-wa"><x-ikon n="wa" class="h-3 w-3"/> {{ $p->no_hp }}</a>@endif</li>
            @endforeach
        </ul>
    </section>
</div>
</x-layouts.app>
