{{-- Panel detail D.I. (bagian dari komponen peta). Variabel: $internal --}}
<div class="sticky top-0 z-20 flex items-center justify-between gap-2 border-b border-savana-line bg-white/95 px-2 py-1.5 backdrop-blur">
    <button type="button" @click="tutupDetail()" class="flex items-center gap-1 rounded px-2 py-1.5 text-sm font-semibold text-saluran-700 hover:bg-savana">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg> Semua D.I.
    </button>
    <button type="button" @click="panel = false" class="rounded px-2 py-1.5 text-sm font-medium text-basalt-soft hover:bg-savana lg:hidden">Lihat peta</button>
</div>

<template x-if="diPilih">
<div class="border-b border-savana-line p-4">
    <p class="text-xs font-medium uppercase tracking-wide text-basalt-mute" x-text="[diPilih.kode, diPilih.kabupaten, diPilih.satgas ? 'Satgas ' + diPilih.satgas : null].filter(Boolean).join(' · ')"></p>
    <h2 class="font-gauge text-[26px] font-semibold leading-tight" x-text="'D.I. ' + diPilih.nama"></h2>
    <div class="mt-1.5 flex flex-wrap items-center gap-2 text-xs">
        <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 font-semibold" :style="`background:${diPilih.warna}22`"><span class="h-2 w-2 rounded-full" :style="`background:${diPilih.warna}`"></span><span x-text="diPilih.status_label"></span></span>
        <span class="text-basalt-soft" x-text="modeRekap ? `status ${warna} · ${data?.periode?.label}` : (diPilih.mt ? diPilih.mt + ' berjalan' : 'MT belum dijadwalkan')"></span>
    </div>
    {{-- foto profil / kondisi awal D.I. (walkthrough, ePAKSI): 1 foto utama + maks. 2 kecil --}}
    <template x-if="detail && detail.profil && detail.profil.length">
        <figure class="mt-3">
            <div class="grid h-40 gap-1 overflow-hidden rounded-md" :class="detail.profil.length > 1 ? 'grid-cols-3 grid-rows-2' : 'grid-cols-1'">
                <template x-for="(f, i) in detail.profil" :key="f.besar">
                    <a :href="f.besar" :data-galeri="galeri(detail.profil)" :data-indeks="i"
                       class="relative block overflow-hidden bg-savana-deep"
                       :class="i === 0 ? (detail.profil.length > 1 ? 'col-span-2 row-span-2' : '') : (detail.profil.length === 2 ? 'row-span-2' : '')">
                        <img :src="i === 0 ? f.besar : f.kecil" :alt="f.label" class="h-full w-full object-cover transition hover:opacity-90" :loading="i === 0 ? 'eager' : 'lazy'">
                    </a>
                </template>
            </div>
            <figcaption class="mt-1 text-[11px] text-basalt-mute" x-text="'Kondisi awal · ' + (detail.profil[0].judul || 'foto profil D.I.') + (detail.profil[0].tanggal ? ' · ' + detail.profil[0].tanggal : '')"></figcaption>
        </figure>
    </template>
    <dl class="mt-3 grid grid-cols-2 gap-px overflow-hidden rounded-md border border-savana-line bg-savana-line text-sm">
        <div class="bg-white px-3 py-2"><dt class="text-[11px] text-basalt-soft">Luas fungsional</dt><dd class="font-gauge text-xl font-semibold leading-tight"><span x-text="fmt(diPilih.luas_ha)"></span> <span class="text-xs font-normal">Ha</span></dd></div>
        <div class="bg-white px-3 py-2" x-show="!modeRekap"><dt class="text-[11px] text-basalt-soft">Sub-D.I. melapor (3 hari)</dt><dd class="font-gauge text-xl font-semibold leading-tight"><span x-text="diPilih.sub_lapor"></span><span class="text-sm font-normal text-basalt-soft" x-text="' / ' + diPilih.jumlah_sub"></span></dd></div>
        <div class="bg-white px-3 py-2" x-show="modeRekap"><dt class="text-[11px] text-basalt-soft">Hari kritis / siaga · lapor</dt><dd class="font-gauge text-xl font-semibold leading-tight"><span x-text="diPilih.hari?.kritis ?? 0"></span><span class="text-sm font-normal text-basalt-soft" x-text="` / ${diPilih.hari?.siaga ?? 0} · ${diPilih.kepatuhan ?? 0}%`"></span></dd></div>
        <div class="bg-white px-3 py-2"><dt class="text-[11px] text-basalt-soft" x-text="modeRekap ? 'Tanaman kekeringan (puncak)' : 'Tanaman kekeringan'">Tanaman kekeringan</dt><dd class="font-gauge text-xl font-semibold leading-tight" :class="diPilih.luas_kering_ha > 0 && 'text-status-siaga'"><span x-text="fmt(diPilih.luas_kering_ha, 1)"></span> <span class="text-xs font-normal">Ha</span></dd></div>
        <div class="bg-white px-3 py-2"><dt class="text-[11px] text-basalt-soft" x-text="modeRekap ? 'Puso (puncak)' : 'Puso'">Puso</dt><dd class="font-gauge text-xl font-semibold leading-tight" :class="diPilih.luas_puso_ha > 0 && 'text-status-kritis'"><span x-text="fmt(diPilih.luas_puso_ha, 1)"></span> <span class="text-xs font-normal">Ha</span></dd></div>
    </dl>
