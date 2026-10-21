@php
    use App\Support\KodeIsian as K;
    $baru = ! $laporan->exists;
    $u = auth()->user();
    $bacaAwal = collect(old('baca', collect($isiBaca)->map(fn ($b) => [
        'tma' => $b['tma_cm'], 'bukaan' => $b['bukaan_cm'], 'debit' => $b['debit_lps'],
        'kondisi_pintu' => $b['kondisi_pintu'], 'kondisi_saluran' => $b['kondisi_saluran'], 'catatan' => $b['catatan'],
    ])->all()))->map(fn ($r) => collect(['tma', 'bukaan', 'debit', 'kondisi_pintu', 'kondisi_saluran', 'catatan'])->mapWithKeys(fn ($k) => [$k => $r[$k] ?? ''])->all());
    $petakAwal = collect(old('petak', $isiPetak))->map(fn ($r) => collect(['luas_tanam_ha', 'jenis_tanaman', 'fase', 'umur_hst', 'kecukupan_air', 'luas_kering_ha', 'keterangan'])
        ->mapWithKeys(fn ($k) => [$k => isset($r[$k]) && $r[$k] !== null ? (string) $r[$k] : ''])->all());
    $awal = [
        'tanggal' => old('tanggal', optional($laporan->tanggal)->toDateString() ?? today()->toDateString()),
        'jam' => old('jam', $laporan->jam ? substr($laporan->jam, 0, 5) : ''),
        'jenis' => old('jenis', $laporan->jenis ?? 'rutin'),
        'alasan' => old('alasan', $laporan->alasan ?? ''),
        'alasan_lain' => old('alasan_lain', $laporan->alasan_lain),
        'sub_daerah_irigasi_id' => (string) old('sub_daerah_irigasi_id', $laporan->sub_daerah_irigasi_id),
        'mt' => (string) old('mt', $laporan->mt),
        'cuaca' => old('cuaca', $laporan->cuaca ?? ''),
        'hujan_7hari' => old('hujan_7hari', $laporan->hujan_7hari ?? ''),
        'tindakan_operasi' => old('tindakan_operasi', $laporan->tindakan_operasi ?? ''),
        'atas_perintah' => old('atas_perintah', $laporan->atas_perintah),
        'kendala' => old('kendala', $laporan->kendala),
        'usulan' => old('usulan', $laporan->usulan),
        'dokumentasi' => old('dokumentasi', $laporan->dokumentasi ?? []),
        'baca' => (object) $bacaAwal->all(),
        'petak' => (object) $petakAwal->all(),
        'lat' => old('lat', $laporan->lat),
        'lng' => old('lng', $laporan->lng),
    ];
    $sisaFoto = 5 - ($laporan->exists ? $laporan->foto->count() : 0);
    $titikSub = $subs->mapWithKeys(fn ($s) => [$s->id => $s->titik()])->filter();
    $infoSub = $subs->mapWithKeys(fn ($s) => [$s->id => ['di' => $s->daerahIrigasi->nama, 'kabupaten' => $s->daerahIrigasi->kabupaten?->nama, 'luas' => $s->luas_ha]]);
    $pelapor = $laporan->user ?? $u;
    $galatPetak = collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'petak'))->flatten();
    $galatPintu = collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'baca'))->flatten();
@endphp
<x-layouts.app :title="$baru ? 'Isi laporan PPA' : 'Ubah laporan PPA'">
<div class="mx-auto max-w-3xl">
<x-judul :judul="$baru ? 'Laporan Petugas Pintu Air (PPA)' : 'Ubah laporan PPA'" sub="Pembacaan pintu sekunder/tersier dan kondisi tanaman tiap petak tersier. Rutin tiap Senin pukul 07.00–09.00 WITA, atau insidental saat ada kejadian. Kolom bertanda * wajib."/>

@if(! $baru && $laporan->status_verifikasi === 'perbaikan')
    <div class="mb-4 rounded-md border border-[#E9B7B3] bg-[#FBECEA] px-4 py-3 text-sm text-[#7E1A14]">
        <p class="font-semibold">Diminta perbaikan oleh {{ $laporan->verifikator?->name ?? 'verifikator' }}:</p>
        <p>{{ $laporan->catatan_verifikasi }}</p>
    </div>
