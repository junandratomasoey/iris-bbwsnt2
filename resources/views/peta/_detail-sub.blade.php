{{-- Tampilan satu Sub-D.I. di panel peta (dibuka dari daftar Sub-D.I. atau batas areal di peta). Variabel: $internal --}}
<template x-if="detail && subAktif">
<div>
<template x-for="s in [subAktif]" :key="s.id">
<div>
    <div class="border-b border-savana-line p-4">
        <p class="text-xs font-medium uppercase tracking-wide text-basalt-mute" x-text="['Sub-D.I.', 'D.I. ' + detail.di.nama, detail.di.kabupaten].filter(Boolean).join(' · ')"></p>
        <h2 class="font-gauge text-[26px] font-semibold leading-tight" x-text="s.nama"></h2>
        <div class="mt-1.5 flex flex-wrap items-center gap-2 text-xs">
            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 font-semibold" :style="`background:${s.warna}22`"><span class="h-2 w-2 rounded-full" :style="`background:${s.warna}`"></span><span x-text="s.status_label"></span></span>
            <span class="text-basalt-soft" x-text="s.laporan ? 'Laporan PPA ' + s.laporan.tanggal : 'Belum ada laporan PPA 7 hari terakhir'"></span>
        </div>
        <p x-show="subCatatan" class="mt-2 rounded bg-[#FFF6DB] px-2.5 py-1.5 text-[11px] text-[#6B4E00]" x-text="subCatatan"></p>

        {{-- foto Sub-D.I.: foto profil, atau foto laporan PPA terakhir --}}
        <template x-for="daftar in [s.foto.length ? s.foto : s.foto_laporan]" :key="s.id + '-foto'">
            <figure x-show="daftar.length" class="mt-3">
                <div class="grid h-40 gap-1 overflow-hidden rounded-md" :class="daftar.length > 1 ? 'grid-cols-3 grid-rows-2' : 'grid-cols-1'">
                    <template x-for="(f, i) in daftar.slice(0, 3)" :key="f.besar">
                        <a :href="f.besar" :data-galeri="galeri(daftar)" :data-indeks="i"
                           class="relative block overflow-hidden bg-savana-deep"
                           :class="i === 0 ? (daftar.length > 1 ? 'col-span-2 row-span-2' : '') : (daftar.length === 2 ? 'row-span-2' : '')">
                            <img :src="i === 0 ? f.besar : f.kecil" :alt="f.label" class="h-full w-full object-cover transition hover:opacity-90" :loading="i === 0 ? 'eager' : 'lazy'">
                            <span x-show="i === 2 && daftar.length > 3" class="absolute inset-0 flex items-center justify-center bg-basalt/55 text-sm font-semibold text-white" x-text="'+' + (daftar.length - 3)"></span>
                        </a>
                    </template>
                </div>
                <figcaption class="mt-1 text-[11px] text-basalt-mute" x-text="(s.foto.length ? (daftar[0].judul || 'Foto Sub-D.I.') : 'Foto laporan PPA terakhir') + (daftar[0].tanggal ? ' · ' + daftar[0].tanggal : '')"></figcaption>
            </figure>
        </template>

        <p x-show="s.keterangan" class="mt-3 whitespace-pre-line text-sm text-basalt" x-text="s.keterangan"></p>

        {{-- luas & tanam --}}
        <dl class="mt-3 grid grid-cols-2 gap-px overflow-hidden rounded-md border border-savana-line bg-savana-line text-sm">
            <div class="bg-white px-3 py-2"><dt class="text-[11px] text-basalt-soft">Luas baku</dt><dd class="font-gauge text-xl font-semibold leading-tight"><span x-text="fmt(s.luas_ha)"></span> <span class="text-xs font-normal">Ha</span></dd></div>
            <div class="bg-white px-3 py-2"><dt class="text-[11px] text-basalt-soft">Luas fungsional</dt><dd class="font-gauge text-xl font-semibold leading-tight"><span x-text="s.luas_fungsional_ha === null ? '–' : fmt(s.luas_fungsional_ha)"></span> <span class="text-xs font-normal">Ha</span></dd></div>
            <div class="bg-white px-3 py-2"><dt class="text-[11px] text-basalt-soft">Tertanam (laporan terakhir)</dt><dd class="font-gauge text-xl font-semibold leading-tight"><span x-text="s.tertanam === null ? '–' : fmt(s.tertanam, 1)"></span> <span class="text-xs font-normal">Ha</span></dd></div>
            <div class="bg-white px-3 py-2"><dt class="text-[11px] text-basalt-soft" x-text="'% tertanam dari ' + (s.luas_fungsional_ha !== null ? 'fungsional' : 'baku')"></dt><dd class="font-gauge text-xl font-semibold leading-tight"><span x-text="s.persen_tertanam === null ? '–' : fmt(s.persen_tertanam, 1)"></span> <span class="text-xs font-normal">%</span></dd></div>
        </dl>
        <div x-show="s.persen_tertanam !== null" class="mt-1.5 h-2 overflow-hidden rounded-sm bg-savana-deep"><div class="h-full bg-[#4E8A2E]" :style="`width:${Math.min(100, s.persen_tertanam || 0)}%`"></div></div>
        <template x-if="s.laporan">
            <p class="mt-1.5 text-xs text-basalt-soft">Kekeringan <b class="text-basalt" x-text="fmt(s.laporan.kering, 1)"></b> Ha<span x-show="s.laporan.puso"> · puso <b class="text-basalt" x-text="fmt(s.laporan.puso, 1)"></b> Ha</span><span x-show="s.laporan.komoditas" x-text="' · ' + s.laporan.komoditas"></span></p>
        </template>

        <div class="mt-3 flex flex-wrap gap-3 text-sm font-semibold">
            <button type="button" @click="gambarSorotSub(s, true); if (window.innerWidth < 1024) panel = false" class="text-saluran-600">Arahkan peta ke Sub-D.I.</button>
            @if($internal)<a x-show="s.url" :href="s.url" class="text-saluran-600">Ubah data Sub-D.I.</a>@endif
        </div>
    </div>

    {{-- kondisi terkini per petak --}}
    <section class="border-b border-savana-line px-4 py-3 text-xs">
        <h3 class="text-sm font-semibold">Kondisi irigasi terkini</h3>
        @include('peta._kondisi-sub')
        @if($internal)
            <div x-show="s.petugas && s.petugas.length" class="mt-2 flex flex-wrap gap-1">
                <template x-for="p in (s.petugas || []).slice(0, 6)" :key="p.nama">
                    <a :href="p.wa || '#'" target="_blank" rel="noopener" :class="p.wa ? 'bg-[#E3F3E9] text-[#14713c]' : 'pointer-events-none bg-savana-deep text-basalt-soft'" class="rounded px-1.5 py-0.5 text-[11px] font-medium" x-text="p.nama + (p.wa ? ' · WA' : '')"></a>
                </template>
            </div>
        @endif
    </section>

    {{-- data teknis Sub-D.I. --}}
    <section class="px-4 py-3 text-xs">
        <h3 class="text-sm font-semibold">Data teknis Sub-D.I.</h3>
        <dl class="mt-2 space-y-1">
            <div class="flex justify-between gap-4"><dt class="shrink-0 text-basalt-soft">Kabupaten / kecamatan</dt><dd class="text-right" x-text="[detail.di.kabupaten, s.kecamatan].filter(Boolean).join(' / ') || '–'"></dd></div>
            <div class="flex justify-between gap-4"><dt class="shrink-0 text-basalt-soft">Lokasi</dt><dd class="text-right" x-text="s.lokasi || '–'"></dd></div>
            <div x-show="s.desa" class="flex justify-between gap-4"><dt class="shrink-0 text-basalt-soft">Desa dilayani</dt><dd class="text-right" x-text="s.desa"></dd></div>
            <div class="flex justify-between gap-4"><dt class="shrink-0 text-basalt-soft">Saluran</dt><dd class="text-right" x-text="[s.saluran, s.panjang_saluran_m ? fmt(s.panjang_saluran_m) + ' m' : null].filter(Boolean).join(' · ') || '–'"></dd></div>
            <div class="flex justify-between gap-4"><dt class="shrink-0 text-basalt-soft">Petak tersier</dt><dd class="text-right" x-text="s.jumlah_petak ? `${s.jumlah_petak} petak · ${fmt(s.luas_petak, 1)} Ha` : '–'"></dd></div>
            <div class="flex justify-between gap-4"><dt class="shrink-0 text-basalt-soft">Pintu air</dt><dd class="text-right" x-text="s.jumlah_pintu || '–'"></dd></div>
            <div class="flex justify-between gap-4"><dt class="shrink-0 text-basalt-soft">Bangunan</dt><dd class="text-right" x-text="s.bangunan.total ? `${s.bangunan.total} (${s.bangunan.baik} baik · ${s.bangunan.rusak} rusak)` : '–'"></dd></div>
            <div x-show="s.p3a.length" class="flex justify-between gap-4"><dt class="shrink-0 text-basalt-soft" x-text="`P3A (${s.p3a.length})`"></dt><dd class="text-right" x-text="s.p3a.join(', ')"></dd></div>
            <div class="flex justify-between gap-4"><dt class="shrink-0 text-basalt-soft">Batas areal di peta</dt><dd class="text-right" x-text="s.geometri ? 'Sudah digambar' : 'Belum digambar'"></dd></div>
        </dl>
    </section>
</div>
</template>
</div>
</template>
