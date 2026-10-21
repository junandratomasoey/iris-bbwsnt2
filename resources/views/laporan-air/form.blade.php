@php
    use App\Models\LaporanAir;
    use App\Support\KodeIsian as K;
    $baru = ! $laporan->exists;
    $diAwal = $diList->firstWhere('id', (int) old('daerah_irigasi_id', $laporan->daerah_irigasi_id));
    $sisaFoto = LaporanAir::MAKS_FOTO - ($baru ? 0 : $laporan->foto->count());
    $u = auth()->user();
    $pelapor = $laporan->user ?? $u;
    $infoDi = $diList->mapWithKeys(fn ($d) => [$d->id => ['kabupaten' => $d->kabupaten?->nama, 'luas' => $d->luas_baku_ha ?: $d->luas_permen_ha]]);
    $titikAwal = collect(old('titik', $isiTitik))->map(fn ($r) => collect(['tma', 'debit', 'kebutuhan', 'kondisi_saluran', 'catatan'])
        ->mapWithKeys(fn ($k) => [$k => isset($r[$k]) && $r[$k] !== null ? (string) $r[$k] : ''])->all());
    $awal = [
        'di' => (string) old('daerah_irigasi_id', $laporan->daerah_irigasi_id),
        'tanggal' => old('tanggal', $laporan->tanggal?->toDateString()),
        'jenis' => old('jenis', $laporan->jenis ?? 'rutin'),
        'alasan' => old('alasan', $laporan->alasan ?? ''),
        'melimpas' => (string) old('melimpas', $laporan->melimpas === null ? '' : (int) $laporan->melimpas),
        'status_pemberian' => old('status_pemberian', $laporan->status_pemberian ?? ''),
        'titik' => (object) $titikAwal->all(),
    ];
    $v = fn ($k) => old($k, $laporan->$k);
@endphp
<x-layouts.app :title="$baru ? 'Laporan Manager Air' : 'Ubah laporan Manager Air'">
<div class="mx-auto max-w-3xl">
<x-judul :judul="$baru ? 'Laporan Manager Air' : 'Ubah laporan Manager Air'" sub="Bangunan utama (bendung/intake) dan saluran primer. Rutin tiap Senin pukul 07.00–09.00 WITA sebelum rekap laporan PPA, atau insidental. Kondisi tanaman tidak diisi Manager (tugas PPA). Kolom bertanda * wajib."/>

