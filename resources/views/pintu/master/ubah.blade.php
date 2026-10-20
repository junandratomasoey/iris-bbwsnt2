@php use App\Models\PintuAir; @endphp
<x-layouts.app title="Ubah pintu {{ $p->kode }}">
<x-judul judul="Ubah pintu {{ $p->kode }}" sub="D.I. {{ $p->daerahIrigasi->nama }}"/>

<form method="POST" action="{{ route('master.pintu-air.update', $p) }}" class="panel grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4" x-data="{ kirim: false }" @submit="kirim = true">
    @csrf @method('PUT')
    <x-field name="kode" label="Kode" wajib><input id="kode" name="kode" value="{{ old('kode', $p->kode) }}" class="input" maxlength="30" required></x-field>
    <x-field name="nama" label="Nama / petak" class="lg:col-span-2"><input id="nama" name="nama" value="{{ old('nama', $p->nama) }}" class="input" maxlength="255"></x-field>
    <x-field name="jenis" label="Jenis"><select id="jenis" name="jenis" class="input">@foreach(PintuAir::JENIS as $k => $l)<option value="{{ $k }}" @selected(old('jenis', $p->jenis) === $k)>{{ $l }}</option>@endforeach</select></x-field>
    <x-field name="tingkat" label="Tingkat jaringan" wajib><select id="tingkat" name="tingkat" class="input">@foreach(PintuAir::TINGKAT as $k => $l)<option value="{{ $k }}" @selected(old('tingkat', $p->tingkat) === $k)>{{ $l }}</option>@endforeach</select></x-field>
    <x-field name="hulu_id" label="Menerima air dari (pintu hulu)" class="lg:col-span-2" hint="Untuk mencocokkan debit hulu dengan jumlah debit pintu di bawahnya.">
        <select id="hulu_id" name="hulu_id" class="input"><option value="">— (paling hulu)</option>@foreach($huluList as $h)<option value="{{ $h->id }}" @selected(old('hulu_id', $p->hulu_id) == $h->id)>{{ $h->kode }}{{ $h->nama ? ' · '.$h->nama : '' }} ({{ $h->tingkat_label }})</option>@endforeach</select>
    </x-field>
    <x-field name="sub_daerah_irigasi_id" label="Sub-D.I."><select id="sub_daerah_irigasi_id" name="sub_daerah_irigasi_id" class="input"><option value="">Tingkat D.I.</option>@foreach($subList as $s)<option value="{{ $s->id }}" @selected(old('sub_daerah_irigasi_id', $p->sub_daerah_irigasi_id) == $s->id)>{{ $s->nama }}</option>@endforeach</select></x-field>
    <x-field name="bangunan_id" label="Di bangunan" hint="Opsional, dari daftar infrastruktur."><select id="bangunan_id" name="bangunan_id" class="input"><option value="">—</option>@foreach($bangunanList as $b)<option value="{{ $b->id }}" @selected(old('bangunan_id', $p->bangunan_id) == $b->id)>{{ $b->nama }}</option>@endforeach</select></x-field>
    <x-field name="saluran" label="Saluran yang dialiri" class="lg:col-span-2"><input id="saluran" name="saluran" value="{{ old('saluran', $p->saluran) }}" class="input" maxlength="255"></x-field>
    <x-field name="luas_layanan_ha" label="Luas layanan (Ha)"><input id="luas_layanan_ha" type="number" step="any" min="0" name="luas_layanan_ha" value="{{ old('luas_layanan_ha', $p->luas_layanan_ha) }}" class="input"></x-field>
    <x-field name="debit_min_lps" label="Debit normal minimum (L/dt)"><input id="debit_min_lps" type="number" step="any" min="0" name="debit_min_lps" value="{{ old('debit_min_lps', $p->debit_min_lps) }}" class="input"></x-field>
    <x-field name="debit_maks_lps" label="Debit normal maksimum (L/dt)"><input id="debit_maks_lps" type="number" step="any" min="0" name="debit_maks_lps" value="{{ old('debit_maks_lps', $p->debit_maks_lps) }}" class="input"></x-field>
    <x-field name="urutan" label="Urutan tampil" hint="Urutan dari hulu ke hilir."><input id="urutan" type="number" min="0" name="urutan" value="{{ old('urutan', $p->urutan) }}" class="input"></x-field>
    <x-field name="tma_min_cm" label="TMA normal minimum (cm)"><input id="tma_min_cm" type="number" step="any" min="0" name="tma_min_cm" value="{{ old('tma_min_cm', $p->tma_min_cm) }}" class="input"></x-field>
    <x-field name="tma_maks_cm" label="TMA normal maksimum (cm)"><input id="tma_maks_cm" type="number" step="any" min="0" name="tma_maks_cm" value="{{ old('tma_maks_cm', $p->tma_maks_cm) }}" class="input"></x-field>
    <div class="rounded-md border border-savana-line p-3 sm:col-span-2 lg:col-span-4">
        <p class="mb-2 text-sm font-semibold">Petugas yang membaca pintu ini</p>
        <div class="grid gap-3 sm:grid-cols-2">
            <x-field name="tugas_utama" label="Penanggung jawab"><select id="tugas_utama" name="tugas_utama" class="input"><option value="">— Belum ditugaskan —</option>@foreach($petugasList as $u)<option value="{{ $u->id }}" @selected(old('tugas_utama', $tugas['utama'] ?? null) == $u->id)>{{ $u->name }} ({{ $u->jabatan ?: $u->roleLabel() }})</option>@endforeach</select></x-field>
            <x-field name="tugas_pengganti" label="Pengganti (opsional)"><select id="tugas_pengganti" name="tugas_pengganti" class="input"><option value="">—</option>@foreach($petugasList as $u)<option value="{{ $u->id }}" @selected(old('tugas_pengganti', $tugas['pengganti'] ?? null) == $u->id)>{{ $u->name }} ({{ $u->jabatan ?: $u->roleLabel() }})</option>@endforeach</select></x-field>
        </div>
        <p class="mt-1 text-xs text-basalt-mute">Bila sudah ditugaskan, hanya penanggung jawab, pengganti, dan pengelola pintu yang bisa mengisi pembacaan pintu ini. Penugasan semua pintu sekaligus: <a href="{{ route('master.pintu-air.penugasan', ['di' => $p->daerah_irigasi_id]) }}" class="text-saluran-600">Atur penugasan</a>.</p>
    </div>
    <div class="sm:col-span-2 lg:col-span-4 text-sm text-basalt-soft">
        <span class="font-semibold text-basalt">Titik pintu.</span>
        @if($p->lat)
            {{ number_format($p->lat, 6) }}, {{ number_format($p->lng, 6) }}@if($p->titik_pada) — diambil {{ $p->titikOleh?->name ? 'oleh '.$p->titikOleh->name : '' }} {{ $p->titik_pada->translatedFormat('d M Y H:i') }}@if($p->titik_akurasi_m) (akurasi ±{{ $p->titik_akurasi_m }} m)@endif @endif
        @else
            Belum ada. Titik diambil petugas dengan GPS saat membaca pintu di lapangan, atau diisi manual di bawah.
        @endif
    </div>
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
