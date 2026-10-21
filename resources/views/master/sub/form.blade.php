@php
    $baru = ! $sub->exists;
    $pusat = $sub->lat ? [$sub->lat, $sub->lng] : ($di->lat ? [$di->lat, $di->lng] : null);
    $maksFoto = \App\Models\SubDaerahIrigasi::MAKS_FOTO_PROFIL;
@endphp
<x-layouts.app :title="$baru ? 'Tambah Sub-D.I.' : 'Ubah '.$sub->nama">
<div class="mx-auto max-w-3xl">
<x-judul :judul="$baru ? 'Tambah Sub-D.I.' : 'Ubah Sub-D.I. '.$sub->nama" :sub="'D.I. '.$di->nama.' · data di halaman ini tampil di peta publik (kecuali petugas)'"/>
<form method="POST" action="{{ $baru ? route('master.daerah-irigasi.sub.store', $di) : route('master.sub.update', $sub) }}" class="space-y-5">
    @csrf @if(! $baru) @method('PUT') @endif
    <fieldset class="panel grid gap-4 p-5 sm:grid-cols-2">
        <legend class="sr-only">Data teknis</legend>
        <x-field name="nama" label="Nama Sub-D.I." wajib><input id="nama" name="nama" value="{{ old('nama', $sub->nama) }}" required class="input"></x-field>
        <x-field name="kabupaten_id" label="Kabupaten"><select id="kabupaten_id" name="kabupaten_id" class="input"><option value="">–</option>@foreach($kabupatenList as $k)<option value="{{ $k->id }}" @selected(old('kabupaten_id', $sub->kabupaten_id) == $k->id)>{{ $k->nama }}</option>@endforeach</select></x-field>
        <x-field name="luas_ha" label="Luas baku (Ha)"><input id="luas_ha" name="luas_ha" type="number" step="0.01" min="0" value="{{ old('luas_ha', $sub->luas_ha) }}" class="input"></x-field>
        <x-field name="luas_fungsional_ha" label="Luas fungsional (Ha)" hint="Areal yang benar-benar bisa diairi saat ini."><input id="luas_fungsional_ha" name="luas_fungsional_ha" type="number" step="0.01" min="0" value="{{ old('luas_fungsional_ha', $sub->luas_fungsional_ha) }}" class="input"></x-field>
        <x-field name="kecamatan" label="Kecamatan"><input id="kecamatan" name="kecamatan" value="{{ old('kecamatan', $sub->kecamatan) }}" class="input"></x-field>
        <x-field name="lokasi" label="Lokasi / desa utama"><input id="lokasi" name="lokasi" value="{{ old('lokasi', $sub->lokasi) }}" class="input"></x-field>
        <x-field name="desa" label="Desa yang dilayani" hint="Pisahkan dengan koma." class="sm:col-span-2"><input id="desa" name="desa" value="{{ old('desa', $sub->desa) }}" maxlength="500" class="input" placeholder="mis. Tarus, Noelbaki, Penfui Timur"></x-field>
        <x-field name="saluran" label="Saluran yang melayani" hint="Nama saluran sekunder/induk."><input id="saluran" name="saluran" value="{{ old('saluran', $sub->saluran) }}" class="input"></x-field>
        <x-field name="panjang_saluran_m" label="Panjang saluran (m)"><input id="panjang_saluran_m" name="panjang_saluran_m" type="number" step="0.1" min="0" value="{{ old('panjang_saluran_m', $sub->panjang_saluran_m) }}" class="input"></x-field>
        <x-field name="keterangan" label="Keterangan untuk peta" hint="Tampil di peta publik: gambaran singkat Sub-D.I., komoditas utama, kendala yang sering terjadi." class="sm:col-span-2"><textarea id="keterangan" name="keterangan" rows="3" class="input">{{ old('keterangan', $sub->keterangan) }}</textarea></x-field>
    </fieldset>

    <fieldset class="panel space-y-3 p-5" x-data="pickLocation({ lat: '{{ old('lat', $sub->lat) }}', lng: '{{ old('lng', $sub->lng) }}', pusat: @js($di->lat ? [$di->lat, $di->lng] : null) })">
        <legend class="float-left font-gauge text-lg font-semibold">Titik Sub-D.I.</legend>
        <p class="clear-left text-sm text-basalt-soft">Ketuk peta di bangunan pengambilan atau pusat areal Sub-D.I. Bila dikosongkan dan batas areal digambar, titik tengah batas dipakai. Peta publik mengarah ke titik/batas ini saat Sub-D.I. dipilih.</p>
        <div x-ref="peta" class="h-64 rounded border border-savana-line"></div>
        <div class="flex flex-wrap items-end gap-2"><label><span class="label">Lintang</span><input name="lat" x-model="lat" class="input w-36"></label><label><span class="label">Bujur</span><input name="lng" x-model="lng" class="input w-36"></label><button type="button" class="btn-ghost" @click="gpsSaya"><x-ikon n="lokasi" class="h-4 w-4"/> GPS</button></div>
        @error('lat')<p class="text-xs font-medium text-status-kritis">{{ $message }}</p>@enderror
    </fieldset>

    <fieldset class="panel space-y-3 p-5" x-data="petaEditor({ geometri: @js($sub->geometri), id: null, pusat: @js($pusat), acuanUrl: '{{ asset('geojson/fungsional.geojson') }}?v={{ \App\Support\GeojsonVersi::get() }}' })"
              @galat-peta.window="alert($event.detail)">
        <legend class="float-left font-gauge text-lg font-semibold">Batas areal Sub-D.I. <span class="text-sm font-normal text-basalt-mute">(opsional)</span></legend>
        <p class="clear-left text-sm text-basalt-soft">Gambar poligon areal Sub-D.I. di atas citra satelit (tombol kiri atas peta), atau muat file GeoJSON. Garis putih = areal fungsional D.I. sebagai acuan.</p>
        <input type="hidden" name="geometri" :value="geometri">
        <div x-ref="peta" class="h-80 rounded border border-savana-line"></div>
        <div class="flex flex-wrap items-center gap-3 text-sm">
            <span class="text-basalt-soft">Luas digambar: <b class="text-basalt" x-text="luasHa.toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' Ha'"></b> · <span x-text="jumlahBagian + ' bagian'"></span></span>
            <label class="btn-ghost cursor-pointer py-1">Muat GeoJSON<input type="file" accept=".geojson,.json,application/geo+json,application/json" class="sr-only" @change="muatBerkas"></label>
            <button type="button" class="text-xs font-medium text-status-kritis" @click="if (confirm('Hapus batas areal Sub-D.I.?')) hapusSemua()">Kosongkan</button>
        </div>
        @error('geometri')<p class="text-xs font-medium text-status-kritis">{{ $message }}</p>@enderror
    </fieldset>
    <div class="flex gap-2"><button class="btn-primary px-8">Simpan</button><a href="{{ route('master.daerah-irigasi.show', $di) }}" class="btn-ghost">Batal</a></div>
