{{-- Peta bersama untuk halaman publik & internal. Variabel: $internal, $api --}}
<div x-data="peta({ internal: {{ $internal ? 'true' : 'false' }}, api: '{{ $api }}' })" class="relative flex h-full min-h-0">
    {{-- panel samping --}}
    <aside x-show="panel" x-cloak
           class="absolute inset-y-0 left-0 z-[500] flex w-[22rem] max-w-[90vw] flex-col border-r border-savana-line bg-white lg:static lg:z-auto">
        <div class="border-b border-savana-line p-4">
            <div class="flex items-start justify-between gap-2">
                <div>
                    <h2 class="text-xl font-semibold leading-tight">Kondisi Daerah Irigasi</h2>
                    <p class="text-xs text-basalt-soft">Dari laporan petugas 3 hari terakhir s.d. tanggal terpilih.</p>
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
                    <span x-text="fmt(rekap.kering, 1)"></span> Ha tanaman kekeringan, <span x-text="fmt(rekap.puso, 1)"></span> Ha puso
                    dari <span x-text="diTampil.length"></span> D.I. (<span x-text="fmt(rekap.luas)"></span> Ha).
                </p>
            </div>
        </div>

        <div class="space-y-3 border-b border-savana-line p-4 text-sm">
            <div class="grid grid-cols-2 gap-2">
                <label class="col-span-2"><span class="label">Tanggal</span>
                    <input type="date" x-model="tanggal" max="{{ now()->toDateString() }}" class="input"></label>
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

        <ul class="flex-1 divide-y divide-savana-line overflow-y-auto" aria-label="Daftar D.I.">
            <template x-for="d in [...diTampil].sort((a, b) => ({kritis:0,siaga:1,waspada:2,aman:3,kosong:4})[a.status] - ({kritis:0,siaga:1,waspada:2,aman:3,kosong:4})[b.status] || a.nama.localeCompare(b.nama))" :key="d.id">
                <li>
                    <button type="button" @click="fokus(d)" class="flex w-full items-center gap-3 px-4 py-2.5 text-left hover:bg-savana">
                        <span class="h-8 w-1.5 shrink-0 rounded-full" :style="`background:${d.warna}`"></span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-semibold" x-text="'D.I. ' + d.nama"></span>
                            <span class="block truncate text-xs text-basalt-soft" x-text="`${d.kabupaten ?? '-'} · ${d.sub_lapor}/${d.jumlah_sub} Sub-D.I. melapor`"></span>
                        </span>
                        <span class="text-right text-xs">
                            <span class="block font-semibold" x-text="d.status_label"></span>
                            <span class="block text-basalt-mute" x-text="fmt(d.luas_ha) + ' Ha'"></span>
                        </span>
                    </button>
                </li>
            </template>
            <li x-show="data && !diTampil.length" class="p-6 text-center text-sm text-basalt-soft">Tidak ada D.I. yang cocok dengan filter.</li>
        </ul>
    </aside>

    <div class="relative min-w-0 flex-1">
        <div x-ref="peta" class="absolute inset-0 z-0" role="application" aria-label="Peta Daerah Irigasi NTT"></div>
        <button type="button" x-show="!panel" @click="panel = true" class="absolute left-3 top-3 z-[400] btn-ghost shadow"><x-ikon n="filter" class="h-4 w-4"/> Daftar &amp; filter</button>
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
