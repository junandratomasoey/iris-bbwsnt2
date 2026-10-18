{{-- Peta bersama untuk halaman publik & internal. Variabel: $internal, $api --}}
<div x-data="peta({ internal: {{ $internal ? 'true' : 'false' }}, api: '{{ $api }}', apiDetail: '{{ $apiDetail }}' })" class="relative flex h-full min-h-0">
    {{-- panel samping: daftar & filter, atau detail D.I. terpilih --}}
    <aside x-show="panel" x-cloak x-ref="panel"
           :class="detailId ? 'w-full sm:w-[26rem]' : 'w-[22rem] max-w-[90vw]'"
           class="absolute inset-y-0 left-0 z-[500] flex shrink-0 flex-col overflow-y-auto border-r border-savana-line bg-white lg:static lg:z-auto">
        <div x-show="detailId" x-cloak>
            @include('peta._detail', ['internal' => $internal])
        </div>
        <div x-show="!detailId" class="contents">
        <div class="border-b border-savana-line p-4">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <h2 class="text-xl font-semibold leading-tight">Kondisi Daerah Irigasi</h2>
                    <p class="text-xs text-basalt-soft" x-show="!modeRekap">Dari laporan petugas 3 hari terakhir. Klik D.I. di peta atau daftar untuk foto, infrastruktur, dan riwayatnya.</p>
                    <p class="text-xs text-basalt-soft" x-show="modeRekap" x-cloak>Rekap <b x-text="data?.periode?.label"></b> (<span x-text="data?.periode?.keterangan"></span>). Warna = status <span x-text="warna"></span>. Klik D.I. untuk riwayat & foto.</p>
                </div>
                <button type="button" class="rounded p-1 hover:bg-savana lg:hidden" @click="panel = false" aria-label="Tutup panel"><x-ikon n="tutup"/></button>
            </div>

            {{-- papan duga: proporsi D.I. per status --}}
            <div class="mt-3" x-show="data">
                <div class="gauge-strip flex h-3 overflow-hidden rounded-sm bg-savana-deep" role="img" :aria-label="`${rekap.aman} aman, ${rekap.waspada} waspada, ${rekap.siaga} siaga, ${rekap.kritis} kritis, ${rekap.kosong} belum ada laporan`">
                    <template x-for="s in ['kritis','siaga','waspada','aman','kosong']" :key="s">
                        <div :style="`width:${diTampil.length ? rekap[s] / diTampil.length * 100 : 0}%; background:${data?.legenda?.[s]?.warna}`"></div>
                    </template>
                </div>
                <dl class="mt-2 grid grid-cols-5 gap-1 text-center">
                    <template x-for="s in ['kritis','siaga','waspada','aman','kosong']" :key="s">
                        <div>
                            <dd class="font-gauge text-2xl font-semibold leading-none" :style="`color:${s === 'kosong' ? '#4A585E' : data?.legenda?.[s]?.warna}`" x-text="rekap[s]"></dd>
                            <dt class="mt-0.5 text-[11px] leading-tight text-basalt-soft" x-text="s === 'kosong' ? 'Tanpa laporan' : data?.legenda?.[s]?.label"></dt>
                        </div>
                    </template>
                </dl>
                <p class="mt-2 text-xs text-basalt-soft">
                    <span x-text="fmt(rekap.kering, 1)"></span> Ha tanaman kekeringan, <span x-text="fmt(rekap.puso, 1)"></span> Ha puso<span x-show="modeRekap"> (puncak)</span>
                    dari <span x-text="diTampil.length"></span> D.I. (<span x-text="fmt(rekap.luas)"></span> Ha).
                </p>
            </div>
        </div>

        <div class="space-y-3 border-b border-savana-line p-4 text-sm">
            <div class="grid grid-cols-2 gap-2">
                <label class="col-span-2"><span class="label">Periode</span>
                    <select x-model="periode" class="input">
                        @foreach(\App\Support\Periode::PILIHAN as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach
                    </select></label>
                <template x-if="periode === 'rentang'">
                    <div class="col-span-2 grid grid-cols-2 gap-2">
                        <label><span class="label">Dari</span><input type="date" x-model="dari" max="{{ now()->toDateString() }}" class="input"></label>
                        <label><span class="label">Sampai</span><input type="date" x-model="sampai" max="{{ now()->toDateString() }}" class="input"></label>
                    </div>
                </template>
                <div x-show="modeRekap" x-cloak class="col-span-2">
                    <span class="label">Warna D.I. dalam periode</span>
                    <div class="grid grid-cols-2 rounded-md border border-savana-line p-0.5 text-xs" role="group">
                        <button type="button" @click="warna = 'terberat'" :class="warna === 'terberat' ? 'bg-basalt text-white' : 'text-basalt-soft'" class="rounded px-2 py-1 font-medium" title="Satu hari kritis sudah membuat D.I. merah">Terberat</button>
                        <button type="button" @click="warna = 'tersering'" :class="warna === 'tersering' ? 'bg-basalt text-white' : 'text-basalt-soft'" class="rounded px-2 py-1 font-medium" title="Status yang paling banyak harinya">Tersering</button>
                    </div>
                </div>
                <label><span class="label">Kabupaten</span>
                    <select x-model="kabupaten" class="input"><option value="">Semua</option>
                        @foreach($kabupatenList as $k)<option>{{ $k }}</option>@endforeach
                    </select></label>
                <label><span class="label">Satgas</span>
                    <select x-model="satgas" class="input"><option value="">Semua</option>
                        @foreach($satgasList as $s)<option value="{{ $s->kode }}">Wilayah {{ $s->kode }}</option>@endforeach
                    </select></label>
            </div>
            <fieldset>
                <legend class="label">Status</legend>
                <div class="flex flex-wrap gap-x-3 gap-y-1">
                    @foreach(\App\Support\StatusKondisi::LABEL as $k => $l)
                        <label class="flex items-center gap-1.5 text-xs"><input type="checkbox" x-model="status.{{ $k }}" class="rounded border-savana-line" style="color: {{ \App\Support\StatusKondisi::WARNA[$k] }}"> {{ $l }}</label>
                    @endforeach
                </div>
            </fieldset>
            <fieldset>
                <legend class="label">Lapisan peta</legend>
                <div class="grid grid-cols-2 gap-x-3 gap-y-1 text-xs">
                    <label class="flex items-center gap-1.5"><input type="checkbox" x-model="lapis.fungsional" class="rounded border-savana-line text-saluran-600"> Areal fungsional</label>
                    <label class="flex items-center gap-1.5"><input type="checkbox" x-model="lapis.baku" class="rounded border-savana-line text-saluran-600"> Luas baku</label>
                    <label class="flex items-center gap-1.5"><input type="checkbox" x-model="lapis.potensial" class="rounded border-savana-line text-saluran-600"> Areal potensial</label>
                    <label class="flex items-center gap-1.5"><input type="checkbox" x-model="lapis.kejadian" class="rounded border-savana-line text-saluran-600"> Titik kejadian</label>
                    @if($internal)
                        <label class="flex items-center gap-1.5"><input type="checkbox" x-model="lapis.sub" class="rounded border-savana-line text-saluran-600"> Sub-D.I.</label>
                        <label class="flex items-center gap-1.5"><input type="checkbox" x-model="lapis.bangunan" class="rounded border-savana-line text-saluran-600"> Bangunan</label>
                    @endif
                </div>
            </fieldset>
        </div>

        {{-- pencarian + tab daftar (menempel di atas saat panel digulir) --}}
        <div class="sticky top-0 z-10 border-b border-savana-line bg-white px-4 pb-0 pt-3">
            <label class="relative block">
                <span class="sr-only">Cari</span>
                <svg class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-basalt-mute" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input type="search" x-model="cari" class="input pl-8" :placeholder="tab === 'di' ? 'Cari D.I. atau kabupaten' : 'Cari desa, kecamatan, kabupaten, jenis…'" autocomplete="off">
            </label>
            <div class="mt-2 flex" role="tablist">
                <button type="button" role="tab" :aria-selected="tab === 'di'" @click="tab = 'di'" :class="tab === 'di' ? 'border-saluran-600 text-saluran-700' : 'border-transparent text-basalt-soft'" class="-mb-px flex-1 border-b-2 py-2 text-sm font-semibold">
                    Daerah Irigasi <span class="ml-0.5 rounded-full bg-savana-deep px-1.5 text-xs" x-text="diDaftar.length"></span>
                </button>
                <button type="button" role="tab" :aria-selected="tab === 'kejadian'" @click="tab = 'kejadian'" :class="tab === 'kejadian' ? 'border-saluran-600 text-saluran-700' : 'border-transparent text-basalt-soft'" class="-mb-px flex-1 border-b-2 py-2 text-sm font-semibold">
                    Kejadian <span class="ml-0.5 rounded-full bg-savana-deep px-1.5 text-xs" x-text="kejadianTampil.length"></span>
                </button>
            </div>
        </div>

        <ul x-show="tab === 'di'" class="divide-y divide-savana-line" aria-label="Daftar D.I.">
            <template x-for="d in diDaftar" :key="d.id">
                <li>
                    <button type="button" @click="fokus(d)" class="flex w-full items-center gap-3 px-4 py-2.5 text-left hover:bg-savana">
                        <span class="h-8 w-1.5 shrink-0 rounded-full" :style="`background:${d.warna}`"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold" x-text="'D.I. ' + d.nama"></span>
                            <span class="block truncate text-xs text-basalt-soft" x-text="`${d.kabupaten ?? '-'} · ` + (d.keterangan ?? `${d.sub_lapor}/${d.jumlah_sub} Sub-D.I. melapor`)"></span>
                        </span>
                        <span class="text-right text-xs">
                            <span class="block font-semibold" x-text="d.status_label"></span>
                            <span class="block text-basalt-mute" x-text="fmt(d.luas_ha) + ' Ha'"></span>
                        </span>
                    </button>
                </li>
            </template>
            <li x-show="data && !diDaftar.length" class="p-6 text-center text-sm text-basalt-soft">Tidak ada D.I. yang cocok.</li>
        </ul>

        <div x-show="tab === 'kejadian'" x-cloak>
            <div class="flex items-center gap-2 px-4 py-2 text-xs">
                <select x-model="statusKejadian" class="input py-1 text-xs" aria-label="Status penanganan">
                    <option value="">Semua status penanganan</option>
                    @foreach(array_keys(\App\Models\Kejadian::STATUS) as $st)<option>{{ $st }}</option>@endforeach
                </select>
            </div>
            <p class="px-4 pb-2 text-xs text-basalt-soft"><span x-text="kejadianTampil.length"></span> lokasi · <span x-text="fmt(luasKejadian, 1)"></span> Ha terdampak</p>
            <ul class="divide-y divide-savana-line border-t border-savana-line" aria-label="Daftar kejadian">
                <template x-for="k in kejadianTampil" :key="k.id">
                    <li>
                        <button type="button" @click="fokusKejadian(k)" class="flex w-full items-start gap-3 px-4 py-2.5 text-left hover:bg-savana">
                            <template x-if="k.foto && k.foto.length">
                                <img :src="k.foto[0].kecil" alt="" loading="lazy" class="h-12 w-14 shrink-0 rounded object-cover">
                            </template>
                            <template x-if="!k.foto || !k.foto.length">
                                <span class="flex h-12 w-14 shrink-0 items-center justify-center rounded bg-savana-deep">
                                    <svg width="16" height="16" viewBox="0 0 18 18" aria-hidden="true"><path d="M9 1.5 17 16H1z" :fill="k.warna"/></svg>
                                </span>
                            </template>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold" x-text="`${k.jenis} — ${k.desa || k.kecamatan || k.kabupaten}`"></span>
                                <span class="block truncate text-xs text-basalt-soft" x-text="[k.kecamatan, k.kabupaten].filter(Boolean).join(', ') + (k.di ? ' · D.I. ' + k.di : '')"></span>
                                <span class="mt-0.5 flex items-center gap-1.5 text-xs">
                                    <span class="h-2 w-2 rounded-full" :style="`background:${k.warna}`"></span>
                                    <span x-text="k.status"></span>
                                    <span class="text-basalt-mute" x-text="'· ' + fmt(k.luas_terdampak_ha, 1) + ' Ha'"></span>
                                </span>
                            </span>
                        </button>
                    </li>
                </template>
                <li x-show="data && !kejadianTampil.length" class="p-6 text-center text-sm text-basalt-soft">Tidak ada kejadian yang cocok.</li>
            </ul>
        </div>
        </div>
    </aside>

    <div class="relative min-w-0 flex-1">
        <div x-ref="peta" class="absolute inset-0 z-0" role="application" aria-label="Peta Daerah Irigasi NTT"></div>
        <button type="button" x-show="!panel" @click="panel = true" class="absolute left-3 top-3 z-[400] btn-ghost max-w-[60vw] shadow"><x-ikon n="filter" class="h-4 w-4 shrink-0"/> <span class="truncate" x-text="diPilih ? 'Detail D.I. ' + diPilih.nama : 'Daftar & filter'">Daftar &amp; filter</span></button>
        <div x-show="memuat" class="absolute left-1/2 top-3 z-[400] -translate-x-1/2 rounded-full bg-white px-3 py-1 text-xs font-medium shadow">Memuat data…</div>
        <div x-show="galat" x-cloak class="absolute left-1/2 top-3 z-[400] -translate-x-1/2 rounded-md bg-[#FBECEA] px-3 py-2 text-xs font-medium text-[#7E1A14] shadow" x-text="galat"></div>
        <div class="absolute right-3 top-3 z-[400] hidden rounded-md bg-white/95 px-3 py-2 text-xs shadow sm:block">
            <p class="mb-1 font-semibold">Legenda</p>
            @foreach(\App\Support\StatusKondisi::LABEL as $k => $l)
                <p class="flex items-center gap-1.5"><span class="h-2.5 w-3.5 rounded-sm" style="background: {{ \App\Support\StatusKondisi::WARNA[$k] }}"></span>{{ $l }}</p>
            @endforeach
            <p class="mt-1 flex items-center gap-1.5"><svg width="12" height="12" viewBox="0 0 18 18"><path d="M9 1.5 17 16H1z" fill="#B3261E"/></svg>Kejadian kekeringan</p>
            @if($internal)<p class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full border-2 border-white bg-status-aman ring-1 ring-basalt-mute"></span>Sub-D.I.</p>@endif
        </div>
    </div>
</div>
