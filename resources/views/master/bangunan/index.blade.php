@php use App\Models\Bangunan; $total = $rekap->sum(); @endphp
<x-layouts.app title="Infrastruktur irigasi">
<x-judul judul="Infrastruktur irigasi" sub="Bendung, intake, saluran, dan bangunan bagi/sadap per D.I. Kondisi dan foto tampil di peta saat D.I. diklik.">
    @can('kelola-master')<a href="{{ route('master.bangunan.create', ['di' => request('di')]) }}" class="btn-primary"><x-ikon n="tambah" class="h-4 w-4"/> Tambah</a>@endcan
</x-judul>

@if($total)
<div class="panel mb-4 p-4">
    <div class="flex h-3 overflow-hidden rounded-sm bg-savana-deep" role="img" aria-label="Proporsi kondisi infrastruktur">
        @foreach(Bangunan::KONDISI as $k => $l)<div style="width: {{ ($rekap[$k] ?? 0) / $total * 100 }}%; background: {{ Bangunan::WARNA_KONDISI[$k] }}"></div>@endforeach
    </div>
    <dl class="mt-2 flex flex-wrap gap-x-5 gap-y-1 text-sm">
        @foreach(Bangunan::KONDISI as $k => $l)
            <div class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full" style="background: {{ Bangunan::WARNA_KONDISI[$k] }}"></span><dt>{{ $l }}</dt><dd class="font-semibold tabular-nums">{{ $rekap[$k] ?? 0 }}</dd></div>
        @endforeach
        <div class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-[#9AA4A8]"></span><dt>Belum dinilai</dt><dd class="font-semibold tabular-nums">{{ $rekap[''] ?? 0 }}</dd></div>
    </dl>
</div>
@endif

<form method="GET" class="panel mb-4 flex flex-wrap items-end gap-3 p-4">
    <label class="min-w-[12rem] flex-1"><span class="label">Cari nama</span><input name="q" value="{{ request('q') }}" class="input"></label>
    <label><span class="label">D.I.</span><select name="di" class="input"><option value="">Semua</option>@foreach($diList as $d)<option value="{{ $d->id }}" @selected(request('di') == $d->id)>{{ $d->nama }}</option>@endforeach</select></label>
    <label><span class="label">Jenis</span><select name="jenis" class="input"><option value="">Semua</option>@foreach(Bangunan::JENIS as $k => $l)<option value="{{ $k }}" @selected(request('jenis') === $k)>{{ $l }}</option>@endforeach</select></label>
    <label><span class="label">Kondisi</span><select name="kondisi" class="input"><option value="">Semua</option>@foreach(Bangunan::KONDISI as $k => $l)<option value="{{ $k }}" @selected(request('kondisi') === $k)>{{ $l }}</option>@endforeach<option value="belum" @selected(request('kondisi') === 'belum')>Belum dinilai</option></select></label>
    <label><span class="label">Titik</span><select name="titik" class="input"><option value="">Semua</option><option value="belum" @selected(request('titik') === 'belum')>Belum ada titik</option></select></label>
    <button class="btn-primary">Tampilkan</button>
</form>

<div class="panel overflow-x-auto">
    <table class="table-base">
        <thead><tr><th>Nama</th><th>Jenis</th><th>D.I. / Sub-D.I.</th><th>Kondisi</th><th class="text-center">Foto</th><th class="text-center">Titik</th>@can('kelola-master')<th></th>@endcan</tr></thead>
        <tbody>
        @forelse($bangunan as $b)
            <tr>
                <td class="font-medium">{{ $b->nama }}@if($b->lokasi)<div class="text-xs text-basalt-mute">{{ $b->lokasi }}</div>@endif @if($b->data_teknis)<span class="mt-0.5 inline-block rounded bg-saluran-50 px-1.5 text-[10px] font-semibold text-saluran-700" title="Punya data teknis dari SIGA">SIGA</span>@endif</td>
                <td>{{ $b->jenis_label }}</td>
                <td>{{ $b->daerahIrigasi?->nama }}@if($b->subDaerahIrigasi)<div class="text-xs text-basalt-mute">{{ $b->subDaerahIrigasi->nama }}</div>@endif</td>
                <td><span class="inline-flex items-center gap-1.5 text-sm"><span class="h-2.5 w-2.5 rounded-full" style="background: {{ $b->warna_kondisi }}"></span>{{ $b->kondisi_label }}</span>
                    @if($b->kondisi_per)<div class="text-xs text-basalt-mute">per {{ $b->kondisi_per->translatedFormat('d M Y') }}</div>@endif</td>
                <td class="text-center tabular-nums">{{ $b->foto_count ?: '–' }}</td>
                <td class="text-center">{{ $b->lat ? 'Ada' : '–' }}</td>
                @can('kelola-master')<td><a href="{{ route('master.bangunan.edit', $b) }}" class="text-xs font-medium text-saluran-600">Ubah</a></td>@endcan
            </tr>
        @empty
            <tr><td colspan="7"><x-kosong judul="Tidak ada infrastruktur yang cocok"/></td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $bangunan->links() }}
</div>
</x-layouts.app>