</form>

@if(! $baru)
    <section class="panel mt-5 p-5">
        <h2 class="text-lg font-semibold">Foto Sub-D.I. <span class="text-sm font-normal text-basalt-mute">{{ $sub->fotoProfil->count() }}/{{ $maksFoto }}</span></h2>
        <p class="text-sm text-basalt-soft">Foto areal sawah, bangunan pengambilan, atau saluran yang mewakili Sub-D.I. Tampil di peta publik saat Sub-D.I. dipilih, bersama foto laporan PPA terbaru.</p>
        @if($sub->fotoProfil->isNotEmpty())
            <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach($sub->fotoProfil as $f)
                    <figure>
                        <img src="{{ $f->url_kecil }}" alt="{{ $f->judul ?? 'Foto Sub-D.I.' }}" class="aspect-[4/3] w-full rounded object-cover">
                        <figcaption class="mt-1 text-[11px] text-basalt-soft">{{ $f->judul }}{{ $f->diambil_pada ? ' · '.$f->diambil_pada->translatedFormat('d M Y') : '' }}</figcaption>
                        <form method="POST" action="{{ route('master.sub.foto.destroy', [$sub, $f]) }}" onsubmit="return confirm('Hapus foto ini?')">@csrf @method('DELETE')<button class="text-xs font-medium text-status-kritis">Hapus</button></form>
                    </figure>
                @endforeach
            </div>
        @endif
        @if($sub->fotoProfil->count() < $maksFoto)
            <form method="POST" action="{{ route('master.sub.foto.store', $sub) }}" enctype="multipart/form-data" class="mt-4 grid gap-3 border-t border-savana-line pt-4 sm:grid-cols-2" x-data="{ kirim: false }" @submit="kirim = true">
                @csrf
                <div class="sm:col-span-2"><x-unggah-foto :maks="$maksFoto - $sub->fotoProfil->count()"/></div>
                <x-field name="judul" label="Judul / keterangan foto"><input id="judul" name="judul" maxlength="150" class="input" placeholder="mis. Areal sawah blok hilir"></x-field>
                <x-field name="diambil_pada" label="Tanggal foto"><input id="diambil_pada" type="date" name="diambil_pada" max="{{ today()->toDateString() }}" class="input"></x-field>
                <div><button class="btn-primary" :disabled="kirim" x-text="kirim ? 'Mengunggah…' : 'Unggah foto'"></button></div>
            </form>
        @endif
    </section>
    <form method="POST" action="{{ route('master.sub.destroy', $sub) }}" class="mt-8 border-t border-savana-line pt-4" onsubmit="return confirm('Hapus Sub-D.I. ini?')">@csrf @method('DELETE')<button class="text-sm font-medium text-status-kritis">Hapus Sub-D.I. ini</button></form>
@endif
</div>
</x-layouts.app>
