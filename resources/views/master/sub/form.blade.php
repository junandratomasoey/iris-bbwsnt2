@php $baru = ! $sub->exists; @endphp
<x-layouts.app :title="$baru ? 'Tambah Sub-D.I.' : 'Ubah '.$sub->nama">
<div class="mx-auto max-w-3xl">
<x-judul :judul="$baru ? 'Tambah Sub-D.I.' : 'Ubah Sub-D.I. '.$sub->nama" :sub="'D.I. '.$di->nama"/>
<form method="POST" action="{{ $baru ? route('master.daerah-irigasi.sub.store', $di) : route('master.sub.update', $sub) }}" class="space-y-5">
    @csrf @if(! $baru) @method('PUT') @endif
    <fieldset class="panel grid gap-4 p-5 sm:grid-cols-2">
        <x-field name="nama" label="Nama Sub-D.I." wajib><input id="nama" name="nama" value="{{ old('nama', $sub->nama) }}" required class="input"></x-field>
        <x-field name="luas_ha" label="Luas (Ha)"><input id="luas_ha" name="luas_ha" type="number" step="0.01" value="{{ old('luas_ha', $sub->luas_ha) }}" class="input"></x-field>
        <x-field name="lokasi" label="Lokasi / desa"><input id="lokasi" name="lokasi" value="{{ old('lokasi', $sub->lokasi) }}" class="input"></x-field>
        <x-field name="kabupaten_id" label="Kabupaten"><select id="kabupaten_id" name="kabupaten_id" class="input"><option value="">–</option>@foreach($kabupatenList as $k)<option value="{{ $k->id }}" @selected(old('kabupaten_id', $sub->kabupaten_id) == $k->id)>{{ $k->nama }}</option>@endforeach</select></x-field>
        <x-field name="keterangan" label="Keterangan" class="sm:col-span-2"><textarea id="keterangan" name="keterangan" rows="2" class="input">{{ old('keterangan', $sub->keterangan) }}</textarea></x-field>
    </fieldset>
    <fieldset class="panel space-y-3 p-5" x-data="pickLocation({ lat: '{{ old('lat', $sub->lat) }}', lng: '{{ old('lng', $sub->lng) }}', pusat: {{ $di->lat ? '['.$di->lat.','.$di->lng.']' : 'null' }} })">
        <legend class="float-left font-gauge text-lg font-semibold">Titik Sub-D.I.</legend>
        <p class="clear-left text-sm text-basalt-soft">Ketuk peta di lokasi bangunan pengambilan atau pusat areal Sub-D.I. Sampai diisi, peta memakai titik tengah D.I.</p>
        <div x-ref="peta" class="h-72 rounded border border-savana-line"></div>
        <div class="flex flex-wrap items-end gap-2"><label><span class="label">Lintang</span><input name="lat" x-model="lat" class="input w-36"></label><label><span class="label">Bujur</span><input name="lng" x-model="lng" class="input w-36"></label><button type="button" class="btn-ghost" @click="gpsSaya"><x-ikon n="lokasi" class="h-4 w-4"/> GPS</button></div>
        @error('lat')<p class="text-xs font-medium text-status-kritis">{{ $message }}</p>@enderror
    </fieldset>
    <div class="flex gap-2"><button class="btn-primary px-8">Simpan</button><a href="{{ route('master.daerah-irigasi.show', $di) }}" class="btn-ghost">Batal</a></div>
</form>
@if(! $baru)
    <form method="POST" action="{{ route('master.sub.destroy', $sub) }}" class="mt-8 border-t border-savana-line pt-4" onsubmit="return confirm('Hapus Sub-D.I. ini?')">@csrf @method('DELETE')<button class="text-sm font-medium text-status-kritis">Hapus Sub-D.I. ini</button></form>
@endif
</div>
</x-layouts.app>
