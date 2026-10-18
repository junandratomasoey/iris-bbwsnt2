@php use App\Models\Bangunan; $baru = ! $bangunan->exists; $diAwal = $diList->firstWhere('id', old('daerah_irigasi_id', $bangunan->daerah_irigasi_id)); @endphp
<x-layouts.app :title="$baru ? 'Tambah infrastruktur' : 'Ubah '.$bangunan->nama">
<div class="mx-auto max-w-3xl">
<x-judul :judul="$baru ? 'Tambah infrastruktur' : $bangunan->nama" :sub="$baru ? 'Bangunan atau ruas saluran dalam D.I.' : $bangunan->jenis_label.' · D.I. '.$bangunan->daerahIrigasi?->nama"/>

<form method="POST" enctype="multipart/form-data" action="{{ $baru ? route('master.bangunan.store') : route('master.bangunan.update', $bangunan) }}" class="space-y-5"
      x-data="{ di: '{{ old('daerah_irigasi_id', $bangunan->daerah_irigasi_id) }}', sub: '{{ old('sub_daerah_irigasi_id', $bangunan->sub_daerah_irigasi_id) }}', subs: @js($subList), kirim: false, tglPer: '{{ old('kondisi_per', $bangunan->kondisi_per?->toDateString()) }}' }" @submit="kirim = true">
    @csrf @if(! $baru) @method('PUT') @endif
    <fieldset class="panel grid gap-4 p-5 sm:grid-cols-2">
        <x-field name="nama" label="Nama" wajib class="sm:col-span-2"><input id="nama" name="nama" value="{{ old('nama', $bangunan->nama) }}" required class="input" placeholder="mis. Bendung Manikin, Saluran Sekunder Oesao"></x-field>
        <x-field name="jenis" label="Jenis" wajib><select id="jenis" name="jenis" class="input">@foreach(Bangunan::JENIS as $k => $l)<option value="{{ $k }}" @selected(old('jenis', $bangunan->jenis) === $k)>{{ $l }}</option>@endforeach</select></x-field>
        <x-field name="lokasi" label="Lokasi / desa"><input id="lokasi" name="lokasi" value="{{ old('lokasi', $bangunan->lokasi) }}" class="input"></x-field>
        <x-field name="daerah_irigasi_id" label="D.I." wajib><select id="daerah_irigasi_id" name="daerah_irigasi_id" x-model="di" @change="sub = ''" required class="input"><option value="">Pilih D.I.</option>@foreach($diList as $d)<option value="{{ $d->id }}">{{ $d->nama }}</option>@endforeach</select></x-field>
        <x-field name="sub_daerah_irigasi_id" label="Sub-D.I." hint="Kosongkan bila melayani seluruh D.I."><select id="sub_daerah_irigasi_id" name="sub_daerah_irigasi_id" x-model="sub" class="input"><option value="">–</option>
            <template x-for="s in subs.filter((s) => s.daerah_irigasi_id == di)" :key="s.id"><option :value="s.id" x-text="s.nama" :selected="s.id == sub"></option></template></select></x-field>
    </fieldset>

    <fieldset class="panel space-y-3 p-5">
        <legend class="float-left font-gauge text-lg font-semibold">Kondisi fisik</legend>
        <div class="clear-left flex flex-wrap gap-2" role="radiogroup" aria-label="Kondisi">
            @foreach(['' => 'Belum dinilai'] + Bangunan::KONDISI as $k => $l)
                <label class="cursor-pointer"><input type="radio" name="kondisi" value="{{ $k }}" @checked((string) old('kondisi', $bangunan->kondisi) === (string) $k) class="peer sr-only" @change="tglPer = '{{ today()->toDateString() }}'">
                    <span class="flex items-center gap-1.5 rounded-md border border-savana-line px-3 py-1.5 text-sm peer-checked:border-basalt peer-checked:bg-basalt peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-saluran-500">
                        <span class="h-2.5 w-2.5 rounded-full" style="background: {{ Bangunan::WARNA_KONDISI[$k] ?? '#9AA4A8' }}"></span>{{ $l }}</span></label>
            @endforeach
        </div>
        @error('kondisi')<p class="text-xs font-medium text-status-kritis">{{ $message }}</p>@enderror
        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="kondisi_per" label="Tanggal pemeriksaan" hint="Otomatis hari ini saat kondisi diubah; sesuaikan bila pemeriksaan di hari lain."><input id="kondisi_per" type="date" name="kondisi_per" max="{{ today()->toDateString() }}" x-model="tglPer" class="input"></x-field>
            <x-field name="keterangan" label="Catatan kerusakan / keterangan" class="sm:col-span-2"><textarea id="keterangan" name="keterangan" rows="2" class="input">{{ old('keterangan', $bangunan->keterangan) }}</textarea></x-field>
        </div>
    </fieldset>

    <fieldset class="panel space-y-3 p-5" x-data="pickLocation({ lat: '{{ old('lat', $bangunan->lat) }}', lng: '{{ old('lng', $bangunan->lng) }}', pusat: {{ $diAwal?->lat ? '['.$diAwal->lat.','.$diAwal->lng.']' : 'null' }} })">
        <legend class="float-left font-gauge text-lg font-semibold">Titik lokasi</legend>
        <p class="clear-left text-sm text-basalt-soft">Ketuk peta citra di lokasi bangunan, atau pakai GPS saat berada di lapangan.</p>
        <div x-ref="peta" class="h-72 rounded border border-savana-line"></div>
        <div class="flex flex-wrap items-end gap-2"><label><span class="label">Lintang</span><input name="lat" x-model="lat" class="input w-36"></label><label><span class="label">Bujur</span><input name="lng" x-model="lng" class="input w-36"></label><button type="button" class="btn-ghost" @click="gpsSaya"><x-ikon n="lokasi" class="h-4 w-4"/> GPS</button></div>
        @error('lat')<p class="text-xs font-medium text-status-kritis">{{ $message }}</p>@enderror
        @error('lng')<p class="text-xs font-medium text-status-kritis">{{ $message }}</p>@enderror
    </fieldset>

    <fieldset class="panel space-y-3 p-5">
        <legend class="float-left font-gauge text-lg font-semibold">Foto kondisi <span class="text-sm font-normal text-basalt-mute">{{ $bangunan->exists ? $bangunan->foto->count() : 0 }}/{{ Bangunan::MAKS_FOTO }}</span></legend>
        <p class="clear-left text-sm text-basalt-soft">Tanggal foto tercatat otomatis, jadi foto baru dan lama bisa dibandingkan.</p>
        @if(! $baru && $bangunan->foto->isNotEmpty())<p class="text-sm text-basalt-soft">Foto tersimpan ada di bawah formulir.</p>@endif
        @if(($bangunan->exists ? $bangunan->foto->count() : 0) < Bangunan::MAKS_FOTO)
            <x-unggah-foto :maks="min(5, Bangunan::MAKS_FOTO - ($bangunan->exists ? $bangunan->foto->count() : 0))"/>
        @endif
    </fieldset>

    <div class="flex gap-2"><button class="btn-primary px-8" :disabled="kirim" x-text="kirim ? 'Menyimpan…' : 'Simpan'"></button><a href="{{ route('master.bangunan.index', ['di' => $bangunan->daerah_irigasi_id]) }}" class="btn-ghost">Kembali</a></div>
</form>
@if(! $baru && $bangunan->foto->isNotEmpty())
    <section class="panel mt-5 p-5">
        <h2 class="mb-3 text-lg font-semibold">Foto tersimpan</h2>
        <x-galeri-foto :foto="$bangunan->foto" :hapus-route="fn ($f) => route('master.bangunan.foto.destroy', [$bangunan, $f])"/>
    </section>
@endif
@if(! $baru)
    <form method="POST" action="{{ route('master.bangunan.destroy', $bangunan) }}" class="mt-8 border-t border-savana-line pt-4" onsubmit="return confirm('Hapus infrastruktur ini beserta fotonya?')">@csrf @method('DELETE')<button class="text-sm font-medium text-status-kritis">Hapus infrastruktur ini</button></form>
@endif
</div>
</x-layouts.app>
