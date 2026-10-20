@php use App\Models\LaporanHarian as LH; @endphp
<x-layouts.app title="Laporan air">
<x-judul judul="Laporan harian" sub="Air sungai, intake & saluran diisi Manager per D.I.; kondisi tanaman diisi PPA per petak.">
    @can('lapor-air')<a href="{{ route('laporan-air.create') }}" class="btn-primary"><x-ikon n="tambah" class="h-4 w-4"/> Isi laporan air</a>@endcan
</x-judul>
<x-tab-laporan aktif="air"/>

@if($hariIni->isNotEmpty())
    <ul class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        @foreach($hariIni as $h)
            <li class="panel flex items-center justify-between gap-3 p-4">
                <div><p class="font-semibold">D.I. {{ $h['di']->nama }}</p>
                    @if($h['laporan'])<p class="text-xs font-medium text-status-aman">Hari ini sudah dilaporkan</p>@else<p class="text-xs font-semibold text-[#8A3510]">Belum ada laporan air hari ini</p>@endif</div>
                @if($h['laporan'])<a href="{{ route('laporan-air.show', $h['laporan']) }}" class="btn-ghost shrink-0">Lihat</a>
                @else<a href="{{ route('laporan-air.create', ['di' => $h['di']->id]) }}" class="btn-primary shrink-0"><x-ikon n="tambah" class="h-4 w-4"/> Lapor</a>@endif
            </li>
        @endforeach
    </ul>
@endif

<form method="GET" class="panel mb-4 grid gap-3 p-3 sm:grid-cols-2 lg:grid-cols-4">
    <label><span class="label">D.I.</span><select name="di" class="input"><option value="">Semua</option>@foreach($diList as $d)<option value="{{ $d->id }}" @selected(request('di') == $d->id)>{{ $d->nama }}</option>@endforeach</select></label>
    <label><span class="label">Dari</span><input type="date" name="dari" value="{{ request('dari') }}" class="input"></label>
    <label><span class="label">Sampai</span><input type="date" name="sampai" value="{{ request('sampai') }}" class="input"></label>
    <div class="flex items-end"><button class="btn-primary w-full">Terapkan</button></div>
</form>
<div class="panel overflow-x-auto">
    @if($laporan->isEmpty())
        <x-kosong judul="Belum ada laporan air">Laporan air sungai, intake & saluran dari Manager D.I. akan muncul di sini.</x-kosong>
    @else
        <table class="table-base">
            <thead><tr><th>Tanggal</th><th>D.I.</th><th class="text-right">Debit sungai</th><th class="text-right">TMA sungai</th><th class="text-right">Debit intake</th><th class="text-right">TMA intake</th><th>Saluran</th><th class="text-right">Foto</th><th>Pelapor</th></tr></thead>
            <tbody>
            @foreach($laporan as $l)
                <tr>
                    <td class="whitespace-nowrap"><a href="{{ route('laporan-air.show', $l) }}" class="font-medium text-saluran-600">{{ $l->tanggal->translatedFormat('D, d M Y') }}</a></td>
                    <td>{{ $l->daerahIrigasi->nama }}</td>
                    <td class="whitespace-nowrap text-right">{{ $l->debit_sungai_m3s !== null ? angka_r($l->debit_sungai_m3s).' m³/dt' : '–' }}</td>
                    <td class="whitespace-nowrap text-right">{{ $l->tma_sungai_cm !== null ? angka_r($l->tma_sungai_cm).' cm' : '–' }}</td>
                    <td class="whitespace-nowrap text-right">{{ angka_r($l->debit_intake_lps) }} L/dt</td>
                    <td class="whitespace-nowrap text-right">{{ $l->tma_intake_cm !== null ? angka_r($l->tma_intake_cm).' cm' : '–' }}</td>
                    <td><x-status :s="$l->status()" :label="LH::KONDISI[$l->kondisi_saluran] ?? $l->kondisi_saluran"/></td>
                    <td class="text-right">{{ $l->foto_count ?: '–' }}</td>
                    <td class="text-xs">{{ $l->user->name }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        {{ $laporan->links() }}
    @endif
</div>
</x-layouts.app>
