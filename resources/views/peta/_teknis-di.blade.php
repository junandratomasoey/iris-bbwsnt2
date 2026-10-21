{{-- Tab "Data teknis": profil D.I., jaringan, dan tabel Sub-D.I. (fungsional vs tertanam). Variabel: $internal --}}
<section x-show="detailTab === 'teknis'" x-cloak class="text-xs">
    <div class="border-b border-savana-line px-4 py-3">
        <h3 class="text-sm font-semibold">Luas & kewenangan</h3>
        <dl class="mt-2 space-y-1">
            <div class="flex justify-between"><dt class="text-basalt-soft">Luas Permen</dt><dd><span x-text="fmt(detail.di.luas.permen)"></span> Ha</dd></div>
            <div class="flex justify-between"><dt class="text-basalt-soft">Luas baku</dt><dd><span x-text="fmt(detail.di.luas.baku)"></span> Ha</dd></div>
            <div class="flex justify-between"><dt class="text-basalt-soft">Luas fungsional</dt><dd><span x-text="fmt(detail.di.luas.fungsional)"></span> Ha</dd></div>
            <div class="flex justify-between"><dt class="text-basalt-soft">Luas potensial</dt><dd><span x-text="fmt(detail.di.luas.potensial)"></span> Ha</dd></div>
            <div class="flex justify-between"><dt class="text-basalt-soft">Luas tertanam (laporan berlaku)</dt><dd><span x-text="diTertanam === null ? '–' : fmt(diTertanam, 1)"></span> Ha<span x-show="persen(diTertanam, diFungsional) !== null" class="text-basalt-soft" x-text="` · ${fmt(persen(diTertanam, diFungsional), 1)}%`"></span></dd></div>
            <div class="flex justify-between"><dt class="text-basalt-soft">Kewenangan</dt><dd x-text="detail.di.kewenangan || '–'"></dd></div>
            <div x-show="detail.di.sumber_air" class="flex justify-between gap-4"><dt class="text-basalt-soft">Sumber air</dt><dd class="text-right" x-text="detail.di.sumber_air"></dd></div>
            <div x-show="detail.di.keterangan" class="flex justify-between gap-4"><dt class="text-basalt-soft">Keterangan</dt><dd class="text-right" x-text="detail.di.keterangan"></dd></div>
            <div x-show="detail.di.tenaga_op.length" class="flex justify-between gap-4"><dt class="text-basalt-soft">Tenaga O&amp;P</dt><dd class="text-right" x-text="detail.di.tenaga_op.map((t) => t.label + ' ' + t.n).join(', ')"></dd></div>
            <div x-show="detail.p3a.length" class="flex justify-between gap-4"><dt class="shrink-0 text-basalt-soft" x-text="`P3A (${detail.p3a.length})`"></dt><dd class="text-right" x-text="detail.p3a.join(', ')"></dd></div>
        </dl>
    </div>

    <div class="border-b border-savana-line px-4 py-3">
        <h3 class="text-sm font-semibold">Jaringan irigasi</h3>
        <dl class="mt-2 space-y-1">
            <div class="flex justify-between gap-4"><dt class="text-basalt-soft">Sub-D.I.</dt><dd class="text-right" x-text="`${detail.teknis.jumlah_sub} · baku ${fmt(detail.teknis.luas_baku_sub)} Ha` + (detail.teknis.luas_fungsional_sub ? ` · fungsional ${fmt(detail.teknis.luas_fungsional_sub)} Ha` : '')"></dd></div>
            <div class="flex justify-between gap-4"><dt class="text-basalt-soft">Petak tersier</dt><dd class="text-right" x-text="detail.teknis.jumlah_petak ? `${detail.teknis.jumlah_petak} petak · ${fmt(detail.teknis.luas_petak, 1)} Ha` : '–'"></dd></div>
            <div class="flex justify-between gap-4"><dt class="shrink-0 text-basalt-soft">Pintu air</dt><dd class="text-right" x-text="detail.teknis.pintu.length ? detail.teknis.pintu.map((p) => `${p.label} ${p.n}`).join(' · ') : '–'"></dd></div>
            <div class="flex justify-between gap-4"><dt class="shrink-0 text-basalt-soft">Bangunan</dt><dd class="text-right" x-text="detail.teknis.bangunan.length ? detail.teknis.bangunan.map((b) => `${b.label} ${b.n}`).join(' · ') : '–'"></dd></div>
            <div x-show="detail.teknis.saluran_m" class="flex justify-between gap-4"><dt class="text-basalt-soft">Panjang saluran (data Sub-D.I.)</dt><dd class="text-right"><span x-text="fmt(detail.teknis.saluran_m / 1000, 2)"></span> km</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-basalt-soft">Batas areal Sub-D.I. di peta</dt><dd class="text-right" x-text="`${detail.teknis.sub_berbatas} dari ${detail.teknis.jumlah_sub}`"></dd></div>
        </dl>
    </div>

    <div class="px-4 py-3">
        <h3 class="text-sm font-semibold">Sub-D.I.: fungsional & tertanam</h3>
        <div class="mt-2 overflow-x-auto">
            <table class="w-full text-right">
                <thead class="text-[11px] text-basalt-soft"><tr><th class="py-1 text-left font-medium">Sub-D.I.</th><th class="font-medium">Baku</th><th class="font-medium">Fungs.</th><th class="font-medium">Tanam</th><th class="font-medium">%</th></tr></thead>
                <tbody class="divide-y divide-savana-line">
                    <template x-for="s in detail.sub" :key="s.id">
                        <tr class="cursor-pointer hover:bg-savana" @click="bukaSub(s)">
                            <td class="py-1.5 text-left"><span class="mr-1 inline-block h-2 w-2 rounded-full" :style="`background:${s.warna}`"></span><span class="font-semibold text-saluran-700" x-text="s.nama"></span></td>
                            <td x-text="fmt(s.luas_ha)"></td>
                            <td x-text="s.luas_fungsional_ha === null ? '–' : fmt(s.luas_fungsional_ha)"></td>
                            <td x-text="s.tertanam === null ? '–' : fmt(s.tertanam, 1)"></td>
                            <td x-text="s.persen_tertanam === null ? '–' : fmt(s.persen_tertanam, 0)"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <p class="mt-1 text-[11px] text-basalt-mute">Luas dalam Ha. % = tertanam terhadap luas fungsional (atau baku bila fungsional belum diisi). Ketuk baris untuk membuka Sub-D.I.</p>
        @if($internal)<a x-show="detail.di.url" :href="detail.di.url" class="mt-3 inline-block text-sm font-semibold text-saluran-600">Buka profil D.I. lengkap</a>@endif
    </div>
</section>
