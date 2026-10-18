<x-layouts.app title="Kontak petugas">
<x-judul judul="Kontak petugas lapangan" :sub="angka($petugas->total()).' petugas aktif'"/>
<form method="GET" class="panel mb-4 grid gap-3 p-4 sm:grid-cols-2 lg:grid-cols-4">
    <label class="lg:col-span-2"><span class="label">Cari nama atau no. HP</span><input name="q" value="{{ request('q') }}" class="input"></label>
    <label><span class="label">D.I.</span><select name="di" class="input"><option value="">Semua</option>@foreach($diList as $d)<option value="{{ $d->id }}" @selected(request('di') == $d->id)>{{ $d->nama }}</option>@endforeach</select></label>
    <label><span class="label">Jabatan</span><select name="jabatan" class="input" onchange="this.form.submit()"><option value="">Semua</option>@foreach($jabatanList as $j)<option @selected(request('jabatan') === $j)>{{ $j }}</option>@endforeach</select></label>
</form>
<div class="panel overflow-hidden">
    <ul class="grid divide-y divide-savana-line sm:grid-cols-2 sm:divide-y-0 lg:grid-cols-3">
        @forelse($petugas as $p)
            <li class="flex items-start justify-between gap-3 border-savana-line p-4 sm:border-b sm:odd:border-r lg:border-r">
                <div class="min-w-0">
                    <p class="font-semibold">{{ $p->name }}</p>
                    <p class="text-xs text-basalt-soft">{{ $p->jabatan ?? $p->roleLabel() }}@if($p->daerahIrigasi) · D.I. {{ $p->daerahIrigasi->nama }}@endif</p>
                    @if($p->subDaerahIrigasi->isNotEmpty())<p class="mt-0.5 truncate text-xs text-basalt-mute">{{ $p->subDaerahIrigasi->pluck('nama')->implode(', ') }}</p>@endif
                </div>
                @if($p->waLink())<a href="{{ $p->waLink() }}" target="_blank" rel="noopener" class="btn-wa shrink-0"><x-ikon n="wa" class="h-3.5 w-3.5"/> {{ $p->no_hp }}</a>@else<span class="text-xs text-basalt-mute">Tanpa no. HP</span>@endif
            </li>
        @empty
            <li class="sm:col-span-2 lg:col-span-3"><x-kosong judul="Tidak ada petugas yang cocok"/></li>
        @endforelse
    </ul>
    {{ $petugas->links() }}
</div>
</x-layouts.app>
