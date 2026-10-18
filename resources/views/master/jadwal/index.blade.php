<x-layouts.app title="Jadwal tanam">
<x-judul judul="Jadwal musim tanam" sub="Jadwal resmi MT I, II, III per D.I. dari Bidang OP. Dipakai untuk menentukan MT berjalan di peta dan laporan.">
    <form method="GET"><select name="tahun" class="input" onchange="this.form.submit()" aria-label="Tahun">@foreach($tahunList as $t)<option @selected($t == $tahun)>{{ $t }}</option>@endforeach</select></form>
    @can('kelola-master')<a href="{{ route('master.jadwal-tanam.create', ['tahun' => $tahun]) }}" class="btn-primary"><x-ikon n="tambah" class="h-4 w-4"/> Tambah jadwal</a>@endcan
</x-judul>
@php
    $awalTahun = \Carbon\Carbon::create($tahun, 1, 1);
    $hariTahun = $awalTahun->isLeapYear() ? 366 : 365;
    $warnaMt = [1 => '#16798C', 2 => '#4E8A2E', 3 => '#D4A017'];
    $bulan = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
    $posHariIni = today()->year == $tahun ? today()->dayOfYear / $hariTahun * 100 : null;
@endphp
<div class="panel overflow-x-auto">
    <div class="min-w-[760px]">
        <div class="grid grid-cols-[13rem_1fr] border-b border-savana-line bg-savana text-xs font-semibold text-basalt-soft">
            <div class="px-3 py-2">D.I.</div>
            <div class="relative grid grid-cols-12">@foreach($bulan as $b)<div class="border-l border-savana-line px-1 py-2">{{ $b }}</div>@endforeach</div>
        </div>
        @foreach($di as $d)
            <div class="grid grid-cols-[13rem_1fr] border-b border-savana-line text-sm hover:bg-savana/50">
                <div class="px-3 py-2"><a href="{{ route('master.daerah-irigasi.show', $d) }}" class="font-medium hover:underline">{{ $d->nama }}</a><div class="text-xs text-basalt-mute">{{ $d->kabupaten?->nama }}</div></div>
                <div class="relative">
                    <div class="absolute inset-0 grid grid-cols-12">@foreach($bulan as $b)<div class="border-l border-savana-line"></div>@endforeach</div>
                    @if($posHariIni)<div class="absolute inset-y-0 w-px bg-status-kritis" style="left: {{ $posHariIni }}%"></div>@endif
                    @forelse($d->jadwalTanam as $j)
                        @php
                            $mulai = max(0, $awalTahun->diffInDays($j->mulai, false));
                            $akhir = min($hariTahun, $awalTahun->diffInDays($j->selesai, false) + 1);
                        @endphp
                        @if($akhir > 0 && $mulai < $hariTahun)
                            @php $boleh = auth()->user()->can('kelola-master'); @endphp
                            <{{ $boleh ? 'a' : 'span' }} @if($boleh) href="{{ route('master.jadwal-tanam.edit', $j) }}" @endif
                               class="absolute top-1/2 flex h-6 -translate-y-1/2 items-center overflow-hidden rounded px-1.5 text-[11px] font-semibold text-white"
                               style="left: {{ $mulai / $hariTahun * 100 }}%; width: {{ ($akhir - $mulai) / $hariTahun * 100 }}%; background: {{ $warnaMt[$j->mt] }}"
                               title="{{ $j->label }}: {{ $j->mulai->format('d/m/Y') }}–{{ $j->selesai->format('d/m/Y') }} · {{ $j->komoditas }}">MT {{ ['', 'I', 'II', 'III'][$j->mt] }}</{{ $boleh ? 'a' : 'span' }}>
                        @endif
                    @empty
                        @can('kelola-master')<a href="{{ route('master.jadwal-tanam.create', ['di' => $d->id, 'tahun' => $tahun]) }}" class="relative flex h-full items-center px-3 text-xs text-basalt-mute hover:text-saluran-600">Belum ada jadwal — tambahkan</a>@else<span class="relative flex h-full items-center px-3 text-xs text-basalt-mute">Belum ada jadwal</span>@endcan
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
</div>
<p class="mt-2 flex flex-wrap gap-4 text-xs text-basalt-soft">
    @foreach($warnaMt as $m => $w)<span class="flex items-center gap-1.5"><span class="h-2.5 w-4 rounded-sm" style="background: {{ $w }}"></span>MT {{ ['', 'I', 'II', 'III'][$m] }}</span>@endforeach
    @if($posHariIni)<span class="flex items-center gap-1.5"><span class="h-3 w-px bg-status-kritis"></span>Hari ini</span>@endif
</p>
</x-layouts.app>
