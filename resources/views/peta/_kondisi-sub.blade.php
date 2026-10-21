{{-- Kondisi terkini satu Sub-D.I. (variabel Alpine: s). Dipakai di daftar Sub-D.I. & tampilan Sub-D.I. --}}
    <template x-if="s.laporan">
        <div class="mt-1 text-basalt-soft">
            <p x-text="[s.laporan.tanggal, s.laporan.kondisi ? 'saluran ' + String(s.laporan.kondisi).toLowerCase() : null, s.laporan.kekurangan_air ? s.laporan.kekurangan_air + ' petak kurang air' : null].filter(Boolean).join(' · ')"></p>
            <p x-show="s.laporan.komoditas" x-text="s.laporan.komoditas"></p>
            <p>Tanam <b class="text-basalt" x-text="fmt(s.laporan.tanam, 1)"></b> · kering <b class="text-basalt" x-text="fmt(s.laporan.kering, 1)"></b><span x-show="s.laporan.puso"> · puso <b class="text-basalt" x-text="fmt(s.laporan.puso, 1)"></b></span> Ha</p>
            @if($internal)<p x-show="!(s.laporan.petak || []).length && s.laporan.debit !== null">Debit <b class="text-basalt" x-text="fmt(s.laporan.debit, 1)"></b> L/dt · TMA <b class="text-basalt" x-text="fmt(s.laporan.tma, 1)"></b> cm · <a :href="s.laporan.url" class="font-semibold text-saluran-600">Laporan</a></p>@endif
            <template x-if="(s.laporan.petak || []).length">
                <ul class="mt-1.5 space-y-1 border-l-2 border-savana-line pl-2">
                    <template x-for="pt in s.laporan.petak" :key="pt.kode || 'sub'">
                        <li class="flex items-baseline justify-between gap-2">
                            <span><span class="mr-1 inline-block h-2 w-2 rounded-full" :style="`background:${pt.warna}`"></span><b class="text-basalt" x-text="pt.kode ? 'Petak ' + pt.kode : (pt.nama || 'Seluruh Sub-D.I.')"></b>
                                <span x-text="` · ${pt.tanggal}` + (pt.jenis ? ` · ${pt.jenis}` : '') + (pt.umur !== null && pt.umur !== undefined ? ` ${pt.umur} HST` : '') + (pt.kecukupan ? ` · air ${pt.kecukupan}` : '') + ` · kering ${fmt(pt.kering, 1)}/${fmt(pt.tanam, 1)} Ha` + (pt.puso ? ` · puso ${fmt(pt.puso, 1)}` : '')" :title="pt.kecukupan_label || ''"></span>
                                @if($internal)<a :href="pt.url" class="font-semibold text-saluran-600">Laporan</a>@endif
                            </span>
                            <span class="shrink-0 font-semibold" :style="`color:${pt.warna}`" x-text="pt.status_label"></span>
                        </li>
                    </template>
                </ul>
            </template>
        </div>
    </template>
    <p x-show="!s.laporan" class="mt-1 text-basalt-mute">Belum ada laporan PPA 7 hari terakhir.</p>
