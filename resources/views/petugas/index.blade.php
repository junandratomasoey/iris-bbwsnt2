<x-layouts.app title="Kontak petugas">
<x-judul judul="Kontak petugas lapangan" :sub="angka($petugas->total()).' kontak'.($status === 'aktif' ? ' aktif' : '')">
    @if($kelola)<a href="{{ route('petugas.create', ['di' => request('di')]) }}" class="btn-primary"><x-ikon n="tambah" class="h-4 w-4"/> Tambah petugas</a>@endif
</x-judul>
<form method="GET" class="panel mb-4 grid gap-3 p-4 sm:grid-cols-2 {{ $kelola ? 'lg:grid-cols-5' : 'lg:grid-cols-4' }}">
    <label class="lg:col-span-2"><span class="label">Cari nama atau no. HP</span><input type="search" name="q" value="{{ request('q') }}" class="input"></label>
    <label><span class="label">D.I.</span><select name="di" class="input" onchange="this.form.submit()"><option value="">Semua</option>@foreach($diList as $d)<option value="{{ $d->id }}" @selected(request('di') == $d->id)>{{ $d->nama }}</option>@endforeach</select></label>
    <label><span class="label">Jabatan</span><select name="jabatan" class="input" onchange="this.form.submit()"><option value="">Semua</option>@foreach($jabatanList as $j)<option @selected(request('jabatan') === $j)>{{ $j }}</option>@endforeach</select></label>
    @if($kelola)
        <label><span class="label">Status</span><select name="status" class="input" onchange="this.form.submit()">
            @foreach(['aktif' => 'Aktif', 'belum_login' => 'Belum pernah login', 'nonaktif' => 'Nonaktif', 'semua' => 'Semua'] as $k => $l)<option value="{{ $k }}" @selected($status === $k)>{{ $l }}</option>@endforeach
        </select></label>
    @endif
    <button class="sr-only">Cari</button>
</form>
<div class="panel overflow-hidden">
    <ul class="grid divide-y divide-savana-line sm:grid-cols-2 sm:divide-y-0 lg:grid-cols-3">
        @forelse($petugas as $p)
            @php $ubah = $kelola && $boleh($p); @endphp
            <li class="flex flex-col gap-2 border-savana-line p-4 sm:border-b sm:odd:border-r lg:border-r {{ $p->is_active ? '' : 'bg-savana' }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="font-semibold">{{ $p->name }}
                            @if(! $p->is_active)<span class="ml-1 inline-block whitespace-nowrap rounded bg-savana-deep px-1.5 text-[11px] font-medium text-basalt-soft">Nonaktif</span>
                            @elseif($kelola && ! $p->last_login_at)<span class="ml-1 inline-block whitespace-nowrap rounded bg-[#FDF3D7] px-1.5 text-[11px] font-medium text-[#7A5A00]">Belum login</span>@endif
                        </p>
                        <p class="text-xs text-basalt-soft">{{ $p->jabatan ?? $p->roleLabel() }}@if($p->daerahIrigasi) · D.I. {{ $p->daerahIrigasi->nama }}@endif</p>
                        @if($p->subDaerahIrigasi->isNotEmpty())<p class="mt-0.5 truncate text-xs text-basalt-mute" title="{{ $p->subDaerahIrigasi->pluck('nama')->implode(', ') }}">{{ $p->subDaerahIrigasi->pluck('nama')->implode(', ') }}</p>@endif
                    </div>
                    @if($ubah)<a href="{{ route('petugas.edit', $p) }}" class="shrink-0 rounded p-1 text-basalt-soft hover:bg-savana hover:text-saluran-700" aria-label="Ubah {{ $p->name }}" title="Ubah"><x-ikon n="edit" class="h-4 w-4"/></a>@endif
                </div>
                <div>@if($p->waLink())<a href="{{ $p->waLink() }}" target="_blank" rel="noopener" class="btn-wa"><x-ikon n="wa" class="h-3.5 w-3.5"/> {{ $p->no_hp }}</a>@else<span class="text-xs text-basalt-mute">Tanpa no. HP</span>@endif</div>
            </li>
        @empty
            <li class="sm:col-span-2 lg:col-span-3"><x-kosong judul="Tidak ada petugas yang cocok" :aksi="$kelola ? 'Tambah petugas' : null" :url="route('petugas.create', ['di' => request('di')])"/></li>
        @endforelse
    </ul>
    {{ $petugas->links() }}
</div>
</x-layouts.app>
