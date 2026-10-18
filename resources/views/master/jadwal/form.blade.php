@php $baru = ! $jadwal->exists; @endphp
<x-layouts.app :title="$baru ? 'Tambah jadwal tanam' : 'Ubah jadwal tanam'">
<div class="mx-auto max-w-2xl">
<x-judul :judul="$baru ? 'Tambah jadwal tanam' : 'Ubah '.$jadwal->label" sub="Sesuai jadwal resmi Bidang OP."/>
<form method="POST" action="{{ $baru ? route('master.jadwal-tanam.store') : route('master.jadwal-tanam.update', $jadwal) }}" class="panel grid gap-4 p-5 sm:grid-cols-3">
    @csrf @if(! $baru) @method('PUT') @endif
    <x-field name="daerah_irigasi_id" label="D.I." class="sm:col-span-3" wajib><select id="daerah_irigasi_id" name="daerah_irigasi_id" required class="input"><option value="">Pilih D.I.</option>@foreach($diList as $d)<option value="{{ $d->id }}" @selected(old('daerah_irigasi_id', $jadwal->daerah_irigasi_id) == $d->id)>{{ $d->nama }}</option>@endforeach</select></x-field>
    <x-field name="tahun" label="Tahun" wajib><input id="tahun" type="number" name="tahun" value="{{ old('tahun', $jadwal->tahun) }}" required class="input"></x-field>
    <x-field name="mt" label="Musim tanam" wajib><select id="mt" name="mt" class="input">@foreach([1 => 'MT I', 2 => 'MT II', 3 => 'MT III'] as $k => $l)<option value="{{ $k }}" @selected(old('mt', $jadwal->mt) == $k)>{{ $l }}</option>@endforeach</select></x-field>
    <x-field name="komoditas" label="Komoditas" wajib><input id="komoditas" name="komoditas" value="{{ old('komoditas', $jadwal->komoditas) }}" list="komoditas-list" required class="input"><datalist id="komoditas-list"><option>Padi</option><option>Palawija</option><option>Jagung</option><option>Kedelai</option></datalist></x-field>
    <x-field name="mulai" label="Mulai" wajib><input id="mulai" type="date" name="mulai" value="{{ old('mulai', optional($jadwal->mulai)->toDateString()) }}" required class="input"></x-field>
    <x-field name="selesai" label="Selesai" wajib><input id="selesai" type="date" name="selesai" value="{{ old('selesai', optional($jadwal->selesai)->toDateString()) }}" required class="input"></x-field>
    <x-field name="sumber" label="Sumber" wajib><input id="sumber" name="sumber" value="{{ old('sumber', $jadwal->sumber) }}" required class="input"></x-field>
    @foreach(['rencana_tanam_ha' => 'Rencana tanam (Ha)', 'realisasi_tanam_ha' => 'Realisasi tanam (Ha)', 'panen_ha' => 'Panen (Ha)', 'puso_ha' => 'Puso (Ha)'] as $n => $l)
        <x-field :name="$n" :label="$l"><input id="{{ $n }}" type="number" step="0.01" name="{{ $n }}" value="{{ old($n, $jadwal->$n) }}" class="input"></x-field>
    @endforeach
    <x-field name="keterangan" label="Keterangan" class="sm:col-span-3"><textarea id="keterangan" name="keterangan" rows="2" class="input">{{ old('keterangan', $jadwal->keterangan) }}</textarea></x-field>
    <div class="flex gap-2 sm:col-span-3"><button class="btn-primary px-8">Simpan</button><a href="{{ route('master.jadwal-tanam.index', ['tahun' => $jadwal->tahun]) }}" class="btn-ghost">Batal</a></div>
</form>
@if(! $baru)
    <form method="POST" action="{{ route('master.jadwal-tanam.destroy', $jadwal) }}" class="mt-6" onsubmit="return confirm('Hapus jadwal ini?')">@csrf @method('DELETE')<button class="text-sm font-medium text-status-kritis">Hapus jadwal ini</button></form>
@endif
</div>
</x-layouts.app>