@if($errors->any())
    <div class="mb-4 rounded-md border border-[#E9B7B3] bg-[#FBECEA] px-4 py-3 text-sm text-[#7E1A14]" role="alert">
        <p class="font-semibold">Laporan belum terkirim. Periksa isian berikut:</p>
        <ul class="mt-1 list-disc pl-5">@foreach(collect($errors->all())->unique()->take(8) as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<form method="POST" enctype="multipart/form-data" action="{{ $baru ? route('laporan-air.store') : route('laporan-air.update', $laporan) }}"
      x-data="{ kirim: false, f: @js($awal), titikDi: @js((object) $titik), intakeDi: @js((object) $intake), infoDi: @js($infoDi), rekapDi: @js((object) $rekap),
                get titik() { return this.titikDi[this.f.di] || []; },
                get info() { return this.infoDi[this.f.di] || null; },
                get rekap() { return this.rekapDi[this.f.di] || null; },
                get senin() { return !!this.f.tanggal && new Date(this.f.tanggal + 'T00:00:00').getDay() === 1; },
                t(id) { if (!this.f.titik[id]) this.f.titik[id] = { tma: '', debit: '', kebutuhan: '', kondisi_saluran: '', catatan: '' }; return this.f.titik[id]; },
                selisih(p) { const r = this.t(p.id); if (r.debit === '' || r.kebutuhan === '') return null; return Math.round((+r.debit - +r.kebutuhan) * 100) / 100; },
                init() {
                    if (!this.f.titik || Array.isArray(this.f.titik)) this.f.titik = {};
                    Object.values(this.titikDi).flat().forEach((p) => { const r = this.t(p.id); if (r.kebutuhan === '' && p.rencana !== null && {{ $baru ? 'true' : 'false' }}) r.kebutuhan = String(p.rencana); });
                } }"
      @submit="kirim = true" class="space-y-5">
    @csrf @if(! $baru) @method('PUT') @endif

    {{-- ================= A. Identitas ================= --}}
    <fieldset class="panel space-y-4 p-4 sm:p-5">
        <legend class="bagian float-left mb-1"><span class="bagian-huruf">A</span>Identitas laporan</legend>
        <div class="clear-left grid gap-4 sm:grid-cols-[1fr_10rem_7rem]">
            <x-field name="daerah_irigasi_id" label="Daerah Irigasi (D.I.)" wajib>
                <select id="daerah_irigasi_id" name="daerah_irigasi_id" x-model="f.di" required class="input py-2.5">
                    <option value="">Pilih D.I.</option>
                    @foreach($diList as $d)<option value="{{ $d->id }}">{{ $d->nama }}</option>@endforeach
                </select>
            </x-field>
            <x-field name="tanggal" label="Hari / tanggal" wajib>
                <input id="tanggal" type="date" name="tanggal" x-model="f.tanggal" max="{{ today()->toDateString() }}" required class="input py-2.5">
            </x-field>
            <x-field name="jam" label="Jam (WITA)">
                <input id="jam" type="time" name="jam" value="{{ old('jam', $laporan->jam ? substr($laporan->jam, 0, 5) : '') }}" class="input py-2.5">
            </x-field>
        </div>
        <dl class="grid grid-cols-2 gap-x-4 gap-y-1 rounded-md bg-savana px-3 py-2 text-xs sm:grid-cols-4" x-show="info">
            <div><dt class="text-basalt-mute">Kabupaten</dt><dd class="font-medium" x-text="info?.kabupaten || '–'"></dd></div>
            <div><dt class="text-basalt-mute">Luas baku D.I.</dt><dd class="font-medium" x-text="info?.luas ? Number(info.luas).toLocaleString('id-ID') + ' Ha' : '–'"></dd></div>
            <div><dt class="text-basalt-mute">Manager Air</dt><dd class="font-medium">{{ $pelapor->name }}</dd></div>
            <div><dt class="text-basalt-mute">No. WhatsApp</dt><dd class="font-medium">{{ $pelapor->no_hp ?: '–' }}</dd></div>
        </dl>
        @if($sebelumnya)<p class="text-xs text-basalt-mute">Kondisi bangunan dan status pemberian air disalin dari laporan {{ $sebelumnya->tanggal->translatedFormat('d M Y') }}. Ubah yang berbeda.</p>@endif
        <div>
            <span class="label">Jenis pembacaan <span class="text-status-kritis">*</span></span>
            <x-pilihan name="jenis" :opsi="K::JENIS_LAPOR" model="f.jenis" kolom="grid-cols-2 sm:max-w-sm"/>
            <p x-show="f.jenis === 'rutin' && !senin" x-cloak class="mt-1 text-xs text-status-siaga">Tanggal ini bukan hari Senin. Pilih "Insidental" bila laporan karena kejadian.</p>
        </div>
        <div x-show="f.jenis === 'insidental'" x-cloak>
            <span class="label">Alasan insidental <span class="text-status-kritis">*</span></span>
            <x-pilihan name="alasan" :opsi="K::ALASAN_MANAGER" model="f.alasan"/>
            <input x-show="f.alasan === 'lainnya'" name="alasan_lain" value="{{ $v('alasan_lain') }}" maxlength="150" class="input mt-2" placeholder="Tulis alasannya">
        </div>
        <div class="grid gap-4 sm:grid-cols-[13rem_1fr]">
            <div>
                <span class="label">Musim tanam</span>
                <div class="grid grid-cols-3 gap-1.5 whitespace-nowrap">@foreach(K::MT as $k => $l)<label class="opsi"><input type="radio" name="mt" value="{{ $k }}" @checked((string) $v('mt') === (string) $k)>{{ $l }}</label>@endforeach</div>
            </div>
            <div>
                <span class="label">Cuaca saat pembacaan</span>
                <div class="grid grid-cols-2 gap-1.5 sm:grid-cols-4">@foreach(K::CUACA as $k => $l)<label class="opsi"><input type="radio" name="cuaca" value="{{ $k }}" @checked($v('cuaca') === $k)>{{ $l }}</label>@endforeach</div>
            </div>
        </div>
        <x-field name="curah_hujan_mm" label="Curah hujan (mm, jika ada penakar)" class="sm:max-w-xs">
            <input id="curah_hujan_mm" name="curah_hujan_mm" type="number" step="0.1" min="0" inputmode="decimal" value="{{ $v('curah_hujan_mm') }}" class="input py-2.5 tabular-nums">
        </x-field>
    </fieldset>

    {{-- ================= B. Bangunan utama ================= --}}
    <fieldset class="panel space-y-4 p-4 sm:p-5">
        <legend class="bagian float-left mb-1"><span class="bagian-huruf">B</span>Bangunan utama (bendung / intake)</legend>
        <p class="clear-left text-xs text-basalt-soft">TMA dibaca di papan duga (peilschaal), dalam cm. Debit dari tabel debit/alat ukur (ambang lebar, Cipoletti, Romijn, dll.), dalam L/detik.<span x-show="intakeDi[f.di]"> Bukaan, TMA hilir, dan debit intake juga tercatat sebagai pembacaan pintu <b x-text="intakeDi[f.di]"></b>.</span></p>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <x-field name="tma_sungai_cm" label="TMA hulu bendung (cm)" wajib>
                <input id="tma_sungai_cm" name="tma_sungai_cm" type="number" step="any" inputmode="decimal" value="{{ $v('tma_sungai_cm') }}" required class="input py-2.5 tabular-nums">
            </x-field>
            <div>
                <span class="label">Air melimpas di mercu</span>
                <div class="grid grid-cols-2 gap-1.5"><label class="opsi"><input type="radio" name="melimpas" value="1" x-model="f.melimpas">Ya</label><label class="opsi"><input type="radio" name="melimpas" value="0" x-model="f.melimpas">Tidak</label></div>
            </div>
            <x-field name="tinggi_limpasan_cm" label="Tinggi limpasan di atas mercu (cm)" x-show="f.melimpas === '1'" x-cloak>
                <input id="tinggi_limpasan_cm" name="tinggi_limpasan_cm" type="number" step="any" min="0" inputmode="decimal" value="{{ $v('tinggi_limpasan_cm') }}" class="input py-2.5 tabular-nums">
            </x-field>
            <x-field name="bukaan_intake_cm" label="Bukaan pintu pengambilan (cm)">
                <input id="bukaan_intake_cm" name="bukaan_intake_cm" type="number" step="any" min="0" inputmode="decimal" value="{{ $v('bukaan_intake_cm') }}" class="input py-2.5 tabular-nums">
            </x-field>
            <x-field name="tma_intake_cm" label="TMA di hilir intake (cm)">
                <input id="tma_intake_cm" name="tma_intake_cm" type="number" step="any" inputmode="decimal" value="{{ $v('tma_intake_cm') }}" class="input py-2.5 tabular-nums">
            </x-field>
            <x-field name="debit_intake_lps" label="Debit masuk saluran induk (L/dt)">
                <input id="debit_intake_lps" name="debit_intake_lps" type="number" step="any" min="0" inputmode="decimal" value="{{ $v('debit_intake_lps') }}" class="input py-2.5 tabular-nums">
            </x-field>
            <x-field name="bukaan_penguras_cm" label="Bukaan pintu penguras (cm)">
                <input id="bukaan_penguras_cm" name="bukaan_penguras_cm" type="number" step="any" min="0" inputmode="decimal" value="{{ $v('bukaan_penguras_cm') }}" class="input py-2.5 tabular-nums">
            </x-field>
            <x-field name="debit_sungai_m3s" label="Debit sungai (m³/dt, opsional)" hint="Tambahan bila ada pengukuran.">
                <input id="debit_sungai_m3s" name="debit_sungai_m3s" type="number" step="0.001" min="0" inputmode="decimal" value="{{ $v('debit_sungai_m3s') }}" class="input py-2.5 tabular-nums">
            </x-field>
        </div>
        @foreach(['kondisi_bendung' => ['Kondisi bendung', K::KONDISI_BENDUNG], 'kondisi_pintu_intake' => ['Kondisi pintu intake', K::KONDISI_PINTU], 'kondisi_kantong_lumpur' => ['Kondisi kantong lumpur', K::KANTONG_LUMPUR]] as $n => [$lbl, $opsi])
            <div>
                <span class="label">{{ $lbl }}</span>
                <div class="grid grid-cols-2 gap-1.5 sm:grid-cols-4">
                    @foreach($opsi as $k => $l)<label class="opsi flex-col sm:flex-row"><input type="radio" name="{{ $n }}" value="{{ $k }}" @checked($v($n) === $k)><span class="opsi-kode">{{ $k }}</span><span class="text-[12px]">{{ $l }}</span></label>@endforeach
                </div>
            </div>
        @endforeach
    </fieldset>

    {{-- ================= C. Saluran primer ================= --}}
    <fieldset class="panel space-y-3 p-4 sm:p-5">
        <legend class="bagian float-left mb-1"><span class="bagian-huruf">C</span>Saluran primer (titik ukur / bangunan bagi)</legend>
        <p class="clear-left text-xs text-basalt-soft">Satu isian per titik ukur, urut dari hulu ke hilir sesuai skema jaringan. Debit kebutuhan sesuai rencana pemberian air MT berjalan dari Bidang OP (terisi dari debit rencana pintu, bisa diubah).</p>
        <p x-show="f.di && !titik.length" class="rounded-md bg-savana px-3 py-2 text-sm text-basalt-soft">Belum ada pintu tingkat primer di D.I. ini. Tambahkan titik ukur/bangunan bagi primer di menu Pintu air (tingkat "Primer").</p>
        <template x-for="(p, i) in titik" :key="p.id">
            <div class="rounded-md border border-savana-line p-3">
                <div class="flex flex-wrap items-baseline justify-between gap-x-2">
                    <p class="font-semibold"><span class="mr-1 text-basalt-mute" x-text="(i + 1) + '.'"></span><span x-text="p.kode"></span><span class="font-normal text-basalt-soft" x-text="p.nama ? ' · ' + p.nama : ''"></span></p>
                    <span class="text-xs font-semibold" x-show="selisih(p) !== null" :class="selisih(p) < 0 ? 'text-[#8A3510]' : 'text-[#2F5A19]'" x-text="'Selisih ' + (selisih(p) > 0 ? '+' : '') + (selisih(p) ?? 0).toLocaleString('id-ID') + ' L/dt'"></span>
                </div>
                <div class="mt-2 grid grid-cols-3 gap-2 sm:gap-3">
                    <label><span class="label text-xs">TMA (cm)</span><input type="number" step="any" inputmode="decimal" :name="`titik[${p.id}][tma]`" x-model="t(p.id).tma" class="input py-2.5 tabular-nums"></label>
                    <label><span class="label text-xs">Debit terukur (L/dt)</span><input type="number" step="any" min="0" inputmode="decimal" :name="`titik[${p.id}][debit]`" x-model="t(p.id).debit" class="input py-2.5 tabular-nums"></label>
                    <label><span class="label text-xs">Debit kebutuhan (L/dt)</span><input type="number" step="any" min="0" inputmode="decimal" :name="`titik[${p.id}][kebutuhan]`" x-model="t(p.id).kebutuhan" class="input py-2.5 tabular-nums"></label>
                </div>
                <div class="mt-1 grid gap-2 sm:grid-cols-2 sm:gap-3">
                    <label><span class="label text-xs">Kondisi saluran</span>
                        <select :name="`titik[${p.id}][kondisi_saluran]`" x-model="t(p.id).kondisi_saluran" class="input py-2"><option value="">–</option>
                            @foreach(K::KONDISI_SALURAN as $k => $l)<option value="{{ $k }}">{{ $k }} · {{ $l }}</option>@endforeach
                        </select></label>
                    <label><span class="label text-xs">Keterangan</span><input :name="`titik[${p.id}][catatan]`" x-model="t(p.id).catatan" maxlength="255" class="input py-2"></label>
                </div>
            </div>
        </template>
        <div class="border-t border-savana-line pt-3">
            <span class="label">Status pemberian air</span>
            <x-pilihan name="status_pemberian" :opsi="K::PEMBERIAN_AIR" model="f.status_pemberian" kolom="grid-cols-3 sm:max-w-md"/>
            <input x-show="f.status_pemberian" x-cloak name="atas_perintah" value="{{ $v('atas_perintah') }}" maxlength="150" class="input mt-2" placeholder="Atas perintah siapa?">
        </div>
    </fieldset>

    {{-- ================= D. Rekap PPA ================= --}}
    <section class="panel p-4 sm:p-5" x-show="rekap">
        <h2 class="bagian"><span class="bagian-huruf">D</span>Rekap laporan PPA</h2>
        <p class="mt-1 text-xs text-basalt-soft">Dihitung otomatis dari laporan PPA pekan <span x-text="rekap?.senin + ' – ' + rekap?.akhir"></span> sebagai kontrol kelengkapan. Diperbarui lagi saat laporan dibuka.</p>
        <div class="mt-2 overflow-x-auto">
            <table class="table-base">
                <thead><tr><th>Sub-D.I.</th><th>Nama PPA</th><th>Laporan masuk</th><th class="text-right">Pintu bermasalah</th><th class="text-right">Petak kekurangan air</th></tr></thead>
                <tbody>
                    <template x-for="s in (rekap?.sub || [])" :key="s.nama">
                        <tr>
                            <td class="font-medium" x-text="s.nama"></td>
                            <td class="text-xs" x-text="s.ppa || '–'"></td>
                            <td><span :class="s.masuk ? 'chip-aman' : 'chip-siaga'" x-text="s.masuk ? 'Ya (' + s.masuk + ')' : 'Belum'"></span></td>
                            <td class="text-right tabular-nums" x-text="s.pintu_bermasalah"></td>
                            <td class="text-right tabular-nums" x-text="s.petak_kurang_air"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </section>

    {{-- ================= E. Permasalahan ================= --}}
    <fieldset class="panel space-y-4 p-4 sm:p-5">
        <legend class="bagian float-left mb-1"><span class="bagian-huruf">E</span>Permasalahan dan dokumentasi</legend>
        <x-field name="catatan" label="Permasalahan di bangunan utama / saluran primer" class="clear-left">
            <textarea id="catatan" name="catatan" rows="3" class="input" placeholder="Kerusakan, sedimentasi, longsor, pengambilan liar, konflik air">{{ $v('catatan') }}</textarea>
        </x-field>
        <x-field name="usulan" label="Usulan tindak lanjut ke Balai">
            <textarea id="usulan" name="usulan" rows="2" class="input">{{ $v('usulan') }}</textarea>
        </x-field>
        <div>
            <span class="label">Dokumentasi foto (centang yang sudah diambil)</span>
            <div class="grid gap-1.5 sm:grid-cols-2">@foreach(K::DOKUMENTASI_MANAGER as $k => $l)<label class="opsi justify-start text-left"><input type="checkbox" name="dokumentasi[]" value="{{ $k }}" @checked(in_array($k, (array) old('dokumentasi', $laporan->dokumentasi ?? [])))>{{ $l }}</label>@endforeach</div>
        </div>
        <div>
            <span class="label">Foto (papan duga, intake & penguras, titik ukur, permasalahan) — GPS aktif</span>
            <x-unggah-foto :maks="max(0, $sisaFoto)"/>
            @if(! $baru && $laporan->foto->isNotEmpty())<x-galeri-foto :foto="$laporan->foto" class="mt-3" :hapus-route="fn ($f) => route('laporan-air.foto.destroy', [$laporan, $f])"/>@endif
        </div>
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
        <p class="rounded-md bg-savana px-3 py-2 text-xs text-basalt-soft">Pengesahan: dilaporkan oleh Manager Air; diketahui oleh Pengamat / Bidang OP BBWS NT II.</p>
    </fieldset>

    <details class="panel p-4 text-sm sm:p-5">
        <summary class="bagian cursor-pointer"><span class="bagian-huruf">F</span>Kode isian</summary>
        <dl class="mt-3 space-y-2">
            @foreach(['Kondisi bendung' => K::KONDISI_BENDUNG, 'Kondisi pintu' => K::KONDISI_PINTU, 'Kondisi kantong lumpur' => K::KANTONG_LUMPUR, 'Kondisi saluran' => K::KONDISI_SALURAN] as $judul => $daftar)
                <div><dt class="font-semibold">{{ $judul }}</dt><dd class="text-basalt-soft">{{ collect($daftar)->map(fn ($l, $k) => $k.' = '.$l)->implode(' · ') }}</dd></div>
            @endforeach
            <div><dt class="font-semibold">Selisih debit</dt><dd class="text-basalt-soft">+ = di atas kebutuhan · − = di bawah kebutuhan</dd></div>
        </dl>
    </details>

    <div class="sticky bottom-0 flex gap-2 border-t border-savana-line bg-savana py-3">
        <button class="btn-primary flex-1 py-3 text-base sm:flex-none sm:px-10" :disabled="kirim" x-text="kirim ? 'Mengirim…' : '{{ $baru ? 'Kirim laporan' : 'Simpan' }}'"></button>
        <a href="{{ $baru ? route('laporan-air.index') : route('laporan-air.show', $laporan) }}" class="btn-ghost">Batal</a>
    </div>
</form>
</div>
</x-layouts.app>
