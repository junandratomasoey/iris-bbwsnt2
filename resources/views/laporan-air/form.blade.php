@php
    use App\Models\LaporanHarian as LH; use App\Models\LaporanAir;
    $baru = ! $laporan->exists;
    $diAwal = $diList->firstWhere('id', (int) old('daerah_irigasi_id', $laporan->daerah_irigasi_id));
    $sisaFoto = LaporanAir::MAKS_FOTO - ($baru ? 0 : $laporan->foto->count());
@endphp
<x-layouts.app :title="$baru ? 'Laporan air' : 'Ubah laporan air'">
<div class="mx-auto max-w-2xl">
<x-judul :judul="$baru ? 'Laporan air' : 'Ubah laporan air'" sub="Tugas Manager D.I.: kondisi air sungai, intake, dan saluran hari ini. Kolom bertanda * wajib diisi."/>

<form method="POST" enctype="multipart/form-data" action="{{ $baru ? route('laporan-air.store') : route('laporan-air.update', $laporan) }}"
      x-data="{ kirim: false, di: @js((string) old('daerah_irigasi_id', $laporan->daerah_irigasi_id)), intake: @js($intake),
                f: { debit_intake_lps: @js(old('debit_intake_lps', $laporan->debit_intake_lps)), tma_intake_cm: @js(old('tma_intake_cm', $laporan->tma_intake_cm)) },
                pakai(b) { if (b.debit !== null) this.f.debit_intake_lps = b.debit; if (b.tma !== null) this.f.tma_intake_cm = b.tma; } }"
      @submit="kirim = true" class="space-y-5">
    @csrf @if(! $baru) @method('PUT') @endif

    <fieldset class="panel space-y-4 p-4 sm:p-5">
        <legend class="sr-only">D.I. dan tanggal</legend>
        <div class="grid gap-4 sm:grid-cols-[1fr_11rem]">
            <x-field name="daerah_irigasi_id" label="D.I." wajib>
                <select id="daerah_irigasi_id" name="daerah_irigasi_id" x-model="di" required class="input py-2.5">
                    <option value="">Pilih D.I.</option>
                    @foreach($diList as $d)<option value="{{ $d->id }}">{{ $d->nama }}</option>@endforeach
                </select>
            </x-field>
            <x-field name="tanggal" label="Tanggal" wajib>
                <input id="tanggal" type="date" name="tanggal" value="{{ old('tanggal', $laporan->tanggal?->toDateString()) }}" max="{{ today()->toDateString() }}" required class="input py-2.5">
            </x-field>
        </div>
        @if($sebelumnya)<p class="text-xs text-basalt-mute">Angka awal disalin dari laporan {{ $sebelumnya->tanggal->translatedFormat('d M Y') }}. Ubah yang berbeda hari ini.</p>@endif
    </fieldset>

    <fieldset class="panel space-y-4 p-4 sm:p-5">
        <legend class="float-left mb-1 font-gauge text-lg font-semibold">Sungai (sumber air)</legend>
        <div class="clear-left grid grid-cols-2 gap-4">
            <x-field name="debit_sungai_m3s" label="Debit sungai (m³/detik)" hint="1 m³/dt = 1.000 L/dt">
                <input id="debit_sungai_m3s" name="debit_sungai_m3s" type="number" step="0.001" min="0" inputmode="decimal" value="{{ old('debit_sungai_m3s', $laporan->debit_sungai_m3s) }}" class="input py-2.5 text-lg tabular-nums">
            </x-field>
            <x-field name="tma_sungai_cm" label="TMA sungai (cm)" hint="Dibaca di papan duga hulu bendung.">
                <input id="tma_sungai_cm" name="tma_sungai_cm" type="number" step="0.1" inputmode="decimal" value="{{ old('tma_sungai_cm', $laporan->tma_sungai_cm) }}" class="input py-2.5 text-lg tabular-nums">
            </x-field>
        </div>
    </fieldset>

    <fieldset class="panel space-y-4 p-4 sm:p-5">
        <legend class="float-left mb-1 font-gauge text-lg font-semibold">Intake dan saluran</legend>
        <template x-for="b in (intake[di] || [])" :key="b.kode">
            <p class="clear-left rounded-md bg-saluran-50 px-3 py-2 text-xs">Pembacaan pintu <b x-text="b.kode"></b> (<span x-text="b.tanggal"></span>): debit <b x-text="b.debit ?? '–'"></b> L/dt, TMA <b x-text="b.tma ?? '–'"></b> cm.
                <button type="button" class="font-semibold text-saluran-600 underline" @click="pakai(b)">Pakai angka ini</button></p>
        </template>
        <div class="clear-left grid grid-cols-2 gap-4">
            <x-field name="debit_intake_lps" label="Debit intake (L/detik)" wajib>
                <input id="debit_intake_lps" name="debit_intake_lps" type="number" step="0.01" min="0" inputmode="decimal" x-model="f.debit_intake_lps" required class="input py-2.5 text-lg tabular-nums">
            </x-field>
            <x-field name="tma_intake_cm" label="TMA intake (cm)">
                <input id="tma_intake_cm" name="tma_intake_cm" type="number" step="0.1" inputmode="decimal" x-model="f.tma_intake_cm" class="input py-2.5 text-lg tabular-nums">
            </x-field>
        </div>
        <x-field name="kondisi_saluran" label="Kondisi air di saluran" wajib>
            <div class="grid grid-cols-4 gap-2" role="radiogroup">
                @foreach(LH::KONDISI as $k => $l)
                    <label class="cursor-pointer">
                        <input type="radio" name="kondisi_saluran" value="{{ $k }}" @checked(old('kondisi_saluran', $laporan->kondisi_saluran) === $k) class="peer sr-only" required>
                        <span class="block rounded-md border border-savana-line py-2.5 text-center text-sm font-medium peer-checked:border-saluran-600 peer-checked:bg-saluran-600 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-saluran-500">{{ $l }}</span>
                    </label>
                @endforeach
            </div>
        </x-field>
        <x-field name="bukaan_pintu_persen" label="Bukaan pintu intake (%)">
            <input id="bukaan_pintu_persen" name="bukaan_pintu_persen" type="number" min="0" max="100" value="{{ old('bukaan_pintu_persen', $laporan->bukaan_pintu_persen) }}" class="input w-28 tabular-nums">
        </x-field>
    </fieldset>

    <fieldset class="panel space-y-4 p-4 sm:p-5">
        <legend class="float-left mb-1 font-gauge text-lg font-semibold">Foto, catatan & titik lokasi</legend>
        <div class="clear-left">
            <span class="label">Foto (sungai, intake, saluran)</span>
            <x-unggah-foto :maks="max(0, $sisaFoto)"/>
            @if(! $baru && $laporan->foto->isNotEmpty())<x-galeri-foto :foto="$laporan->foto" class="mt-3" :hapus-route="fn ($f) => route('laporan-air.foto.destroy', [$laporan, $f])"/>@endif
        </div>
        <x-field name="catatan" label="Catatan" hint="Mis. banjir kiriman, sedimen di intake, pintu macet, pembagian air digilir.">
            <textarea id="catatan" name="catatan" rows="3" class="input">{{ old('catatan', $laporan->catatan) }}</textarea>
        </x-field>
        <div x-data="pickLocation({ lat: @js(old('lat', $laporan->lat)), lng: @js(old('lng', $laporan->lng)), otomatis: {{ $baru ? 'true' : 'false' }}, pusat: @js($diAwal?->lat ? [$diAwal->lat, $diAwal->lng] : null) })">
            <span class="label">Titik lokasi (GPS)</span>
            <div x-ref="peta" class="h-56 rounded border border-savana-line"></div>
            <div class="mt-2 flex flex-wrap items-end gap-2">
                <label><span class="label">Lintang</span><input name="lat" x-model="lat" class="input w-36" inputmode="decimal"></label>
                <label><span class="label">Bujur</span><input name="lng" x-model="lng" class="input w-36" inputmode="decimal"></label>
                <button type="button" class="btn-ghost" @click="gpsSaya"><x-ikon n="lokasi" class="h-4 w-4"/> GPS</button>
            </div>
            <p class="mt-1 text-xs text-basalt-mute" x-text="gpsStatus === 'mencari' ? 'Mencari lokasi GPS…' : (gpsStatus === 'gagal' ? 'GPS tidak didapat. Ketuk peta atau ketik koordinat.' : (akurasi ? `Dari GPS HP (±${akurasi} m)` : 'Ketuk peta untuk memindahkan titik.'))"></p>
            @error('lat')<p class="text-xs font-medium text-status-kritis">{{ $message }}</p>@enderror
        </div>
    </fieldset>

    <div class="sticky bottom-0 flex gap-2 border-t border-savana-line bg-savana py-3">
        <button class="btn-primary flex-1 py-3 text-base sm:flex-none sm:px-10" :disabled="kirim" x-text="kirim ? 'Mengirim…' : '{{ $baru ? 'Kirim laporan air' : 'Simpan' }}'"></button>
        <a href="{{ $baru ? route('laporan-air.index') : route('laporan-air.show', $laporan) }}" class="btn-ghost">Batal</a>
    </div>
</form>
</div>
</x-layouts.app>
