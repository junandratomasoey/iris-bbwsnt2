@php $baru = ! $kejadian->exists; @endphp
<x-layouts.app :title="$baru ? 'Laporkan kejadian' : 'Ubah kejadian'">
<div class="mx-auto max-w-3xl">
<x-judul :judul="$baru ? 'Laporkan kejadian kekeringan' : 'Ubah kejadian'" sub="Untuk gagal tanam, gagal panen, atau kerusakan jaringan yang perlu ditangani."/>
<form method="POST" enctype="multipart/form-data" action="{{ $baru ? route('kejadian.store') : route('kejadian.update', $kejadian) }}" class="space-y-5"
      x-data="{ di: '{{ old('daerah_irigasi_id', $kejadian->daerah_irigasi_id) }}', subs: @js($subList) }">
    @csrf @if(! $baru) @method('PUT') @endif
    <fieldset class="panel grid gap-4 p-5 sm:grid-cols-2">
        <x-field name="tanggal_lapor" label="Tanggal" wajib><input id="tanggal_lapor" type="date" name="tanggal_lapor" value="{{ old('tanggal_lapor', optional($kejadian->tanggal_lapor)->toDateString()) }}" max="{{ today()->toDateString() }}" required class="input"></x-field>
        <x-field name="jenis_kegagalan" label="Jenis kejadian" wajib><select id="jenis_kegagalan" name="jenis_kegagalan" required class="input">@foreach(App\Models\Kejadian::JENIS as $j)<option @selected(old('jenis_kegagalan', $kejadian->jenis_kegagalan) === $j)>{{ $j }}</option>@endforeach</select></x-field>
        <x-field name="kabupaten_id" label="Kabupaten" wajib><select id="kabupaten_id" name="kabupaten_id" required class="input"><option value="">Pilih</option>@foreach($kabupatenList as $k)<option value="{{ $k->id }}" @selected(old('kabupaten_id', $kejadian->kabupaten_id) == $k->id)>{{ $k->nama }}</option>@endforeach</select></x-field>
        <x-field name="kecamatan" label="Kecamatan"><input id="kecamatan" name="kecamatan" value="{{ old('kecamatan', $kejadian->kecamatan) }}" class="input"></x-field>
        <x-field name="desa" label="Desa/kelurahan"><input id="desa" name="desa" value="{{ old('desa', $kejadian->desa) }}" class="input"></x-field>
        <x-field name="daerah_irigasi_id" label="D.I. (bila di dalam D.I.)"><select id="daerah_irigasi_id" name="daerah_irigasi_id" x-model="di" class="input"><option value="">Di luar D.I. / belum tahu</option>@foreach($diList as $d)<option value="{{ $d->id }}">{{ $d->nama }}</option>@endforeach</select></x-field>
        <x-field name="sub_daerah_irigasi_id" label="Sub-D.I." class="sm:col-span-2" x-show="di">
            <select id="sub_daerah_irigasi_id" name="sub_daerah_irigasi_id" class="input"><option value="">–</option>
                <template x-for="s in subs.filter(s => s.daerah_irigasi_id == di)" :key="s.id"><option :value="s.id" x-text="s.nama" :selected="s.id == {{ (int) old('sub_daerah_irigasi_id', $kejadian->sub_daerah_irigasi_id) }}"></option></template>
            </select>
        </x-field>
    </fieldset>
    <fieldset class="panel grid gap-4 p-5 sm:grid-cols-2">
        <x-field name="status_kegagalan" label="Status" wajib><select id="status_kegagalan" name="status_kegagalan" class="input">@foreach(['Potensi', 'Terjadi'] as $s)<option @selected(old('status_kegagalan', $kejadian->status_kegagalan) === $s)>{{ $s }}</option>@endforeach</select></x-field>
        <x-field name="luas_terdampak_ha" label="Luas terdampak (Ha)" wajib><input id="luas_terdampak_ha" type="number" step="0.01" min="0" inputmode="decimal" name="luas_terdampak_ha" value="{{ old('luas_terdampak_ha', $kejadian->luas_terdampak_ha) }}" required class="input"></x-field>
        <x-field name="penyebab" label="Penyebab" class="sm:col-span-2"><input id="penyebab" name="penyebab" value="{{ old('penyebab', $kejadian->penyebab) }}" list="daftar-penyebab" class="input"><datalist id="daftar-penyebab"><option>Kekeringan sesuai pola tanam</option><option>Kerusakan jaringan irigasi</option><option>Debit sumber air menurun</option><option>Bencana alam (gempa/banjir)</option></datalist></x-field>
        <x-field name="sumber_air" label="Sumber air utama & alternatif terdekat" class="sm:col-span-2"><textarea id="sumber_air" name="sumber_air" rows="2" class="input" placeholder="Mis. Bendung X; alternatif sungai Y ±300 m">{{ old('sumber_air', $kejadian->sumber_air) }}</textarea></x-field>
        <x-field name="solusi" label="Usulan penanganan" class="sm:col-span-2"><textarea id="solusi" name="solusi" rows="2" class="input">{{ old('solusi', $kejadian->solusi) }}</textarea></x-field>
        <x-field name="p3a" label="P3A/Poktan"><input id="p3a" name="p3a" value="{{ old('p3a', $kejadian->p3a) }}" class="input"></x-field>
        <x-field name="pelapor_nama" label="Nama pelapor" hint="Kosongkan bila Anda sendiri pelapornya."><input id="pelapor_nama" name="pelapor_nama" value="{{ old('pelapor_nama', $kejadian->pelapor_nama) }}" class="input"></x-field>
        <x-field name="pelapor_hp" label="No. HP pelapor"><input id="pelapor_hp" name="pelapor_hp" inputmode="tel" value="{{ old('pelapor_hp', $kejadian->pelapor_hp) }}" class="input"></x-field>
        <x-field name="catatan" label="Catatan" class="sm:col-span-2"><textarea id="catatan" name="catatan" rows="2" class="input">{{ old('catatan', $kejadian->catatan) }}</textarea></x-field>
    </fieldset>
    <fieldset class="panel space-y-3 p-5" x-data="pickLocation({ lat: '{{ old('lat', $kejadian->lat) }}', lng: '{{ old('lng', $kejadian->lng) }}' })">
        <legend class="float-left font-gauge text-lg font-semibold">Titik lokasi</legend>
        <p class="clear-left text-sm text-basalt-soft">Ketuk peta untuk menandai lokasi, atau pakai GPS bila Anda sedang di lokasi.</p>
        <div x-ref="peta" class="h-64 rounded border border-savana-line"></div>
        <div class="flex flex-wrap items-end gap-2">
            <label><span class="label">Lintang</span><input name="lat" x-model="lat" class="input w-36"></label>
            <label><span class="label">Bujur</span><input name="lng" x-model="lng" class="input w-36"></label>
            <button type="button" class="btn-ghost" @click="gpsSaya"><x-ikon n="lokasi" class="h-4 w-4"/> Pakai GPS</button>
        </div>
        @error('lat')<p class="text-xs font-medium text-status-kritis">{{ $message }}</p>@enderror
    </fieldset>
    <fieldset class="panel space-y-3 p-5">
        <legend class="float-left font-gauge text-lg font-semibold">Foto dokumentasi</legend>
        @if(! $baru && $kejadian->foto->isNotEmpty())
            <div class="clear-left">
                <p class="mb-2 text-sm text-basalt-soft">Foto yang sudah ada ({{ $kejadian->foto->count() }}). Hapus dari halaman detail kejadian.</p>
                <x-galeri-foto :foto="$kejadian->foto"/>
            </div>
        @endif
        @php $sisaFoto = \App\Http\Controllers\KejadianController::MAKS_FOTO - ($baru ? 0 : $kejadian->foto->count()); @endphp
        @if($sisaFoto > 0)
            <div class="clear-left flex flex-wrap gap-2" role="radiogroup" aria-label="Jenis foto baru">
                @foreach(\App\Models\Foto::LABEL as $k => $l)
                    <label class="cursor-pointer"><input type="radio" name="jenis_foto" value="{{ $k }}" @checked(old('jenis_foto', $baru ? 'sebelum' : 'lainnya') === $k) class="peer sr-only">
                        <span class="block rounded-md border border-savana-line px-3 py-1.5 text-sm peer-checked:border-saluran-600 peer-checked:bg-saluran-600 peer-checked:text-white">{{ $l }}</span></label>
                @endforeach
            </div>
            <x-unggah-foto :maks="min(5, $sisaFoto)" class="clear-left"/>
        @endif
    </fieldset>
    <div class="flex gap-2"><button class="btn-primary px-8">{{ $baru ? 'Kirim laporan kejadian' : 'Simpan perubahan' }}</button><a href="{{ $baru ? route('kejadian.index') : route('kejadian.show', $kejadian) }}" class="btn-ghost">Batal</a></div>
</form>
</div>
</x-layouts.app>
