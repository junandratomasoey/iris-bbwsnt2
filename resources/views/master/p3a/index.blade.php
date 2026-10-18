<x-layouts.app title="P3A">
<x-judul judul="P3A / GP3A / IP3A" sub="Perkumpulan petani pemakai air dan kontak ketuanya.">
    @can('kelola-master')<a href="{{ route('master.p3a.create') }}" class="btn-primary"><x-ikon n="tambah" class="h-4 w-4"/> Tambah P3A</a>@endcan
</x-judul>
<form method="GET" class="panel mb-4 flex flex-wrap items-end gap-3 p-4">
    <label class="min-w-[14rem] flex-1"><span class="label">Cari nama/ketua</span><input name="q" value="{{ request('q') }}" class="input"></label>
    <label><span class="label">Kabupaten</span><select name="kabupaten" class="input"><option value="">Semua</option>@foreach($kabupatenList as $k)<option value="{{ $k->id }}" @selected(request('kabupaten') == $k->id)>{{ $k->nama }}</option>@endforeach</select></label>
    <button class="btn-primary">Cari</button>
</form>
<div class="panel overflow-x-auto">
    <table class="table-base">
        <thead><tr><th>Nama</th><th>Jenis</th><th>Ketua</th><th>Kabupaten</th><th>D.I.</th><th>Kontak</th>@can('kelola-master')<th></th>@endcan</tr></thead>
        <tbody>
        @forelse($p3a as $p)
            <tr><td class="font-medium">{{ $p->nama }}</td><td>{{ $p->jenis }}</td><td>{{ $p->ketua }}</td><td>{{ $p->kabupaten?->nama }}</td><td>{{ $p->daerahIrigasi?->nama ?? '–' }}</td>
                <td>@if(wa_link($p->no_hp))<a href="{{ wa_link($p->no_hp) }}" target="_blank" rel="noopener" class="btn-wa"><x-ikon n="wa" class="h-3 w-3"/> {{ $p->no_hp }}</a>@else – @endif</td>
                @can('kelola-master')<td><a href="{{ route('master.p3a.edit', $p) }}" class="text-xs font-medium text-saluran-600">Ubah</a></td>@endcan</tr>
        @empty
            <tr><td colspan="7"><x-kosong judul="Belum ada data P3A"/></td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $p3a->links() }}
</div>
</x-layouts.app>
