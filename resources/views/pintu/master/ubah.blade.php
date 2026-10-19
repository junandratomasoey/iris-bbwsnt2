@php use App\Models\PintuAir; @endphp
<x-layouts.app title="Ubah pintu {{ $p->kode }}">
<x-judul judul="Ubah pintu {{ $p->kode }}" sub="D.I. {{ $p->daerahIrigasi->nama }}"/>

<form method="POST" action="{{ route('master.pintu-air.update', $p) }}" class="panel grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4" x-data="{ kirim: false }" @submit="kirim = true">
    @csrf @method('PUT')
    <x-field name="kode" label="Kode" wajib><input id="kode" name="kode" value="{{ old('kode', $p->kode) }}" class="input" maxlength="30" required></x-field>
    <x-field name="nama" label="Nama / petak" class="lg:col-span-2"><input id="nama" name="nama" value="{{ old('nama', $p->nama) }}" class="input" maxlength="255"></x-field>
    <x-field name="jenis" label="Jenis"><select id="jenis" name="jenis" class="input">@foreach(PintuAir::JENIS as $k => $l)<option value="{{ $k }}" @selected(old('jenis', $p->jenis) === $k)>{{ $l }}</option>@endforeach</select></x-field>
    <x-field name="sub_daerah_irigasi_id" label="Sub-D.I."><select id="sub_daerah_irigasi_id" name="sub_daerah_irigasi_id" class="input"><option value="">Tingkat D.I.</option>@foreach($subList as $s)<option value="{{ $s->id }}" @selected(old('sub_daerah_irigasi_id', $p->sub_daerah_irigasi_id) == $s->id)>{{ $s->nama }}</option>@endforeach</select></x-field>
    <x-field name="bangunan_id" label="Di bangunan" hint="Opsional, dari daftar infrastruktur."><select id="bangunan_id" name="bangunan_id" class="input"><option value="">—</option>@foreach($bangunanList as $b)<option value="{{ $b->id }}" @selected(old('bangunan_id', $p->bangunan_id) == $b->id)>{{ $b->nama }}</option>@endforeach</select></x-field>
    <x-field name="saluran" label="Saluran yang dialiri" class="lg:col-span-2"><input id="saluran" name="saluran" value="{{ old('saluran', $p->saluran) }}" class="input" maxlength="255"></x-field>
    <x-field name="luas_layanan_ha" label="Luas layanan (Ha)"><input id="luas_layanan_ha" type="number" step="any" min="0" name="luas_layanan_ha" value="{{ old('luas_layanan_ha', $p->luas_layanan_ha) }}" class="input"></x-field>
    <x-field name="debit_min_lps" label="Debit normal minimum (L/dt)"><input id="debit_min_lps" type="number" step="any" min="0" name="debit_min_lps" value="{{ old('debit_min_lps', $p->debit_min_lps) }}" class="input"></x-field>
    <x-field name="debit_maks_lps" label="Debit normal maksimum (L/dt)"><input id="debit_maks_lps" type="number" step="any" min="0" name="debit_maks_lps" value="{{ old('debit_maks_lps', $p->debit_maks_lps) }}" class="input"></x-field>
    <x-field name="urutan" label="Urutan tampil" hint="Urutan dari hulu ke hilir."><input id="urutan" type="number" min="0" name="urutan" value="{{ old('urutan', $p->urutan) }}" class="input"></x-field>
    <x-field name="tma_min_cm" label="TMA normal minimum (cm)"><input id="tma_min_cm" type="number" step="any" min="0" name="tma_min_cm" value="{{ old('tma_min_cm', $p->tma_min_cm) }}" class="input"></x-field>
    <x-field name="tma_maks_cm" label="TMA normal maksimum (cm)"><input id="tma_maks_cm" type="number" step="any" min="0" name="tma_maks_cm" value="{{ old('tma_maks_cm', $p->tma_maks_cm) }}" class="input"></x-field>
    <x-field name="lat" label="Lintang (lat)"><input id="lat" name="lat" value="{{ old('lat', $p->lat) }}" class="input" inputmode="decimal" placeholder="-10.1"></x-field>
    <x-field name="lng" label="Bujur (lng)"><input id="lng" name="lng" value="{{ old('lng', $p->lng) }}" class="input" inputmode="decimal" placeholder="123.7"></x-field>
    <x-field name="keterangan" label="Keterangan" class="sm:col-span-2 lg:col-span-4"><textarea id="keterangan" name="keterangan" rows="2" class="input">{{ old('keterangan', $p->keterangan) }}</textarea></x-field>
    <label class="flex items-center gap-2 text-sm sm:col-span-2 lg:col-span-4"><input type="hidden" name="aktif" value="0"><input type="checkbox" name="aktif" value="1" @checked(old('aktif', $p->aktif)) class="rounded border-savana-line text-saluran-600"> Aktif (muncul di form pembacaan)</label>
    <div class="flex flex-wrap gap-2 sm:col-span-2 lg:col-span-4">
        <button class="btn-primary px-8" :disabled="kirim" x-text="kirim ? 'Menyimpan…' : 'Simpan'"></button>
        <a href="{{ route('pintu.show', $p) }}" class="btn-ghost">Batal</a>
    </div>
</form>
<form method="POST" action="{{ route('master.pintu-air.destroy', $p) }}" class="mt-4" onsubmit="return confirm('{{ $jumlahBacaan ? 'Pintu ini punya '.$jumlahBacaan.' pembacaan, jadi akan dinonaktifkan. Lanjutkan?' : 'Hapus pintu ini?' }}')">
    @csrf @method('DELETE')
    <button class="text-sm font-medium text-status-kritis">{{ $jumlahBacaan ? 'Nonaktifkan pintu' : 'Hapus pintu' }}</button>
</form>
</x-layouts.app>
