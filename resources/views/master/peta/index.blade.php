@php use App\Models\PetaDi; $kelola = auth()->user()->can('kelola-master'); @endphp
<x-layouts.app title="Peta batas D.I.">
<x-judul judul="Peta batas Daerah Irigasi" sub="Poligon areal D.I. yang tampil di peta publik dan internal. Setiap perubahan langsung memperbarui peta dan luas D.I.">
    <a href="{{ route('master.peta.unduh', ['lapisan' => $lapisan]) }}" class="btn-ghost"><x-ikon n="unduh" class="h-4 w-4"/> Unduh GeoJSON</a>
    @if($kelola)
        <a href="{{ route('master.peta.import', ['lapisan' => $lapisan]) }}" class="btn-ghost"><x-ikon n="unduh" class="h-4 w-4 rotate-180"/> Import</a>
        <a href="{{ route('master.peta.create', ['lapisan' => $lapisan]) }}" class="btn-primary"><x-ikon n="tambah" class="h-4 w-4"/> Gambar batas baru</a>
    @endif
</x-judul>

<nav class="mb-4 flex gap-1 border-b border-savana-line" aria-label="Lapisan">
    @foreach(PetaDi::LAPISAN as $k => $l)
        <a href="{{ route('master.peta.index', ['lapisan' => $k]) }}" @class(['-mb-px border-b-2 px-4 py-2 text-sm font-semibold', 'border-saluran-600 text-saluran-700' => $lapisan === $k, 'border-transparent text-basalt-soft hover:text-basalt' => $lapisan !== $k])>
            {{ $l }} <span class="ml-1 rounded-full bg-savana-deep px-1.5 text-xs">{{ $jumlah[$k] ?? 0 }}</span>
        </a>
    @endforeach
</nav>

@if(request('di'))
    <p class="mb-3 text-sm">Menampilkan batas untuk satu D.I. <a href="{{ route('master.peta.index', ['lapisan' => $lapisan]) }}" class="font-medium text-saluran-600">Tampilkan semua</a></p>
@endif

@if($tanpaPeta->isNotEmpty() && ! request('di'))
    <div class="mb-4 rounded-md border border-[#F1DFA8] bg-[#FBF6E4] px-4 py-3 text-sm">
        <p class="font-semibold">{{ $tanpaPeta->count() }} D.I. belum punya batas {{ strtolower(PetaDi::LAPISAN[$lapisan]) }}:</p>
        <p class="mt-1 flex flex-wrap gap-x-3 gap-y-1">
            @foreach($tanpaPeta as $d)
                @if($kelola)<a href="{{ route('master.peta.create', ['lapisan' => $lapisan, 'di' => $d->id]) }}" class="text-saluran-700 underline-offset-2 hover:underline">{{ $d->nama }}</a>@else<span>{{ $d->nama }}</span>@endif
            @endforeach
        </p>
    </div>
@endif

<div class="panel overflow-x-auto">
    <table class="table-base">
        <thead><tr><th>Nama batas</th><th>D.I.</th><th class="text-right">Luas (Ha)</th><th>Sumber</th><th>Diperbarui</th><th></th></tr></thead>
        <tbody>
        @forelse($daftar as $p)
            <tr @class(['bg-[#FBF6E4]' => ! $p->daerah_irigasi_id])>
                <td class="font-medium">{{ $p->nama }}</td>
                <td>@if($p->daerahIrigasi)<a href="{{ route('master.daerah-irigasi.show', $p->daerahIrigasi) }}" class="text-saluran-700 hover:underline">{{ $p->daerahIrigasi->nama }}</a>@else<span class="text-xs font-semibold text-status-siaga">Belum terhubung</span>@endif</td>
                <td class="text-right tabular-nums">{{ angka($p->luas_ha, 2) }}</td>
                <td class="max-w-xs text-xs text-basalt-soft">{{ $p->sumber }}</td>
                <td class="whitespace-nowrap text-xs text-basalt-soft">{{ $p->updated_at->translatedFormat('d M Y H:i') }}</td>
                <td class="whitespace-nowrap text-right text-xs">
                    @if($kelola)<a href="{{ route('master.peta.edit', $p) }}" class="font-semibold text-saluran-600">Ubah di peta</a>@endif
                    <a href="{{ route('master.peta.unduh', ['lapisan' => $lapisan, 'id' => $p->id]) }}" class="ml-2 text-basalt-soft hover:text-basalt">Unduh</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="6"><x-kosong judul="Belum ada batas pada lapisan ini" :aksi="$kelola ? 'Import GeoJSON' : null" :url="route('master.peta.import', ['lapisan' => $lapisan])">Import dari ArcGIS/QGIS atau gambar langsung di peta.</x-kosong></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
@if($kelola)
    <form method="POST" action="{{ route('master.peta.bangun-ulang') }}" class="mt-4 text-right">@csrf
        <button class="text-xs text-basalt-soft underline-offset-2 hover:underline">Bangun ulang file peta dari database</button>
    </form>
@endif
</x-layouts.app>
