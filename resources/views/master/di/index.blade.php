<x-layouts.app title="Daerah irigasi">
<x-judul judul="Daerah irigasi" :sub="$di->count().' D.I. kewenangan pusat · '.angka($di->sum('luas_permen_ha')).' Ha (Permen PUPR 14/2015)'">
    @can('kelola-master')<a href="{{ route('master.daerah-irigasi.create') }}" class="btn-primary"><x-ikon n="tambah" class="h-4 w-4"/> Tambah D.I.</a>@endcan
</x-judul>
<div class="panel overflow-x-auto">
    <table class="table-base">
        <thead><tr><th>Kode</th><th>D.I.</th><th>Kabupaten</th><th>Satgas</th><th class="text-right">Luas Permen (Ha)</th><th class="text-right">Fungsional SHP (Ha)</th><th class="text-right">Sub-D.I.</th><th class="text-right">Petugas</th><th>Status hari ini</th></tr></thead>
        <tbody>
        @foreach($di as $d)
            @php $r = $ringkasan->get($d->id); @endphp
            <tr class="hover:bg-savana">
                <td class="text-xs text-basalt-soft">{{ $d->kode }}</td>
                <td><a href="{{ route('master.daerah-irigasi.show', $d) }}" class="font-semibold text-saluran-700 hover:underline">{{ $d->nama }}</a>@if($d->keterangan)<div class="text-xs text-basalt-mute">{{ $d->keterangan }}</div>@endif</td>
                <td>{{ $d->kabupaten?->nama ?? '–' }}</td>
                <td>{{ $d->kabupaten?->wilayahSatgas?->kode ?? '–' }}</td>
                <td class="text-right tabular-nums">{{ angka($d->luas_permen_ha) }}</td>
                <td class="text-right tabular-nums">{{ angka($d->luas_fungsional_ha) }}</td>
                <td class="text-right">{{ $d->sub_daerah_irigasi_count }}</td>
                <td class="text-right">{{ $d->petugas_count }}</td>
                <td><x-status :s="$r['status'] ?? 'kosong'"/></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
</x-layouts.app>
