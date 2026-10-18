@php
    use App\Models\LaporanHarian as LH;
    $baru = ! $laporan->exists;
    $awal = [
        'tanggal' => old('tanggal', optional($laporan->tanggal)->toDateString() ?? today()->toDateString()),
        'sub_daerah_irigasi_id' => (string) old('sub_daerah_irigasi_id', $laporan->sub_daerah_irigasi_id),
        'debit_intake_lps' => old('debit_intake_lps', $laporan->debit_intake_lps),
        'tma_cm' => old('tma_cm', $laporan->tma_cm),
        'kondisi_saluran' => old('kondisi_saluran', $laporan->kondisi_saluran),
        'bukaan_pintu_persen' => old('bukaan_pintu_persen', $laporan->bukaan_pintu_persen),
        'fase_tanaman' => old('fase_tanaman', $laporan->fase_tanaman),
        'luas_tanam_ha' => old('luas_tanam_ha', $laporan->luas_tanam_ha ?? 0),
        'luas_hijau_ha' => old('luas_hijau_ha', $laporan->luas_hijau_ha ?? 0),
        'luas_kering_ha' => old('luas_kering_ha', $laporan->luas_kering_ha ?? 0),
        'luas_panen_ha' => old('luas_panen_ha', $laporan->luas_panen_ha ?? 0),
        'luas_puso_ha' => old('luas_puso_ha', $laporan->luas_puso_ha ?? 0),
        'kendala' => old('kendala', $laporan->kendala),
        'lat' => old('lat', $laporan->lat),
        'lng' => old('lng', $laporan->lng),
    ];
    $sisaFoto = 5 - ($laporan->exists ? $laporan->foto->count() : 0);
    $luasSub = $subs->pluck('luas_ha', 'id');
    $titikSub = $subs->mapWithKeys(fn ($s) => [$s->id => $s->titik()])->filter();
    $status = \App\Support\StatusKondisi::class;
@endphp
<x-layouts.app :title="$baru ? 'Isi laporan harian' : 'Ubah laporan'">
<div class="mx-auto max-w-2xl">
<x-judul :judul="$baru ? 'Laporan harian' : 'Ubah laporan'" sub="Isi kondisi air dan lahan Sub-D.I. hari ini. Kolom bertanda * wajib diisi."/>

@if(! $baru && $laporan->status_verifikasi === 'perbaikan')
    <div class="mb-4 rounded-md border border-[#E9B7B3] bg-[#FBECEA] px-4 py-3 text-sm text-[#7E1A14]">
        <p class="font-semibold">Diminta perbaikan oleh {{ $laporan->verifikator?->name ?? 'verifikator' }}:</p>
        <p>{{ $laporan->catatan_verifikasi }}</p>
    </div>
@endif