@endif
@if($errors->any())
    <div class="mb-4 rounded-md border border-[#E9B7B3] bg-[#FBECEA] px-4 py-3 text-sm text-[#7E1A14]" role="alert">
        <p class="font-semibold">Laporan belum terkirim. Periksa isian berikut:</p>
        <ul class="mt-1 list-disc pl-5">@foreach(collect($errors->all())->unique()->take(8) as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
@endif

<form method="POST" enctype="multipart/form-data"
      action="{{ $baru ? route('laporan.store') : route('laporan.update', $laporan) }}"
      x-data="laporanForm({ baru: {{ $baru ? 'true' : 'false' }}, awal: @js($awal), sisaFoto: {{ $sisaFoto }}, titikSub: @js($titikSub), petakSub: @js($petak), pintuSub: @js((object) $pintu), sebelumnya: @js((object) $sebelumnya), infoSub: @js($infoSub), wajibBaca: {{ $baru || ! empty($isiBaca) ? 'true' : 'false' }}, statusKecukupan: @js(K::STATUS_KECUKUPAN) })"
      @submit="kirimForm" class="space-y-5">
    @csrf @if(! $baru) @method('PUT') @endif

    <div x-show="!online" x-cloak class="rounded-md bg-[#FBF0CC] px-4 py-2 text-sm text-[#7A5A00]">Anda sedang offline. Isian tetap tersimpan di HP ini dan bisa dikirim saat ada sinyal.</div>
    <div x-show="pesanOffline" x-cloak class="rounded-md bg-[#FBECEA] px-4 py-2 text-sm text-[#7E1A14]" role="alert">Laporan belum terkirim karena tidak ada sinyal. Isian sudah tersimpan — tekan Kirim lagi saat sinyal tersedia.</div>
    <div x-show="adaDraf" x-cloak class="flex flex-wrap items-center justify-between gap-2 rounded-md border border-saluran-100 bg-saluran-50 px-4 py-2 text-sm">
        <span>Ada isian yang belum terkirim untuk Sub-D.I. ini.</span>
        <span class="flex gap-2"><button type="button" class="btn-primary py-1" @click="pulihkanDraf">Lanjutkan isian</button><button type="button" class="btn-ghost py-1" @click="hapusDraf">Abaikan</button></span>
    </div>

    {{-- ================= A. Identitas ================= --}}
    <fieldset class="panel space-y-4 p-4 sm:p-5">
        <legend class="bagian float-left mb-1"><span class="bagian-huruf">A</span>Identitas laporan</legend>
        <div class="clear-left grid gap-4 sm:grid-cols-[1fr_10rem_7rem]">
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
            <x-field name="tanggal" label="Hari / tanggal" wajib>
                <input id="tanggal" type="date" name="tanggal" x-model="f.tanggal" max="{{ today()->toDateString() }}" required class="input py-2.5">
            </x-field>
            <x-field name="jam" label="Jam (WITA)">
                <input id="jam" type="time" name="jam" x-model="f.jam" class="input py-2.5">
            </x-field>
        </div>
        <dl class="grid grid-cols-2 gap-x-4 gap-y-1 rounded-md bg-savana px-3 py-2 text-xs sm:grid-cols-4" x-show="info">
            <div><dt class="text-basalt-mute">D.I.</dt><dd class="font-medium" x-text="info?.di"></dd></div>
            <div><dt class="text-basalt-mute">Kabupaten</dt><dd class="font-medium" x-text="info?.kabupaten || '–'"></dd></div>
            <div><dt class="text-basalt-mute">Petugas (PPA)</dt><dd class="font-medium">{{ $pelapor->name }}</dd></div>
            <div><dt class="text-basalt-mute">No. WhatsApp</dt><dd class="font-medium">{{ $pelapor->no_hp ?: '–' }}</dd></div>
        </dl>
        <p class="text-xs text-basalt-mute" x-show="sebelumnyaSub" x-text="sebelumnyaSub ? 'Jenis tanaman, fase, umur (ditambah selang hari), dan luas tanam disalin dari laporan ' + sebelumnyaSub.tanggal + '. Ubah yang berbeda.' : ''"></p>

        <div>
            <span class="label">Jenis pembacaan <span class="text-status-kritis">*</span></span>
            <x-pilihan name="jenis" :opsi="K::JENIS_LAPOR" model="f.jenis" kolom="grid-cols-2 sm:max-w-sm"/>
            <p x-show="f.jenis === 'rutin' && !senin" x-cloak class="mt-1 text-xs text-status-siaga">Tanggal ini bukan hari Senin. Bila ini laporan rutin yang terlambat, biarkan "Rutin"; bila karena kejadian, pilih "Insidental".</p>
        </div>
        <div x-show="f.jenis === 'insidental'" x-cloak>
            <span class="label">Alasan insidental <span class="text-status-kritis">*</span></span>
            <x-pilihan name="alasan" :opsi="K::ALASAN_PPA" model="f.alasan"/>
            <input x-show="f.alasan === 'lainnya'" name="alasan_lain" x-model="f.alasan_lain" maxlength="150" class="input mt-2" placeholder="Tulis alasannya">
            @error('alasan')<p class="mt-1 text-xs font-medium text-status-kritis">{{ $message }}</p>@enderror
            @error('alasan_lain')<p class="mt-1 text-xs font-medium text-status-kritis">{{ $message }}</p>@enderror
        </div>
        <div class="grid gap-4 sm:grid-cols-[13rem_1fr]">
            <div>
                <span class="label">Musim tanam</span>
                <x-pilihan name="mt" :opsi="K::MT" model="f.mt" kolom="grid-cols-3 whitespace-nowrap"/>
            </div>
            <div>
                <span class="label">Cuaca saat pembacaan</span>
                <x-pilihan name="cuaca" :opsi="K::CUACA" model="f.cuaca" kolom="grid-cols-2 sm:grid-cols-4"/>
            </div>
        </div>
        <div>
            <span class="label">Hujan 7 hari terakhir</span>
            <x-pilihan name="hujan_7hari" :opsi="K::HUJAN_7_HARI" model="f.hujan_7hari" kolom="grid-cols-2 sm:grid-cols-4"/>
        </div>
    </fieldset>

    {{-- ================= B. Pembacaan pintu ================= --}}
    <fieldset class="panel space-y-3 p-4 sm:p-5">
        <legend class="bagian float-left mb-1"><span class="bagian-huruf">B</span>Pembacaan pintu air (sekunder/tersier)</legend>
        <p class="clear-left text-xs text-basalt-soft">Satu isian per pintu. <strong>TMA</strong>: baca papan duga (peilschaal) di hulu pintu saat air tenang, mata sejajar permukaan air (cm). <strong>Bukaan</strong>: tinggi bukaan daun pintu (cm, 0 = tertutup). <strong>Debit</strong>: dari tabel/lengkung debit pintu (L/dt); kosongkan bila tidak ada tabel.</p>
        @if($galatPintu->isNotEmpty())<p class="rounded-md bg-[#F6D9D7] px-3 py-2 text-sm font-medium text-[#7E1A14]">{{ $galatPintu->first() }}</p>@endif
        <p x-show="f.sub_daerah_irigasi_id && !semuaPintu.length" class="rounded-md bg-savana px-3 py-2 text-sm text-basalt-soft">Belum ada pintu air terdaftar di Sub-D.I. ini. Manager dapat menambahkannya di menu Pintu air.</p>
        <template x-for="p in daftarPintu" :key="p.id">
            <div class="rounded-md border border-savana-line p-3" :class="p.saya && 'border-saluran-500 bg-saluran-50/40'">
                <div class="flex flex-wrap items-baseline justify-between gap-x-2">
                    <p class="font-semibold"><span x-text="p.kode"></span>
                        <span x-show="p.saya" class="ml-1 rounded bg-saluran-600 px-1.5 text-[10px] font-semibold text-white">tugas saya</span></p>
                    <span class="text-xs text-basalt-soft" x-text="p.tingkat_label + (p.nama ? ' · ' + p.nama : '')"></span>
                </div>
                <p class="text-[11px] text-basalt-mute" x-text="[p.terakhir ? 'Terakhir ' + p.terakhir : null, p.rencana ? 'Q rencana ' + p.rencana + ' L/dt' : null].filter(Boolean).join(' · ')"></p>
                <div class="mt-2 grid grid-cols-3 items-start gap-2 sm:gap-3">
                    <label><span class="label whitespace-nowrap text-xs">TMA hulu (cm)<span x-show="p.wajib" class="text-status-kritis"> *</span></span>
                        <input type="number" step="any" min="0" inputmode="decimal" :name="`baca[${p.id}][tma]`" x-model="baca(p.id).tma" :required="p.wajib && wajibBaca" class="input py-2.5 tabular-nums">
                        <span class="mt-0.5 block h-4 text-[11px] font-semibold" :class="warnaPintu(statusPintu(p, 'tma'))" x-text="teksPintu(statusPintu(p, 'tma'))"></span></label>
                    <label><span class="label whitespace-nowrap text-xs">Bukaan (cm)</span>
                        <input type="number" step="any" min="0" inputmode="decimal" :name="`baca[${p.id}][bukaan]`" x-model="baca(p.id).bukaan" class="input py-2.5 tabular-nums"></label>
                    <label><span class="label whitespace-nowrap text-xs">Debit (L/dt)</span>
                        <input type="number" step="any" min="0" inputmode="decimal" :name="`baca[${p.id}][debit]`" x-model="baca(p.id).debit" class="input py-2.5 tabular-nums">
                        <span class="mt-0.5 block h-4 text-[11px] font-semibold" :class="warnaPintu(statusPintu(p, 'debit'))" x-text="teksPintu(statusPintu(p, 'debit'))"></span></label>
                </div>
                <div class="mt-1 grid grid-cols-2 gap-2 sm:gap-3">
                    <label><span class="label text-xs">Kondisi pintu</span>
                        <select :name="`baca[${p.id}][kondisi_pintu]`" x-model="baca(p.id).kondisi_pintu" class="input py-2">
                            <option value="">–</option>
                            @foreach(K::KONDISI_PINTU as $k => $l)<option value="{{ $k }}">{{ $k }} · {{ $l }}</option>@endforeach
                        </select></label>
                    <label><span class="label text-xs">Kondisi saluran</span>
                        <select :name="`baca[${p.id}][kondisi_saluran]`" x-model="baca(p.id).kondisi_saluran" class="input py-2">
                            <option value="">–</option>
                            @foreach(K::KONDISI_SALURAN as $k => $l)<option value="{{ $k }}">{{ $k }} · {{ $l }}</option>@endforeach
                        </select></label>
                </div>
                <input :name="`baca[${p.id}][catatan]`" x-model="baca(p.id).catatan" maxlength="255" class="input mt-2 py-2 text-sm" placeholder="Keterangan (opsional)">
            </div>
        </template>
        <button type="button" x-show="pintuLain" x-cloak class="btn-ghost w-full py-2 text-sm" @click="tampilPintuLain = !tampilPintuLain"
                x-text="tampilPintuLain ? 'Sembunyikan pintu lain' : 'Tampilkan ' + pintuLain + ' pintu lain di Sub-D.I. ini'"></button>
        <div class="border-t border-savana-line pt-3">
            <span class="label">Tindakan operasi pintu hari ini</span>
            <x-pilihan name="tindakan_operasi" :opsi="K::TINDAKAN_PINTU" model="f.tindakan_operasi" kolom="grid-cols-2 sm:grid-cols-4"/>
            <input x-show="f.tindakan_operasi && f.tindakan_operasi !== 'tetap'" x-cloak name="atas_perintah" x-model="f.atas_perintah" maxlength="150" class="input mt-2" placeholder="Atas perintah siapa? (mis. Manager, Pengamat, kesepakatan P3A)">
        </div>
    </fieldset>

    {{-- ================= C. Kondisi tanaman per petak ================= --}}
    <fieldset class="panel space-y-3 p-4 sm:p-5">
        <legend class="bagian float-left mb-1"><span class="bagian-huruf">C</span>Kondisi tanaman per petak tersier</legend>
        <p class="clear-left text-xs text-basalt-soft">Wajib untuk setiap petak tersier yang dilayani pintu tugas Anda. HST = hari setelah tanam. Status kekeringan dihitung dari <strong>kecukupan air</strong> petak terburuk.</p>
        @if($galatPetak->isNotEmpty())<p class="rounded-md bg-[#F6D9D7] px-3 py-2 text-sm font-medium text-[#7E1A14]">{{ $galatPetak->first() }}</p>@endif
        <template x-for="p in daftarPetak" :key="p.id">
            <div class="rounded-md border border-savana-line p-3" :class="p.saya && 'border-saluran-500'">
                <div class="flex flex-wrap items-baseline justify-between gap-x-2">
                    <p class="font-semibold"><span x-text="p.kode ? 'Petak ' + p.kode : p.nama"></span>
                        <span x-show="p.saya" class="ml-1 rounded bg-saluran-600 px-1.5 text-[10px] font-semibold text-white">tugas saya</span></p>
                    <span class="text-xs text-basalt-soft" x-text="[p.kode && p.nama ? p.nama : null, p.kode ? 'Pintu pelayan ' + p.kode : null, p.luas ? 'Luas baku ' + p.luas.toLocaleString('id-ID') + ' Ha' : null].filter(Boolean).join(' · ')"></span>
                </div>
                <div class="mt-2">
                    <span class="label text-xs">Jenis tanaman<span x-show="p.wajib" class="text-status-kritis"> *</span></span>
                    <div class="grid grid-cols-5 gap-1.5" role="group">
                        @foreach(K::JENIS_TANAMAN as $k => $l)
                            <label class="opsi px-1" title="{{ $l }}"><input type="radio" :name="`petak[${p.id}][jenis_tanaman]`" value="{{ $k }}" x-model="petak(p.id).jenis_tanaman"><span class="opsi-kode">{{ $k }}</span><span class="hidden sm:inline">{{ \Illuminate\Support\Str::before($l, ' (') }}</span></label>
                        @endforeach
                    </div>
                    <p class="mt-0.5 text-[11px] text-basalt-mute sm:hidden">P padi · J jagung · K kacang-kacangan · Pl palawija lain · B bera</p>
                </div>
                <div x-show="petak(p.id).jenis_tanaman && petak(p.id).jenis_tanaman !== 'B'" x-cloak class="mt-3 space-y-3">
                    <div class="grid grid-cols-2 gap-2 sm:grid-cols-[1fr_1fr_8rem] sm:gap-3">
                        <label><span class="label text-xs">Luas tanam (Ha) *</span>
                            <input type="number" step="0.01" min="0" inputmode="decimal" :name="`petak[${p.id}][luas_tanam_ha]`" x-model="petak(p.id).luas_tanam_ha" class="input py-2.5 tabular-nums"></label>
                        <label><span class="label text-xs">Luas terdampak kering (Ha)</span>
                            <input type="number" step="0.01" min="0" inputmode="decimal" :name="`petak[${p.id}][luas_kering_ha]`" x-model="petak(p.id).luas_kering_ha" class="input py-2.5 tabular-nums"></label>
                        <label class="col-span-2 sm:col-span-1"><span class="label text-xs">Umur (HST)</span>
                            <input type="number" step="1" min="0" max="365" inputmode="numeric" :name="`petak[${p.id}][umur_hst]`" x-model="petak(p.id).umur_hst" class="input py-2.5 tabular-nums"></label>
                    </div>
                    <p x-show="peringatanLuas(p)" class="text-xs font-medium text-status-siaga" x-text="peringatanLuas(p)"></p>
                    <label class="block"><span class="label text-xs">Fase tanaman *</span>
                        <select :name="`petak[${p.id}][fase]`" x-model="petak(p.id).fase" class="input py-2.5">
                            <option value="">Pilih fase</option>
                            @foreach(K::FASE as $k => $l)<option value="{{ $k }}">{{ $k }} · {{ $l }}</option>@endforeach
                        </select></label>
                    <div>
                        <span class="label text-xs">Kecukupan air *</span>
                        <div class="grid grid-cols-2 gap-1.5 sm:grid-cols-5" role="group">
                            @foreach(K::KECUKUPAN_AIR as $k => $l)
                                <label class="opsi flex-col items-start text-left sm:items-center sm:text-center"><input type="radio" :name="`petak[${p.id}][kecukupan_air]`" value="{{ $k }}" x-model="petak(p.id).kecukupan_air"><span class="opsi-kode">{{ $k }}</span><span class="text-[12px]">{{ $l }}</span></label>
                            @endforeach
                        </div>
                    </div>
                </div>
                <input x-show="petak(p.id).jenis_tanaman" :name="`petak[${p.id}][keterangan]`" x-model="petak(p.id).keterangan" maxlength="255" class="input mt-2 py-2 text-sm" placeholder="Keterangan petak (opsional, mis. hama, giliran air)">
                <div x-show="statusPetak(p.id)" class="mt-2 flex items-center gap-2 text-xs">
                    <span class="text-basalt-soft">Status petak:</span>
                    @foreach(\App\Support\StatusKondisi::LABEL as $k => $l)<span x-show="statusPetak(p.id) === '{{ $k }}'"><x-status :s="$k"/></span>@endforeach
                    <span x-show="petak(p.id).kecukupan_air === 'Bj'" class="chip-lebih">kelebihan air</span>
                </div>
            </div>
        </template>
        <button type="button" x-show="petakLain" x-cloak class="btn-ghost w-full py-2 text-sm" @click="tampilPetakLain = !tampilPetakLain"
                x-text="tampilPetakLain ? 'Sembunyikan petak lain' : 'Tampilkan ' + petakLain + ' petak lain yang belum ditugaskan'"></button>
        <div x-show="ringkas.jumlah" class="flex flex-wrap items-center gap-x-4 gap-y-1 rounded-md bg-savana px-3 py-2 text-sm">
            <span><span class="text-basalt-soft">Petak diisi</span> <strong x-text="ringkas.jumlah"></strong></span>
            <span><span class="text-basalt-soft">Luas tanam</span> <strong x-text="ringkas.tanam.toLocaleString('id-ID', { maximumFractionDigits: 2 }) + ' Ha'"></strong></span>
            <span><span class="text-basalt-soft">Kering</span> <strong x-text="ringkas.kering.toLocaleString('id-ID', { maximumFractionDigits: 2 }) + ' Ha'"></strong></span>
            <span class="flex items-center gap-1"><span class="text-basalt-soft">Perkiraan status</span>
                @foreach(\App\Support\StatusKondisi::LABEL as $k => $l)<span x-show="statusPratinjau === '{{ $k }}'"><x-status :s="$k"/></span>@endforeach</span>
        </div>
    </fieldset>

    {{-- ================= D. Permasalahan & dokumentasi ================= --}}
    <fieldset class="panel space-y-4 p-4 sm:p-5">
        <legend class="bagian float-left mb-1"><span class="bagian-huruf">D</span>Permasalahan dan dokumentasi</legend>
        <x-field name="kendala" label="Permasalahan di lapangan" class="clear-left">
            <textarea id="kendala" name="kendala" rows="3" x-model="f.kendala" class="input" placeholder="Kebocoran, sedimentasi, sampah, pengambilan air liar, konflik pembagian air, hama, dll."></textarea>
        </x-field>
        <x-field name="usulan" label="Usulan / tindak lanjut">
            <textarea id="usulan" name="usulan" rows="2" x-model="f.usulan" class="input"></textarea>
        </x-field>
        <div>
            <span class="label">Dokumentasi foto (centang yang sudah diambil)</span>
            <x-pilihan name="dokumentasi" jenis="checkbox" :opsi="K::DOKUMENTASI_PPA" model="f.dokumentasi" kolom="sm:grid-cols-2"/>
            <p class="mt-1 text-xs text-basalt-mute">Minimal 1 foto papan duga dan 1 foto kondisi sawah per petak, dengan GPS aktif.</p>
        </div>
        <div>
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
        <p class="rounded-md bg-savana px-3 py-2 text-xs text-basalt-soft">Pengesahan: laporan ini diperiksa dan disetujui (diketahui) oleh Manager / Pengamat D.I. melalui verifikasi di aplikasi.</p>
    </fieldset>

    {{-- ================= E. Kode isian ================= --}}
    <details class="panel p-4 text-sm sm:p-5">
        <summary class="bagian cursor-pointer"><span class="bagian-huruf">E</span>Kode isian</summary>
        <dl class="mt-3 space-y-2">
            @foreach(['Kondisi pintu' => K::KONDISI_PINTU, 'Kondisi saluran' => K::KONDISI_SALURAN, 'Jenis tanaman' => K::JENIS_TANAMAN, 'Fase tanaman' => K::FASE, 'Kecukupan air' => K::KECUKUPAN_AIR] as $judul => $daftar)
                <div><dt class="font-semibold">{{ $judul }}</dt><dd class="text-basalt-soft">{{ collect($daftar)->map(fn ($l, $k) => $k.' = '.$l)->implode(' · ') }}</dd></div>
            @endforeach
        </dl>
    </details>

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
