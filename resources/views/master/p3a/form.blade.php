@php $baru = ! $p3a->exists; @endphp
<x-layouts.app :title="$baru ? 'Tambah P3A' : 'Ubah P3A'">
<div class="mx-auto max-w-2xl">
<x-judul :judul="$baru ? 'Tambah P3A' : 'Ubah '.$p3a->nama"/>
<form method="POST" action="{{ $baru ? route('master.p3a.store') : route('master.p3a.update', $p3a) }}" class="panel grid gap-4 p-5 sm:grid-cols-2">
    @csrf @if(! $baru) @method('PUT') @endif
    <x-field name="nama" label="Nama" wajib><input id="nama" name="nama" value="{{ old('nama', $p3a->nama) }}" required class="input"></x-field>
    <x-field name="jenis" label="Jenis" wajib><select id="jenis" name="jenis" class="input">@foreach(['P3A', 'GP3A', 'IP3A'] as $j)<option @selected(old('jenis', $p3a->jenis) === $j)>{{ $j }}</option>@endforeach</select></x-field>
    <x-field name="ketua" label="Ketua"><input id="ketua" name="ketua" value="{{ old('ketua', $p3a->ketua) }}" class="input"></x-field>
    <x-field name="no_hp" label="No. HP ketua"><input id="no_hp" name="no_hp" inputmode="tel" value="{{ old('no_hp', $p3a->no_hp) }}" class="input"></x-field>
    <x-field name="kabupaten_id" label="Kabupaten"><select id="kabupaten_id" name="kabupaten_id" class="input"><option value="">–</option>@foreach($kabupatenList as $k)<option value="{{ $k->id }}" @selected(old('kabupaten_id', $p3a->kabupaten_id) == $k->id)>{{ $k->nama }}</option>@endforeach</select></x-field>
    <x-field name="daerah_irigasi_id" label="D.I."><select id="daerah_irigasi_id" name="daerah_irigasi_id" class="input"><option value="">–</option>@foreach($diList as $d)<option value="{{ $d->id }}" @selected(old('daerah_irigasi_id', $p3a->daerah_irigasi_id) == $d->id)>{{ $d->nama }}</option>@endforeach</select></x-field>
    <div class="flex gap-2 sm:col-span-2"><button class="btn-primary px-8">Simpan</button><a href="{{ route('master.p3a.index') }}" class="btn-ghost">Batal</a></div>
</form>
@if(! $baru)<form method="POST" action="{{ route('master.p3a.destroy', $p3a) }}" class="mt-6" onsubmit="return confirm('Hapus P3A ini?')">@csrf @method('DELETE')<button class="text-sm font-medium text-status-kritis">Hapus P3A ini</button></form>@endif
</div>
</x-layouts.app>
