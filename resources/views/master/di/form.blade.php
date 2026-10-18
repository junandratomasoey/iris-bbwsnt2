@php $baru = ! $di->exists; @endphp
<x-layouts.app :title="$baru ? 'Tambah D.I.' : 'Ubah D.I. '.$di->nama">
<div class="mx-auto max-w-3xl">
<x-judul :judul="$baru ? 'Tambah Daerah Irigasi' : 'Ubah D.I. '.$di->nama"/>
<form method="POST" action="{{ $baru ? route('master.daerah-irigasi.store') : route('master.daerah-irigasi.update', $di) }}" class="space-y-5">
    @csrf @if(! $baru) @method('PUT') @endif
    <fieldset class="panel grid gap-4 p-5 sm:grid-cols-3">
        <x-field name="kode" label="Kode" wajib><input id="kode" name="kode" value="{{ old('kode', $di->kode) }}" required class="input"></x-field>
        <x-field name="nama" label="Nama D.I." class="sm:col-span-2" wajib><input id="nama" name="nama" value="{{ old('nama', $di->nama) }}" required class="input"></x-field>
        <x-field name="kabupaten_id" label="Kabupaten"><select id="kabupaten_id" name="kabupaten_id" class="input"><option value="">–</option>@foreach($kabupatenList as $k)<option value="{{ $k->id }}" @selected(old('kabupaten_id', $di->kabupaten_id) == $k->id)>{{ $k->nama }}</option>@endforeach</select></x-field>
        <x-field name="kewenangan" label="Kewenangan" wajib><input id="kewenangan" name="kewenangan" value="{{ old('kewenangan', $di->kewenangan) }}" required class="input"></x-field>
        <x-field name="keterangan" label="Keterangan"><input id="keterangan" name="keterangan" value="{{ old('keterangan', $di->keterangan) }}" placeholder="Lintas kabupaten / negara" class="input"></x-field>
        @foreach(['luas_permen_ha' => 'Luas Permen (Ha)', 'luas_baku_ha' => 'Luas baku (Ha)', 'luas_potensial_ha' => 'Luas potensial (Ha)', 'luas_fungsional_ha' => 'Luas fungsional (Ha)'] as $n => $l)
            <x-field :name="$n" :label="$l"><input id="{{ $n }}" name="{{ $n }}" type="number" step="0.01" value="{{ old($n, $di->$n) }}" class="input"></x-field>
        @endforeach
        <x-field name="sumber_air" label="Sumber air" class="sm:col-span-2"><input id="sumber_air" name="sumber_air" value="{{ old('sumber_air', $di->sumber_air) }}" class="input"></x-field>
    </fieldset>
    <fieldset class="panel space-y-3 p-5" x-data="pickLocation({ lat: '{{ old('lat', $di->lat) }}', lng: '{{ old('lng', $di->lng) }}' })">
        <legend class="float-left font-gauge text-lg font-semibold">Titik tengah D.I.</legend>
        <div x-ref="peta" class="clear-left h-64 rounded border border-savana-line"></div>
        <div class="flex gap-2"><label><span class="label">Lintang</span><input name="lat" x-model="lat" class="input w-36"></label><label><span class="label">Bujur</span><input name="lng" x-model="lng" class="input w-36"></label></div>
    </fieldset>
    <div class="flex flex-wrap gap-2"><button class="btn-primary px-8">Simpan</button><a href="{{ $baru ? route('master.daerah-irigasi.index') : route('master.daerah-irigasi.show', $di) }}" class="btn-ghost">Batal</a></div>
</form>
@if(! $baru)
    <form method="POST" action="{{ route('master.daerah-irigasi.destroy', $di) }}" class="mt-8 border-t border-savana-line pt-4" onsubmit="return confirm('Hapus D.I. ini beserta seluruh Sub-D.I.-nya?')">@csrf @method('DELETE')
        <button class="text-sm font-medium text-status-kritis">Hapus D.I. ini</button>
        <p class="text-xs text-basalt-mute">Hanya bisa bila belum ada laporan harian.</p></form>
@endif
</div>
</x-layouts.app>