</div>
</template>

<div x-show="detailMemuat && !detail" class="p-6 text-center text-sm text-basalt-soft">Memuat detail…</div>
<div x-show="detailGalat" x-cloak class="m-4 rounded-md bg-[#FBECEA] px-3 py-2 text-sm text-[#7E1A14]"><span x-text="detailGalat"></span> <button type="button" class="font-semibold underline" @click="bukaDetail(detailId, { terbang: false })">Coba lagi</button></div>

<template x-if="detail">
<div>
    {{-- riwayat 30 hari: satu kotak per hari, warna = status terberat hari itu --}}
    <section class="border-b border-savana-line px-4 py-3" aria-labelledby="judul-riwayat">
        <div class="flex items-baseline justify-between">
            <h3 id="judul-riwayat" class="text-sm font-semibold" x-text="detail.riwayat_judul || 'Riwayat 30 hari'">Riwayat 30 hari</h3>
            <span class="text-[11px] text-basalt-mute">ketuk kotak bertanda foto</span>
        </div>
        <div class="mt-2 flex gap-[2px]" role="list">
            <template x-for="h in detail.riwayat" :key="h.tanggal">
                <button type="button" role="listitem" @click="keTanggal(h)" :disabled="!h.foto"
                        class="relative h-7 flex-1 rounded-[2px] disabled:cursor-default"
                        :style="`background:${h.laporan ? h.warna : '#E7ECE8'}`"
                        :title="`${h.label}: ${h.laporan ? h.status_label + ' · ' + h.laporan + ' laporan' : 'tidak ada laporan'}${h.foto ? ' · ' + h.foto + ' foto' : ''}`"
                        :aria-label="`${h.label}: ${h.laporan ? h.status_label : 'tidak ada laporan'}${h.foto ? ', ' + h.foto + ' foto' : ''}`">
                    <span x-show="h.foto" class="absolute bottom-0.5 left-1/2 h-1.5 w-1.5 -translate-x-1/2 rounded-full bg-white ring-1 ring-black/20"></span>
                </button>
            </template>
        </div>
        <div class="mt-1 flex justify-between text-[10px] text-basalt-mute"><span x-text="detail.riwayat[0]?.label"></span><span x-text="detail.riwayat[detail.riwayat.length - 1]?.label"></span></div>
        <template x-if="detail.jadwal.length">
            <div class="mt-3 flex flex-wrap gap-1.5">
                <template x-for="j in detail.jadwal" :key="j.label">
                    <span class="rounded border px-2 py-0.5 text-[11px]" :class="j.berjalan ? 'border-saluran-600 bg-saluran-600 text-white' : (j.lewat ? 'border-savana-line text-basalt-mute' : 'border-savana-line')"
                          x-text="`${j.label}: ${j.mulai} – ${j.selesai}${j.komoditas ? ' · ' + j.komoditas : ''}`"></span>
                </template>
            </div>
        </template>
    </section>

    {{-- tab --}}
    <div class="sticky top-[45px] z-10 flex overflow-x-auto border-b border-savana-line bg-white px-1" role="tablist">
        @foreach(['foto' => 'Foto', 'sub' => 'Sub-D.I.', 'infra' => 'Infrastruktur', 'kejadian' => 'Kejadian'] as $k => $l)
            <button type="button" role="tab" :aria-selected="detailTab === '{{ $k }}'" @click="detailTab = '{{ $k }}'"
                    :class="detailTab === '{{ $k }}' ? 'border-saluran-600 text-saluran-700' : 'border-transparent text-basalt-soft'"
                    class="-mb-px flex-1 shrink-0 whitespace-nowrap border-b-2 px-1.5 py-2 text-[13px] font-semibold">{{ $l }}
                <span class="ml-0.5 rounded-full bg-savana-deep px-1 text-[11px]" x-text="{ foto: jumlahFoto, sub: detail.sub.length, infra: detail.bangunan.length, kejadian: detail.kejadian.length }['{{ $k }}']"></span></button>
        @endforeach
    </div>

    {{-- FOTO per tanggal laporan --}}
    <section x-show="detailTab === 'foto'" class="pb-4">
        <template x-for="g in detail.foto_harian" :key="g.tanggal">
            <div :id="'foto-' + g.tanggal" class="scroll-mt-24 border-b border-savana-line px-4 py-3">
                <h3 class="flex items-baseline justify-between gap-2">
                    <span class="font-gauge text-lg font-semibold leading-tight" x-text="g.label"></span>
                    <span class="shrink-0 text-xs text-basalt-mute" x-text="g.semua.length + ' foto'"></span>
                </h3>
                <template x-for="l in g.laporan" :key="l.id">
                    <div class="mt-2">
                        <p class="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs">
                            <span class="inline-flex items-center gap-1 font-semibold text-basalt"><span class="h-2 w-2 rounded-full" :style="`background:${l.warna}`"></span><span x-text="l.sub"></span></span>
                            <span class="text-basalt-soft" x-text="['Saluran ' + (l.kondisi || '').toLowerCase(), l.fase].filter(Boolean).join(' · ')"></span>
                            <span x-show="!l.terverifikasi" class="rounded bg-savana-deep px-1.5 text-[10px] text-basalt-soft">belum diverifikasi</span>
                        </p>
                        @if($internal)
                            <p class="text-[11px] text-basalt-mute"><span x-text="`Debit ${fmt(l.debit, 1)} L/dt · TMA ${fmt(l.tma, 1)} cm · ${l.pelapor ?? ''}`"></span> · <a :href="l.url" class="font-semibold text-saluran-600">Detail</a></p>
                        @endif
                        <p x-show="l.komoditas" class="text-[11px] text-basalt-soft" x-text="l.komoditas"></p>
                        <p x-show="l.kendala" class="mt-0.5 text-xs italic text-basalt-soft" x-text="l.kendala"></p>
                        <div class="mt-1.5 grid grid-cols-3 gap-1">
                            <template x-for="f in l.foto" :key="f.besar">
                                <a :href="f.besar" :data-galeri="g.galeri" :data-indeks="f.n" class="block overflow-hidden rounded bg-savana-deep">
                                    <img :src="f.kecil" :alt="'Foto ' + f.label" loading="lazy" class="aspect-[4/3] w-full object-cover transition hover:opacity-90">
                                </a>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </template>
        <div x-show="!detail.foto_harian.length" class="px-4 py-8 text-center text-sm text-basalt-soft">Belum ada foto laporan dalam 30 hari terakhir.</div>
        <div x-show="detail.foto_sebelum" class="px-4 pt-3">
            <button type="button" @click="muatFotoLama()" :disabled="memuatLama" class="btn-ghost w-full justify-center" x-text="memuatLama ? 'Memuat…' : 'Muat foto lebih lama'"></button>
        </div>
    </section>

    {{-- SUB-D.I. --}}
    <ul x-show="detailTab === 'sub'" x-cloak class="divide-y divide-savana-line" aria-label="Kondisi Sub-D.I.">
        <template x-for="s in detail.sub" :key="s.id">
            <li class="flex gap-3 px-4 py-3">
                <span class="w-1.5 shrink-0 rounded-full" :style="`background:${s.warna}`"></span>
                <div class="min-w-0 flex-1 text-xs">
                    <div class="flex items-baseline justify-between gap-2">
                        <p class="truncate text-sm font-semibold" x-text="s.nama"></p>
                        <span class="shrink-0 font-semibold" :style="`color:${s.status === 'kosong' ? '#4A585E' : s.warna}`" x-text="s.status_label"></span>
                    </div>
                    <p class="text-basalt-mute" x-text="[s.lokasi, fmt(s.luas_ha) + ' Ha'].filter(Boolean).join(' · ')"></p>
                    <template x-if="s.laporan">
                        <div class="mt-1 text-basalt-soft">
                            <p x-text="`${s.laporan.tanggal} · saluran ${String(s.laporan.kondisi).toLowerCase()}${s.laporan.fase ? ' · ' + s.laporan.fase : ''}`"></p>
                            <p x-show="s.laporan.komoditas" x-text="s.laporan.komoditas"></p>
                            <p>Tanam <b class="text-basalt" x-text="fmt(s.laporan.tanam, 1)"></b> · kering <b class="text-basalt" x-text="fmt(s.laporan.kering, 1)"></b> · puso <b class="text-basalt" x-text="fmt(s.laporan.puso, 1)"></b> Ha</p>
                            @if($internal)<p>Debit <b class="text-basalt" x-text="fmt(s.laporan.debit, 1)"></b> L/dt · TMA <b class="text-basalt" x-text="fmt(s.laporan.tma, 1)"></b> cm · <a :href="s.laporan.url" class="font-semibold text-saluran-600">Laporan</a></p>@endif
                        </div>
                    </template>
                    <p x-show="!s.laporan" class="mt-1 text-basalt-mute">Belum ada laporan 3 hari terakhir.</p>
                    @if($internal)
                        <div x-show="s.petugas.length" class="mt-1.5 flex flex-wrap gap-1">
                            <template x-for="p in s.petugas.slice(0, 4)" :key="p.nama">
                                <a :href="p.wa || '#'" target="_blank" rel="noopener" :class="p.wa ? 'bg-[#E3F3E9] text-[#14713c]' : 'pointer-events-none bg-savana-deep text-basalt-soft'" class="rounded px-1.5 py-0.5 text-[11px] font-medium" x-text="p.nama + (p.wa ? ' · WA' : '')"></a>
                            </template>
                        </div>
                    @endif
                </div>
            </li>
        </template>
    </ul>

    {{-- INFRASTRUKTUR --}}
    <section x-show="detailTab === 'infra'" x-cloak>
        <div x-show="rekapInfra.total" class="border-b border-savana-line px-4 py-3">
            <div class="flex h-2.5 overflow-hidden rounded-sm bg-savana-deep">
                @foreach(\App\Models\Bangunan::KONDISI as $k => $l)<div :style="`width:${rekapInfra.{{ $k }} / rekapInfra.total * 100}%;background:{{ \App\Models\Bangunan::WARNA_KONDISI[$k] }}`"></div>@endforeach
            </div>
            <p class="mt-1.5 flex flex-wrap gap-x-3 text-[11px] text-basalt-soft">
                @foreach(\App\Models\Bangunan::KONDISI as $k => $l)<span x-show="rekapInfra.{{ $k }}"><b class="text-basalt" x-text="rekapInfra.{{ $k }}"></b> {{ strtolower($l) }}</span>@endforeach
                <span x-show="rekapInfra.belum"><b class="text-basalt" x-text="rekapInfra.belum"></b> belum dinilai</span>
            </p>
            <p x-show="rekapInfra.titik < rekapInfra.total" class="mt-1 text-[11px] text-basalt-mute"><span x-text="rekapInfra.total - rekapInfra.titik"></span> belum punya titik di peta.</p>
        </div>
        <ul class="divide-y divide-savana-line">
            <template x-for="b in detail.bangunan" :key="b.id">
                <li class="flex gap-3 px-4 py-3 text-xs">
                    <template x-if="b.foto.length">
                        <a :href="b.foto[0].besar" :data-galeri="galeri(b.foto)" data-indeks="0" class="relative block h-14 w-16 shrink-0 overflow-hidden rounded bg-savana-deep">
                            <img :src="b.foto[0].kecil" alt="" loading="lazy" class="h-full w-full object-cover">
                            <span x-show="b.foto.length > 1" class="absolute bottom-0.5 right-0.5 rounded bg-basalt/75 px-1 text-[10px] text-white" x-text="b.foto.length"></span>
                        </a>
                    </template>
                    <template x-if="!b.foto.length">
                        <span class="flex h-14 w-16 shrink-0 items-center justify-center rounded bg-savana-deep text-basalt-mute"><x-ikon n="bangunan" class="h-6 w-6"/></span>
                    </template>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold leading-snug" x-text="b.nama"></p>
                        <p class="text-basalt-mute" x-text="[b.jenis, b.sub, b.lokasi].filter(Boolean).join(' · ')"></p>
                        <p class="mt-0.5 flex items-center gap-1.5"><span class="h-2 w-2 rounded-full" :style="`background:${b.warna}`"></span><span class="font-medium" x-text="b.kondisi_label"></span><span class="text-basalt-mute" x-show="b.kondisi_per" x-text="'per ' + b.kondisi_per"></span></p>
                        <p x-show="b.keterangan" class="mt-0.5 text-basalt-soft" x-text="b.keterangan"></p>
                        <details x-show="b.teknis.length" class="mt-1 group/t">
                            <summary class="cursor-pointer select-none font-semibold text-saluran-600">Data teknis</summary>
                            <dl class="mt-1 grid grid-cols-[auto_1fr] gap-x-3 gap-y-0.5 rounded bg-savana px-2 py-1.5">
                                <template x-for="t in b.teknis" :key="t.label">
                                    <div class="contents"><dt class="text-basalt-mute" x-text="t.label"></dt><dd class="text-basalt" x-text="t.nilai"></dd></div>
                                </template>
                            </dl>
                        </details>
                        <p class="mt-1 flex gap-3">
                            <button type="button" x-show="b.lat" @click="fokusBangunan(b)" class="font-semibold text-saluran-600">Lihat di peta</button>
                            @if($internal)<a x-show="b.url" :href="b.url" class="font-semibold text-saluran-600">Ubah</a>@endif
                        </p>
                    </div>
                </li>
            </template>
        </ul>
        <div x-show="!detail.bangunan.length" class="px-4 py-8 text-center text-sm text-basalt-soft">Belum ada data infrastruktur.</div>
    </section>

    {{-- KEJADIAN --}}
    <ul x-show="detailTab === 'kejadian'" x-cloak class="divide-y divide-savana-line">
        <template x-for="k in detail.kejadian" :key="k.id">
            <li>
                <button type="button" @click="k.lat && fokusKejadian(k)" class="flex w-full items-start gap-3 px-4 py-2.5 text-left hover:bg-savana" :class="!k.lat && 'cursor-default'">
                    <template x-if="k.foto"><img :src="k.foto.kecil" alt="" loading="lazy" class="h-12 w-14 shrink-0 rounded object-cover"></template>
                    <template x-if="!k.foto"><span class="flex h-12 w-14 shrink-0 items-center justify-center rounded bg-savana-deep"><svg width="16" height="16" viewBox="0 0 18 18" aria-hidden="true"><path d="M9 1.5 17 16H1z" :fill="k.warna"/></svg></span></template>
                    <span class="min-w-0 flex-1 text-xs">
                        <span class="block truncate text-sm font-semibold" x-text="`${k.jenis} — ${k.desa || k.kecamatan || '-'}`"></span>
                        <span class="block text-basalt-mute" x-text="[k.tanggal, fmt(k.luas_terdampak_ha, 1) + ' Ha'].filter(Boolean).join(' · ')"></span>
                        <span class="mt-0.5 flex items-center gap-1.5"><span class="h-2 w-2 rounded-full" :style="`background:${k.warna}`"></span><span x-text="k.status"></span></span>
                    </span>
                </button>
            </li>
        </template>
        <li x-show="!detail.kejadian.length" class="px-4 py-8 text-center text-sm text-basalt-soft">Tidak ada catatan kejadian kekeringan.</li>
    </ul>

    {{-- profil teknis --}}
    <details class="group border-t border-savana-line px-4 py-3 text-sm">
        <summary class="cursor-pointer select-none font-semibold">Data teknis D.I.</summary>
        <dl class="mt-2 space-y-1 text-xs">
            <div class="flex justify-between"><dt class="text-basalt-soft">Luas Permen</dt><dd><span x-text="fmt(detail.di.luas.permen)"></span> Ha</dd></div>
            <div class="flex justify-between"><dt class="text-basalt-soft">Luas baku</dt><dd><span x-text="fmt(detail.di.luas.baku)"></span> Ha</dd></div>
            <div class="flex justify-between"><dt class="text-basalt-soft">Luas fungsional</dt><dd><span x-text="fmt(detail.di.luas.fungsional)"></span> Ha</dd></div>
            <div class="flex justify-between"><dt class="text-basalt-soft">Luas potensial</dt><dd><span x-text="fmt(detail.di.luas.potensial)"></span> Ha</dd></div>
            <div class="flex justify-between"><dt class="text-basalt-soft">Kewenangan</dt><dd x-text="detail.di.kewenangan || '–'"></dd></div>
            <div x-show="detail.di.sumber_air" class="flex justify-between gap-4"><dt class="text-basalt-soft">Sumber air</dt><dd class="text-right" x-text="detail.di.sumber_air"></dd></div>
            <div x-show="detail.di.keterangan" class="flex justify-between gap-4"><dt class="text-basalt-soft">Keterangan</dt><dd class="text-right" x-text="detail.di.keterangan"></dd></div>
            <div x-show="detail.di.tenaga_op.length" class="flex justify-between gap-4"><dt class="text-basalt-soft">Tenaga O&amp;P</dt><dd class="text-right" x-text="detail.di.tenaga_op.map((t) => t.label + ' ' + t.n).join(', ')"></dd></div>
            <div x-show="detail.p3a.length" class="flex justify-between gap-4"><dt class="shrink-0 text-basalt-soft" x-text="`P3A (${detail.p3a.length})`"></dt><dd class="text-right" x-text="detail.p3a.join(', ')"></dd></div>
        </dl>
        @if($internal)<a x-show="detail.di.url" :href="detail.di.url" class="mt-3 inline-block text-sm font-semibold text-saluran-600">Buka profil D.I. lengkap</a>@endif
    </details>
</div>
</template>