<form method="POST" enctype="multipart/form-data"
      action="{{ $baru ? route('laporan.store') : route('laporan.update', $laporan) }}"
      x-data="laporanForm({ baru: {{ $baru ? 'true' : 'false' }}, awal: @js($awal), sisaFoto: {{ $sisaFoto }}, luasSub: @js($luasSub), titikSub: @js($titikSub) })"
      @submit="kirimForm" class="space-y-5">
    @csrf @if(! $baru) @method('PUT') @endif

    <div x-show="!online" x-cloak class="rounded-md bg-[#FBF0CC] px-4 py-2 text-sm text-[#7A5A00]">Anda sedang offline. Isian tetap tersimpan di HP ini dan bisa dikirim saat ada sinyal.</div>
    <div x-show="pesanOffline" x-cloak class="rounded-md bg-[#FBECEA] px-4 py-2 text-sm text-[#7E1A14]" role="alert">Laporan belum terkirim karena tidak ada sinyal. Isian sudah tersimpan — tekan Kirim lagi saat sinyal tersedia.</div>
    <div x-show="adaDraf" x-cloak class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-saluran-100 bg-saluran-50 px-4 py-2 text-sm">
        <span>Ada isian yang belum terkirim untuk Sub-D.I. ini.</span>
        <span class="flex gap-2"><button type="button" class="btn-primary py-1" @click="pulihkanDraf">Lanjutkan isian</button><button type="button" class="btn-ghost py-1" @click="hapusDraf">Abaikan</button></span>
    </div>

    <fieldset class="panel space-y-4 p-4 sm:p-5">
        <legend class="sr-only">Lokasi dan tanggal</legend>
        <div class="grid gap-4 sm:grid-cols-[1fr_11rem]">
            <x-field name="sub_daerah_irigasi_id" label="Sub-D.I." wajib>
                <select id="sub_daerah_irigasi_id" name="sub_daerah_irigasi_id" x-model="f.sub_daerah_irigasi_id" required class="input py-2.5">
                    <option value="">Pilih Sub-D.I.</option>
                    @foreach($subs->groupBy(fn ($s) => $s->daerahIrigasi->nama) as $di => $grup)
                        <optgroup label="D.I. {{ $di }}">
                            @foreach($grup as $s)<option value="{{ $s->id }}">{{ $s->nama }}@if($s->luas_ha) ({{ angka($s->luas_ha) }} Ha)@endif</option>@endforeach
                        </optgroup>
                    @endforeach
                </select>
            </x-field>
            <x-field name="tanggal" label="Tanggal" wajib>
                <input id="tanggal" type="date" name="tanggal" x-model="f.tanggal" max="{{ today()->toDateString() }}" required class="input py-2.5">
            </x-field>
        </div>
        @if($sebelumnya)
            <p class="text-xs text-basalt-mute">Angka awal disalin dari laporan {{ $sebelumnya->tanggal->translatedFormat('d M Y') }}. Ubah yang berbeda hari ini.</p>
        @endif
    </fieldset>

    <fieldset class="panel space-y-4 p-4 sm:p-5">
        <legend class="float-left mb-1 font-gauge text-lg font-semibold">Air di intake dan saluran</legend>
        <div class="clear-left grid grid-cols-2 gap-4">
            <x-field name="debit_intake_lps" label="Debit intake (L/detik)" wajib>
                <input id="debit_intake_lps" name="debit_intake_lps" type="number" step="0.01" min="0" inputmode="decimal" x-model="f.debit_intake_lps" required class="input py-2.5 text-lg tabular-nums">
            </x-field>
            <x-field name="tma_cm" label="TMA (cm)" wajib>
                <input id="tma_cm" name="tma_cm" type="number" step="0.1" inputmode="decimal" x-model="f.tma_cm" required class="input py-2.5 text-lg tabular-nums">
            </x-field>
        </div>
        <x-field name="kondisi_saluran" label="Kondisi air di saluran" wajib>
            <div class="grid grid-cols-4 gap-2" role="radiogroup">
                @foreach(LH::KONDISI as $k => $l)
                    <label class="cursor-pointer">
                        <input type="radio" name="kondisi_saluran" value="{{ $k }}" x-model="f.kondisi_saluran" class="peer sr-only" required>
                        <span class="block rounded-md border border-savana-line py-2.5 text-center text-sm font-medium peer-checked:border-saluran-600 peer-checked:bg-saluran-600 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-saluran-500">{{ $l }}</span>
                    </label>
                @endforeach
            </div>
        </x-field>
        <x-field name="bukaan_pintu_persen" label="Bukaan pintu intake (%)" hint="Kosongkan bila tidak ada pintu.">
            <div class="flex items-center gap-3">
                <input type="range" min="0" max="100" step="5" x-model="f.bukaan_pintu_persen" class="flex-1 accent-saluran-600" aria-label="Bukaan pintu">
                <input id="bukaan_pintu_persen" name="bukaan_pintu_persen" type="number" min="0" max="100" x-model="f.bukaan_pintu_persen" class="input w-20 tabular-nums">
            </div>
        </x-field>
    </fieldset>

    <fieldset class="panel space-y-4 p-4 sm:p-5">
        <legend class="float-left mb-1 font-gauge text-lg font-semibold">Kondisi sawah</legend>
        <x-field name="fase_tanaman" label="Fase tanaman dominan" class="clear-left">
            <select id="fase_tanaman" name="fase_tanaman" x-model="f.fase_tanaman" class="input py-2.5">
                <option value="">Pilih fase</option>
                @foreach(LH::FASE as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
            </select>
        </x-field>
        <div class="grid grid-cols-2 gap-4">
            @foreach(['luas_tanam_ha' => ['Luas tanam (Ha)', 'Total sawah yang ditanami.'], 'luas_hijau_ha' => ['Tanaman sehat/hijau (Ha)', 'Mendapat air cukup.'], 'luas_kering_ha' => ['Tanaman kekeringan (Ha)', 'Ditanam tetapi kurang air.'], 'luas_puso_ha' => ['Puso (Ha)', 'Gagal, tidak bisa dipanen.'], 'luas_panen_ha' => ['Luas panen (Ha)', 'Dipanen hari ini / periode ini.']] as $n => [$lbl, $hint])
                <x-field :name="$n" :label="$lbl" :hint="$hint" wajib>
                    <input id="{{ $n }}" name="{{ $n }}" type="number" step="0.01" min="0" inputmode="decimal" x-model="f.{{ $n }}" required class="input py-2.5 tabular-nums">
                </x-field>
            @endforeach
        </div>
        <p x-show="peringatanLuas" x-cloak class="text-sm font-medium text-status-siaga" x-text="peringatanLuas"></p>
        <div class="flex items-center gap-2 rounded-md bg-savana px-3 py-2 text-sm">
            <span class="text-basalt-soft">Perkiraan status:</span>
            @foreach($status::LABEL as $k => $l)
                <span x-show="statusPratinjau === '{{ $k }}'"><x-status :s="$k"/></span>
            @endforeach
        </div>
    </fieldset>

    <fieldset class="panel space-y-4 p-4 sm:p-5">
        <legend class="float-left mb-1 font-gauge text-lg font-semibold">Foto dan catatan</legend>
        <div class="clear-left">
            <span class="label">Foto lapangan (maks. {{ $sisaFoto }})</span>
            @if(! $baru && $laporan->foto->isNotEmpty())
                <div class="mb-2 flex flex-wrap gap-2">
                    @foreach($laporan->foto as $ft)
                        <div class="relative">
                            <img src="{{ $ft->url }}" alt="Foto laporan" class="h-20 w-24 rounded object-cover">
                            <button form="hapus-foto-{{ $ft->id }}" class="absolute -right-1.5 -top-1.5 rounded-full bg-status-kritis p-0.5 text-white" aria-label="Hapus foto"><x-ikon n="tutup" class="h-3.5 w-3.5"/></button>
                        </div>
                    @endforeach
                </div>
            @endif
            <div class="flex flex-wrap gap-2">
                <template x-for="(ft, i) in foto" :key="ft.url">
                    <div class="relative">
                        <img :src="ft.url" alt="Pratinjau foto" class="h-20 w-24 rounded object-cover">
                        <span class="absolute bottom-0.5 left-0.5 rounded bg-basalt/70 px-1 text-[10px] text-white" x-text="ft.ukuran + ' KB'"></span>
                        <button type="button" @click="hapusFotoBaru(i)" class="absolute -right-1.5 -top-1.5 rounded-full bg-status-kritis p-0.5 text-white" aria-label="Hapus foto"><x-ikon n="tutup" class="h-3.5 w-3.5"/></button>
                    </div>
                </template>
                <label x-show="foto.length < sisaFoto" class="flex h-20 w-24 cursor-pointer flex-col items-center justify-center gap-1 rounded border-2 border-dashed border-savana-line text-xs text-basalt-soft hover:border-saluran-500">
                    <x-ikon n="kamera"/> <span x-text="memproses ? 'Memproses…' : 'Ambil foto'"></span>
                    <input type="file" accept="image/*" capture="environment" multiple class="sr-only" @change="pilihFoto">
                </label>
            </div>
            <input type="file" name="foto[]" multiple x-ref="fotoInput" class="hidden" aria-hidden="true" tabindex="-1">
            @error('foto')<p class="mt-1 text-xs font-medium text-status-kritis">{{ $message }}</p>@enderror
            @error('foto.*')<p class="mt-1 text-xs font-medium text-status-kritis">{{ $message }}</p>@enderror
        </div>
        <x-field name="kendala" label="Kendala / catatan">
            <textarea id="kendala" name="kendala" rows="3" x-model="f.kendala" class="input" placeholder="Mis. saluran sekunder bocor, debit sungai turun, perlu pompa"></textarea>
        </x-field>
        <div>
            <span class="label">Titik lokasi pengamatan</span>
            <p class="-mt-0.5 mb-2 text-xs text-basalt-mute">Pakai GPS HP, ketik/tempel koordinat (mis. dari Google Maps), atau ketuk di peta.</p>
            <div class="grid grid-cols-2 gap-2">
                <label><span class="sr-only">Lintang</span>
                    <input name="lat" x-model="f.lat" @input="lokasi = 'ketik'" @blur="rapikan('lat')" @paste="tempelKoordinat" inputmode="decimal" autocomplete="off" placeholder="Lintang, mis. -10.12345" class="input tabular-nums" :class="!latValid && 'border-status-kritis'"></label>
                <label><span class="sr-only">Bujur</span>
                    <input name="lng" x-model="f.lng" @input="lokasi = 'ketik'" @blur="rapikan('lng')" @paste="tempelKoordinat" inputmode="decimal" autocomplete="off" placeholder="Bujur, mis. 123.81234" class="input tabular-nums" :class="!lngValid && 'border-status-kritis'"></label>
            </div>
            <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
                <button type="button" class="btn-ghost py-1" @click="ambilGps()"><x-ikon n="lokasi" class="h-4 w-4"/> GPS HP</button>
                <button type="button" class="btn-ghost py-1" @click="togglePeta()" :aria-expanded="petaBuka"><x-ikon n="peta" class="h-4 w-4"/> <span x-text="petaBuka ? 'Tutup peta' : 'Pilih di peta'"></span></button>
                <button type="button" x-show="f.lat || f.lng" class="py-1 text-xs font-medium text-basalt-soft hover:text-status-kritis" @click="f.lat = ''; f.lng = ''; lokasi = null">Kosongkan</button>
                <span class="text-xs" :class="gps.status === 'gagal' || !koordinatValid ? 'text-status-siaga' : 'text-basalt-soft'" x-text="keteranganLokasi"></span>
            </div>
            <div x-show="petaBuka" x-cloak class="mt-2">
                <div x-ref="petaLokasi" class="h-72 rounded border border-savana-line" role="application" aria-label="Peta untuk memilih titik lokasi"></div>
                <p class="mt-1 text-xs text-basalt-mute">Ketuk peta untuk menaruh titik. Titik bisa digeser.</p>
            </div>
            @error('lat')<p class="mt-1 text-xs font-medium text-status-kritis">{{ $message }}</p>@enderror
            @error('lng')<p class="mt-1 text-xs font-medium text-status-kritis">{{ $message }}</p>@enderror
        </div>
    </fieldset>

    <div class="sticky bottom-0 -mx-4 flex gap-2 border-t border-savana-line bg-savana/95 px-4 py-3 backdrop-blur sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:p-0">
        <button class="btn-primary flex-1 py-3 text-base sm:flex-none sm:px-8" :disabled="memproses || kirim" x-text="kirim ? 'Mengirim…' : '{{ $baru ? 'Kirim laporan' : 'Simpan perubahan' }}'"></button>
        <a href="{{ $baru ? route('dashboard') : route('laporan.show', $laporan) }}" class="btn-ghost py-3">Batal</a>
    </div>
</form>

@if(! $baru)
    @foreach($laporan->foto as $ft)
        <form id="hapus-foto-{{ $ft->id }}" method="POST" action="{{ route('laporan.foto.destroy', [$laporan, $ft]) }}" class="hidden">@csrf @method('DELETE')</form>
    @endforeach
@endif
</div>
</x-layouts.app>
